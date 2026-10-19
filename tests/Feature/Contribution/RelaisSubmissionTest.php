<?php

use App\Domain\Access\Enums\Role;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Contribution\Events\RelaisStatusChanged;
use App\Domain\Contribution\Models\Category;
use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Contribution\States\Received;
use App\Domain\Contribution\States\Uploading;
use App\Domain\Relais\Models\RelaisProfile;
use App\Domain\Territoire\Models\Commune;
use App\Models\User;
use Clickbar\Magellan\Data\Geometries\LineString;
use Clickbar\Magellan\Data\Geometries\MultiPolygon;
use Clickbar\Magellan\Data\Geometries\Point;
use Clickbar\Magellan\Data\Geometries\Polygon;
use Database\Seeders\ListsSeeder;
use Illuminate\Support\Facades\Event;
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

    $this->profile = RelaisProfile::factory()->create(['commune_id' => $this->cocody->id]);
    $this->relais = $this->profile->user;
    Sanctum::actingAs($this->relais);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function relaisPayload(array $overrides = []): array
{
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
        ...$overrides,
    ];
}

function createRelais(array $overrides = [], bool $withMedia = true): TestResponse
{
    $response = test()->postJson(route('api.v1.relais.store'), relaisPayload($overrides));

    if ($withMedia && $response->isSuccessful()) {
        attachReadyMedia(RelaisItem::withoutCommuneRestriction()->where('uuid', $response->json('data.uuid'))->firstOrFail());
    }

    return $response;
}

it('crée un relais en brouillon (envoi en cours) [MOB-14, MOB-19]', function () {
    createRelais()
        ->assertCreated()
        ->assertJsonPath('data.status', 'uploading')
        ->assertJsonPath('data.status_label', 'Envoi en cours')
        ->assertJsonPath('data.protections.credit_mode', 'anonymous')
        ->assertJsonMissingPath('data.internal_note');

    expect(RelaisItem::query()->sole()->state)->toBeInstanceOf(Uploading::class);
});

it('est idempotent : le même client_uuid ne crée jamais deux relais [MOB-16]', function () {
    $payload = relaisPayload();

    $this->postJson(route('api.v1.relais.store'), $payload)->assertCreated();
    $this->postJson(route('api.v1.relais.store'), $payload)->assertOk();

    expect(RelaisItem::query()->count())->toBe(1);

    Sanctum::actingAs(RelaisProfile::factory()->create()->user);
    $this->postJson(route('api.v1.relais.store'), $payload)->assertConflict();
});

it('route vers la commune du GPS plutôt que la commune déclarée [§8.2]', function () {
    $uuid = createRelais()->json('data.uuid');
    Event::fake([RelaisStatusChanged::class]);

    $this->postJson(route('api.v1.relais.submit', $uuid))
        ->assertOk()
        ->assertJsonPath('data.status', 'received')
        ->assertJsonPath('data.commune.name', 'Yopougon');

    $relais = RelaisItem::query()->sole();
    expect($relais->routed_by)->toBe('gps')
        ->and($relais->state)->toBeInstanceOf(Received::class)
        ->and($relais->sla_due_at->diffInMinutes($relais->received_at, true))->toBe(30.0)
        ->and($relais->statusHistory()->sole()->to)->toBe('received')
        ->and(AuditLog::query()->where('action', 'relais.received')->sole()->commune_id)->toBe($this->yopougon->id);
    Event::assertDispatched(RelaisStatusChanged::class);
});

it('se rabat sur la commune déclarée sans GPS exploitable [§8.2]', function () {
    $uuid = createRelais(['location' => ['lat' => 7.69, 'lng' => -5.03], 'urgency' => 'normal'])->json('data.uuid');

    $this->postJson(route('api.v1.relais.submit', $uuid))->assertOk()->assertJsonPath('data.commune.name', 'Cocody');

    $relais = RelaisItem::query()->sole();
    expect($relais->routed_by)->toBe('declared')
        ->and($relais->sla_due_at->diffInMinutes($relais->received_at, true))->toBe(240.0);
});

it('refuse l’envoi sans les questions de protection ni la commune [MOB-15]', function () {
    $uuid = createRelais(['protections' => null, 'location' => null, 'declared_commune' => null, 'title' => null])->json('data.uuid');

    $this->postJson(route('api.v1.relais.submit', $uuid))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['has_minors', 'people_consent', 'credit_mode', 'title', 'declared_commune']);
});

it('exige au moins un média entièrement reçu avant l’envoi [MOB-11]', function () {
    $uuid = createRelais(withMedia: false)->json('data.uuid');

    $this->postJson(route('api.v1.relais.submit', $uuid))->assertJsonValidationErrors(['medias' => 'Ajoutez au moins une photo, une vidéo ou un enregistrement.']);
});

it('exige le pseudonyme quand le Relais veut être crédité sous pseudonyme', function () {
    $uuid = createRelais(['protections' => ['has_minors' => false, 'people_consent' => 'yes', 'credit_mode' => 'pseudonym']])->json('data.uuid');

    $this->postJson(route('api.v1.relais.submit', $uuid))->assertJsonValidationErrors('credit_name');
});

it('complète un brouillon, puis le verrouille après l’envoi [MOB-19]', function () {
    $uuid = createRelais(['title' => null])->json('data.uuid');

    $this->putJson(route('api.v1.relais.update', $uuid), ['title' => 'Marché de Wassakara'])->assertOk()->assertJsonPath('data.title', 'Marché de Wassakara');
    $this->postJson(route('api.v1.relais.submit', $uuid))->assertOk();
    $this->postJson(route('api.v1.relais.submit', $uuid))->assertOk();

    $this->putJson(route('api.v1.relais.update', $uuid), ['title' => 'Autre'])->assertForbidden();
});

it('liste mes relais, du plus récent au plus ancien, avec le statut public [MOB-20]', function () {
    $first = createRelais(['title' => 'Premier'])->json('data.uuid');
    createRelais(['title' => 'Second']);
    $this->postJson(route('api.v1.relais.submit', $first))->assertOk();

    $this->getJson(route('api.v1.relais.index'))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.title', 'Second')
        ->assertJsonPath('data.1.status', 'received');
});

it('ne montre jamais le relais d’un autre Relais', function () {
    $uuid = createRelais()->json('data.uuid');

    Sanctum::actingAs(RelaisProfile::factory()->create()->user);

    $this->getJson(route('api.v1.relais.show', $uuid))->assertForbidden();
    $this->getJson(route('api.v1.relais.index'))->assertOk()->assertJsonCount(0, 'data');
});

it('refuse l’envoi à un Relais suspendu ou à un candidat', function () {
    Sanctum::actingAs(RelaisProfile::factory()->suspended()->create()->user);
    createRelais()->assertForbidden();

    Sanctum::actingAs(User::factory()->withRole(Role::Candidat)->create());
    createRelais()->assertForbidden();
});

it('valide les coordonnées et la date de capture', function () {
    createRelais(['location' => ['lat' => 120, 'lng' => 0], 'device_captured_at' => now()->addDay()->toIso8601String()])
        ->assertJsonValidationErrors(['location.lat', 'device_captured_at']);
});
