<?php

use App\Domain\Access\Enums\Role;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Contribution\Events\RelaisStatusChanged;
use App\Domain\Contribution\Models\Category;
use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Contribution\States\ComplementRequested;
use App\Domain\Contribution\States\InReview;
use App\Domain\Contribution\States\Received;
use App\Domain\Contribution\States\Uploading;
use App\Domain\Contribution\States\Validated;
use App\Domain\Media\Actions\PurgeAbandonedUploads;
use App\Domain\Media\Models\Media;
use App\Domain\Relais\Enums\RelaisStatus;
use App\Domain\Relais\Models\RelaisProfile;
use App\Domain\Territoire\Models\Commune;
use App\Models\User;
use Clickbar\Magellan\Data\Geometries\LineString;
use Clickbar\Magellan\Data\Geometries\MultiPolygon;
use Clickbar\Magellan\Data\Geometries\Point;
use Clickbar\Magellan\Data\Geometries\Polygon;
use Database\Seeders\ListsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(ListsSeeder::class);
    $this->category = Category::query()->active()->firstOrFail();

    // Yopougon : un carré autour de (5.34, -4.07) ; Cocody : sans contour, ouverte.
    $square = fn (float $lat, float $lng) => MultiPolygon::make([Polygon::make([LineString::make([
        Point::makeGeodetic($lat - 0.05, $lng - 0.05), Point::makeGeodetic($lat - 0.05, $lng + 0.05),
        Point::makeGeodetic($lat + 0.05, $lng + 0.05), Point::makeGeodetic($lat + 0.05, $lng - 0.05),
        Point::makeGeodetic($lat - 0.05, $lng - 0.05),
    ])])], 4326);
    $this->yopougon = Commune::factory()->create(['name' => 'Yopougon', 'geom' => $square(5.34, -4.07)]);
    $this->cocody = Commune::factory()->create(['name' => 'Cocody']);

    $this->storage = fakeMultipart();
    $this->profile = RelaisProfile::factory()->create(['commune_id' => $this->cocody->id]);
    $this->relais = $this->profile->user;
    Sanctum::actingAs($this->relais);
});

/**
 * Contenu d'une photo de test (JPEG valide).
 */
function photoBytes(): string
{
    $image = imagecreatetruecolor(400, 300);
    imagefill($image, 0, 0, imagecolorallocate($image, 242, 140, 40));
    ob_start();
    imagejpeg($image, null, 85);

    return (string) ob_get_clean();
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function relaisPayload(array $overrides = []): array
{
    $photo = photoBytes();

    return [
        'client_uuid' => (string) Str::uuid(),
        'category' => test()->category->uuid,
        'urgency' => 'urgent',
        'title' => 'Inondation au carrefour Siporex',
        'description' => 'La route est coupée depuis ce matin.',
        'location' => ['lat' => 5.341, 'lng' => -4.069, 'accuracy_m' => 12],
        'declared_commune' => test()->cocody->uuid,
        'device_captured_at' => now()->subMinutes(3)->toIso8601String(),
        'device_model' => 'Tecno Spark 10',
        'protections' => ['has_minors' => false, 'people_consent' => 'not_applicable', 'credit_mode' => 'anonymous'],
        'medias' => [[
            'client_uuid' => (string) Str::uuid(), 'kind' => 'photo', 'mime' => 'image/jpeg',
            'size' => strlen($photo), 'sha256' => hash('sha256', $photo), 'filename' => 'IMG_2041.jpg',
        ]],
        ...$overrides,
    ];
}

function sendRelais(array $overrides = []): TestResponse
{
    return test()->postJson(route('api.v1.relais.store'), relaisPayload($overrides));
}

/**
 * Dépose les fichiers annoncés dans la réponse (comme le ferait l'application) puis clôt chaque envoi.
 */
function uploadFiles(TestResponse $response): void
{
    $photo = photoBytes();

    foreach ($response->json('uploads') as $upload) {
        $etag = test()->storage->putPart(Media::query()->where('uuid', $upload['uuid'])->value('upload_id'), 1, $photo);
        test()->postJson(route('api.v1.medias.complete', $upload['uuid']), ['parts' => [['number' => 1, 'etag' => $etag]]])->assertOk();
    }
}

it('envoie un relais en un seul appel : contenu et fichiers, avec les URL d’envoi [MOB-14, MOB-19]', function () {
    sendRelais()
        ->assertCreated()
        ->assertJsonPath('data.status', 'uploading')
        ->assertJsonPath('data.protections.credit_mode', 'anonymous')
        ->assertJsonPath('uploads.0.status', 'pending')
        ->assertJsonPath('uploads.0.upload.missing_parts.0.number', 1)
        ->assertJsonMissingPath('data.internal_note');

    expect(RelaisItem::query()->sole()->state)->toBeInstanceOf(Uploading::class);
});

it('soumet automatiquement le relais dès que son dernier fichier est reçu, routé vers la commune du GPS [§8.2]', function () {
    Event::fake([RelaisStatusChanged::class]);
    uploadFiles(sendRelais());

    $relais = RelaisItem::query()->sole();
    expect($relais->state)->toBeInstanceOf(Received::class)
        ->and($relais->routed_by)->toBe('gps')
        ->and($relais->commune_id)->toBe($this->yopougon->id)
        ->and($relais->sla_due_at->diffInMinutes($relais->received_at, true))->toBe(30.0)
        ->and(AuditLog::query()->where('action', 'relais.received')->sole()->commune_id)->toBe($this->yopougon->id);
    Event::assertDispatched(RelaisStatusChanged::class);

    $this->getJson(route('api.v1.relais.show', $relais->uuid))->assertJsonPath('data.status', 'received');
});

it('attend tous les fichiers avant de soumettre le relais', function () {
    $photo = photoBytes();
    $second = ['client_uuid' => (string) Str::uuid(), 'kind' => 'photo', 'mime' => 'image/jpeg', 'size' => strlen($photo), 'sha256' => hash('sha256', $photo)];
    $response = sendRelais(['medias' => [relaisPayload()['medias'][0], $second]])->assertCreated()->assertJsonCount(2, 'uploads');

    $first = $response->json('uploads.0.uuid');
    $etag = $this->storage->putPart(Media::query()->where('uuid', $first)->value('upload_id'), 1, $photo);
    $this->postJson(route('api.v1.medias.complete', $first), ['parts' => [['number' => 1, 'etag' => $etag]]])->assertOk();

    expect(RelaisItem::query()->sole()->state)->toBeInstanceOf(Uploading::class);

    uploadFiles(new TestResponse(response()->json(['uploads' => [['uuid' => $response->json('uploads.1.uuid')]]])));

    expect(RelaisItem::query()->sole()->state)->toBeInstanceOf(Received::class);
});

it('se rabat sur la commune déclarée sans GPS exploitable [§8.2]', function () {
    uploadFiles(sendRelais(['location' => ['lat' => 7.69, 'lng' => -5.03], 'urgency' => 'normal']));

    $relais = RelaisItem::query()->sole();
    expect($relais->routed_by)->toBe('declared')
        ->and($relais->commune_id)->toBe($this->cocody->id)
        ->and($relais->sla_due_at->diffInMinutes($relais->received_at, true))->toBe(240.0);
});

it('refuse d’emblée un relais incomplet, sans rien créer : protections, titre, catégorie, lieu, fichiers [MOB-11, MOB-15]', function () {
    sendRelais(['protections' => null, 'location' => null, 'declared_commune' => null, 'title' => null, 'category' => null, 'medias' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['protections', 'title', 'category', 'location', 'medias']);

    expect(RelaisItem::query()->count())->toBe(0);
});

it('refuse un relais dont le lieu n’est rattaché à aucune commune', function () {
    sendRelais(['location' => ['lat' => 7.69, 'lng' => -5.03], 'declared_commune' => null])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['declared_commune']);

    expect(RelaisItem::query()->count())->toBe(0);
});

it('exige le pseudonyme quand le Relais veut être crédité sous pseudonyme', function () {
    sendRelais(['protections' => ['has_minors' => false, 'people_consent' => 'yes', 'credit_mode' => 'pseudonym']])
        ->assertJsonValidationErrors('protections.credit_name');
});

it('refuse un fichier trop lourd, en indiquant lequel, sans rien créer', function () {
    $payload = relaisPayload();
    $payload['medias'][0]['size'] = 900 * 1024 * 1024;

    $this->postJson(route('api.v1.relais.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors(['medias.0.size']);

    expect(RelaisItem::query()->count())->toBe(0);
});

it('est idempotent : le même client_uuid ne crée jamais deux relais, et redonne les URL d’envoi [MOB-16]', function () {
    $payload = relaisPayload();

    $this->postJson(route('api.v1.relais.store'), $payload)->assertCreated();
    $this->postJson(route('api.v1.relais.store'), $payload)->assertOk()->assertJsonPath('uploads.0.upload.missing_parts.0.number', 1);

    expect(RelaisItem::query()->count())->toBe(1);

    Sanctum::actingAs(RelaisProfile::factory()->create()->user);
    $this->postJson(route('api.v1.relais.store'), $payload)->assertConflict();
});

it('liste mes relais, du plus récent au plus ancien, avec le statut public [MOB-20]', function () {
    uploadFiles(sendRelais(['title' => 'Premier']));
    sendRelais(['title' => 'Second']);

    $this->getJson(route('api.v1.relais.index'))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.title', 'Second')
        ->assertJsonPath('data.1.status', 'received');
});

it('ne montre jamais le relais d’un autre Relais', function () {
    $uuid = sendRelais()->assertCreated()->json('data.uuid');

    Sanctum::actingAs(RelaisProfile::factory()->create()->user);

    $this->getJson(route('api.v1.relais.show', $uuid))->assertForbidden();
    $this->getJson(route('api.v1.relais.index'))->assertOk()->assertJsonCount(0, 'data');
});

it('refuse l’envoi à un Relais suspendu ou à un candidat', function () {
    Sanctum::actingAs(RelaisProfile::factory()->suspended()->create()->user);
    sendRelais()->assertForbidden();

    Sanctum::actingAs(User::factory()->withRole(Role::Candidat)->create());
    sendRelais()->assertForbidden();
});

it('ne soumet pas un relais dont le Relais a été suspendu pendant l’envoi des fichiers (revue sécurité)', function () {
    $response = sendRelais()->assertCreated();
    $this->profile->forceFill(['status' => RelaisStatus::Suspended, 'suspended_at' => now()])->save();

    $upload = $response->json('uploads.0.uuid');
    $etag = $this->storage->putPart(Media::query()->where('uuid', $upload)->value('upload_id'), 1, photoBytes());
    $this->postJson(route('api.v1.medias.complete', $upload), ['parts' => [['number' => 1, 'etag' => $etag]]])->assertForbidden();

    expect(RelaisItem::withoutCommuneRestriction()->value('submitted_at'))->toBeNull();
});

it('valide les coordonnées et la date de capture', function () {
    sendRelais(['location' => ['lat' => 120, 'lng' => 0], 'device_captured_at' => now()->addDay()->toIso8601String()])
        ->assertJsonValidationErrors(['location.lat', 'device_captured_at']);
});

it('ne propose plus d’envoi séparé : la modification passe par PUT /relais/{relais}', function () {
    expect(Route::has('api.v1.relais.submit'))->toBeFalse()
        ->and(Route::has('api.v1.relais.update'))->toBeTrue();
});

it('soumet quand même le relais si un de ses fichiers n’arrive jamais (abandonné après 48 h)', function () {
    $photo = photoBytes();
    $second = ['client_uuid' => (string) Str::uuid(), 'kind' => 'photo', 'mime' => 'image/jpeg', 'size' => strlen($photo), 'sha256' => hash('sha256', $photo)];
    $response = sendRelais(['medias' => [relaisPayload()['medias'][0], $second]])->assertCreated();

    $first = $response->json('uploads.0.uuid');
    $etag = $this->storage->putPart(Media::query()->where('uuid', $first)->value('upload_id'), 1, $photo);
    $this->postJson(route('api.v1.medias.complete', $first), ['parts' => [['number' => 1, 'etag' => $etag]]])->assertOk();

    $this->travel(49)->hours();
    app(PurgeAbandonedUploads::class)->handle();

    expect(RelaisItem::query()->sole()->state)->toBeInstanceOf(Received::class);
});

it('renvoie à l’application des messages lisibles pour un relais incomplet [MOB-19]', function () {
    $this->postJson(route('api.v1.relais.store'), ['protections' => ['credit_mode' => 'pseudonym'], 'medias' => [['kind' => 'photo']]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'location' => 'Indiquez la position GPS ou choisissez une commune.',
            'protections.credit_name' => 'Indiquez le pseudonyme à afficher à l’antenne.',
            'category' => 'Le champ catégorie est obligatoire.',
        ]);
});

/**
 * Envoi en une seule requête (multipart, champs à plat comme dans la documentation).
 *
 * @param  array<string, mixed>  $overrides
 */
function sendRelaisAtOnce(array $overrides = []): TestResponse
{
    return test()->post(route('api.v1.relais.store'), [
        'category' => test()->category->uuid,
        'title' => 'Inondation au carrefour Siporex',
        'description' => 'La route est coupée depuis ce matin.',
        'urgency' => 'urgent',
        'declared_commune' => test()->cocody->uuid,
        'has_minors' => 'false',
        'people_consent' => 'not_applicable',
        'credit_mode' => 'anonymous',
        'files' => [UploadedFile::fake()->createWithContent('photo.jpg', photoBytes())],
        ...$overrides,
    ], ['Accept' => 'application/json']);
}

it('envoie un relais en une seule requête : contenu et fichier ensemble, aussitôt reçu [MOB-19]', function () {
    $response = sendRelaisAtOnce()
        ->assertCreated()
        ->assertJsonPath('data.status', 'received')
        ->assertJsonPath('data.title', 'Inondation au carrefour Siporex')
        ->assertJsonPath('data.protections.has_minors', false)
        ->assertJsonCount(1, 'data.medias')
        ->assertJsonMissingPath('uploads');

    $relais = RelaisItem::withoutCommuneRestriction()->where('uuid', $response->json('data.uuid'))->sole();
    $media = $relais->medias()->sole();

    expect($relais->state)->toBeInstanceOf(Received::class)
        ->and($relais->commune_id)->toBe($this->cocody->id)
        ->and($media->declared_sha256)->toBe(hash('sha256', photoBytes()))
        ->and(Storage::disk('medias')->exists($media->path))->toBeTrue();
});

it('accepte la position GPS à plat (lat, lng) et route le relais vers la commune du point', function () {
    sendRelaisAtOnce(['declared_commune' => '', 'lat' => '5.34', 'lng' => '-4.07', 'accuracy_m' => '10'])
        ->assertCreated()
        ->assertJsonPath('data.commune.uuid', $this->yopougon->uuid);
});

it('reprend le même relais avec le même client_uuid, sans doublon', function () {
    $uuid = (string) Str::uuid();

    $first = sendRelaisAtOnce(['client_uuid' => $uuid])->assertCreated()->json('data.uuid');
    sendRelaisAtOnce(['client_uuid' => $uuid])->assertOk()->assertJsonPath('data.uuid', $first);

    expect(RelaisItem::withoutCommuneRestriction()->count())->toBe(1);
});

it('refuse un envoi sans fichier, un format inconnu ou un fichier de plus de 100 Mo, sans rien créer', function () {
    sendRelaisAtOnce(['files' => []])->assertUnprocessable()->assertJsonValidationErrors('files');
    sendRelaisAtOnce(['files' => [UploadedFile::fake()->create('doc.pdf', 20, 'application/pdf')]])->assertUnprocessable()->assertJsonValidationErrors('files.0');
    sendRelaisAtOnce(['files' => [UploadedFile::fake()->create('film.mp4', 101 * 1024, 'video/mp4')]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['files.0' => 'envoyez-le en morceaux']);
    sendRelaisAtOnce(['title' => '', 'credit_mode' => 'pseudonym'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['title', 'protections.credit_name']);

    expect(RelaisItem::withoutCommuneRestriction()->count())->toBe(0)
        ->and(Storage::disk('medias')->allFiles())->toBeEmpty();
});

/**
 * @param  array<string, mixed>  $data
 */
function updateRelais(string $uuid, array $data): TestResponse
{
    return test()->put(route('api.v1.relais.update', $uuid), $data, ['Accept' => 'application/json']);
}

it('modifie un relais avant sa prise en charge : seuls les champs envoyés changent [MOB-19]', function () {
    $uuid = sendRelaisAtOnce()->json('data.uuid');

    updateRelais($uuid, ['title' => 'Route coupée à Siporex', 'description' => '', 'urgency' => ''])
        ->assertOk()
        ->assertJsonPath('data.title', 'Route coupée à Siporex')
        ->assertJsonPath('data.description', 'La route est coupée depuis ce matin.')
        ->assertJsonPath('data.status', 'received');

    $this->assertDatabaseHas('audit_logs', ['action' => 'relais.updated']);
});

it('ajoute et retire des fichiers d’un relais envoyé, sans pouvoir tous les retirer', function () {
    $response = sendRelaisAtOnce();
    $uuid = $response->json('data.uuid');
    $first = $response->json('data.medias.0.uuid');

    updateRelais($uuid, ['files' => [UploadedFile::fake()->createWithContent('autre.jpg', photoBytes())], 'remove_medias' => [$first]])
        ->assertOk()
        ->assertJsonCount(1, 'data.medias');

    $remaining = RelaisItem::withoutCommuneRestriction()->where('uuid', $uuid)->sole()->medias()->sole();
    expect($remaining->uuid)->not->toBe($first);

    updateRelais($uuid, ['remove_medias' => [$remaining->uuid]])->assertUnprocessable()->assertJsonValidationErrors('files');
    updateRelais($uuid, ['remove_medias' => [(string) Str::uuid()]])->assertUnprocessable()->assertJsonValidationErrors('remove_medias');
});

it('déplace le relais vers la commune du nouveau lieu avant sa prise en charge', function () {
    $uuid = sendRelaisAtOnce()->json('data.uuid');

    updateRelais($uuid, ['lat' => '5.34', 'lng' => '-4.07'])->assertOk()->assertJsonPath('data.commune.uuid', $this->yopougon->uuid);
});

it('refuse une modification vide, et toute modification pendant l’examen ou après la décision (409)', function () {
    $uuid = sendRelaisAtOnce()->json('data.uuid');
    updateRelais($uuid, [])->assertUnprocessable()->assertJsonValidationErrors('relais');

    RelaisItem::withoutCommuneRestriction()->where('uuid', $uuid)->update(['state' => InReview::getMorphClass()]);
    updateRelais($uuid, ['title' => 'Autre'])->assertConflict();

    RelaisItem::withoutCommuneRestriction()->where('uuid', $uuid)->update(['state' => Validated::getMorphClass()]);
    updateRelais($uuid, ['title' => 'Autre'])->assertConflict();
});

it('répond à une demande de complément par la modification : le relais revient dans la file [COO-14]', function () {
    $uuid = sendRelaisAtOnce()->json('data.uuid');
    RelaisItem::withoutCommuneRestriction()->where('uuid', $uuid)->update(['state' => ComplementRequested::getMorphClass()]);

    updateRelais($uuid, ['files' => [UploadedFile::fake()->createWithContent('plan-large.jpg', photoBytes())], 'message' => 'Voici une vue plus large.'])
        ->assertOk()
        ->assertJsonPath('data.status', 'received')
        ->assertJsonCount(2, 'data.medias');
});

it('n’autorise pas la modification du relais d’un autre Relais', function () {
    $uuid = sendRelaisAtOnce()->json('data.uuid');
    Sanctum::actingAs(RelaisProfile::factory()->create()->user);

    updateRelais($uuid, ['title' => 'Piraté'])->assertNotFound();
});

it('accepte des champs facultatifs envoyés vides (urgence, description, emplacements de fichier)', function () {
    sendRelaisAtOnce(['urgency' => '', 'description' => '', 'declared_place' => '', 'files' => [UploadedFile::fake()->createWithContent('photo.jpg', photoBytes()), '', '']])
        ->assertCreated()
        ->assertJsonPath('data.urgency', 'normal')
        ->assertJsonCount(1, 'data.medias');
});

it('accepte la catégorie et la commune par leur slug, et génère client_uuid s’il est absent', function () {
    $response = sendRelaisAtOnce(['category' => $this->category->slug, 'declared_commune' => $this->cocody->slug])
        ->assertCreated()
        ->assertJsonPath('data.category.uuid', $this->category->uuid)
        ->assertJsonPath('data.commune.uuid', $this->cocody->uuid);

    expect(RelaisItem::withoutCommuneRestriction()->where('uuid', $response->json('data.uuid'))->value('client_uuid'))->toBeString();
});

it('explique une catégorie ou une commune inconnue', function () {
    sendRelaisAtOnce(['category' => 'inconnue', 'declared_commune' => 'atlantide'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'category' => 'Catégorie inconnue : choisissez-en une de GET /categories',
            'declared_commune' => 'Commune inconnue ou fermée',
        ]);
});
