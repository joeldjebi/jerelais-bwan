<?php

use App\Domain\Access\Enums\Role;
use App\Domain\AppelRelais\Actions\PublishAppel;
use App\Domain\AppelRelais\Enums\AppelStatus;
use App\Domain\AppelRelais\Models\AppelRelais;
use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Identity\Models\IdentityVerification;
use App\Domain\Relais\Models\RelaisProfile;
use App\Domain\Settings\Settings;
use App\Domain\Territoire\Models\Commune;
use App\Domain\Territoire\Models\LieuType;
use App\Livewire\Console\Appels\Index as Appels;
use App\Livewire\Console\Appels\Show as AppelShow;
use App\Livewire\Console\Validation\Index as Queue;
use App\Models\User;
use Clickbar\Magellan\Data\Geometries\LineString;
use Clickbar\Magellan\Data\Geometries\MultiPolygon;
use Clickbar\Magellan\Data\Geometries\Point;
use Clickbar\Magellan\Data\Geometries\Polygon;
use Database\Seeders\ListsSeeder;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(ListsSeeder::class);
    $this->sms = fakeSms();

    $square = fn (float $lat, float $lng) => MultiPolygon::make([Polygon::make([LineString::make([
        Point::makeGeodetic($lat - 0.02, $lng - 0.02), Point::makeGeodetic($lat - 0.02, $lng + 0.02),
        Point::makeGeodetic($lat + 0.02, $lng + 0.02), Point::makeGeodetic($lat + 0.02, $lng - 0.02),
        Point::makeGeodetic($lat - 0.02, $lng - 0.02),
    ])])], 4326);

    $this->yopougon = Commune::factory()->create(['name' => 'Yopougon', 'geom' => $square(5.34, -4.07)]);
    $this->bouake = Commune::factory()->create(['name' => 'Bouaké', 'geom' => $square(7.69, -5.03)]);
    $this->relaisYop = RelaisProfile::factory()->create(['commune_id' => $this->yopougon->id]);
    $this->relaisBouake = RelaisProfile::factory()->create(['commune_id' => $this->bouake->id]);
    $this->redacteur = User::factory()->withTwoFactor()->withRole(Role::RedacteurEnChef)->create();
});

/**
 * @param  array<string, mixed>  $overrides
 */
function launch(User $by, array $overrides = []): AppelRelais
{
    return app(PublishAppel::class)->handle($by, [
        'title' => 'Inondations : l’état des routes',
        'instructions' => 'Filmez les routes coupées, sans vous mettre en danger.',
        'target' => 'communes',
        'deadline_at' => now()->addDay()->toDateTimeString(),
        'communes' => [test()->yopougon->uuid],
        ...$overrides,
    ]);
}

it('cible les Relais des communes choisies et les prévient par SMS [ADM-14, MOB-23]', function () {
    $appel = launch($this->redacteur);

    expect($appel->status)->toBe(AppelStatus::Open)
        ->and($appel->recipients_count)->toBe(1)
        ->and($this->sms->sent)->toHaveCount(1)
        ->and($this->sms->sent[0]['to'])->toBe($this->relaisYop->user->phoneNumber()->e164)
        ->and($this->sms->sent[0]['message'])->toContain('appel à relais');
});

it('cible un rayon autour d’un point grâce aux contours des communes (ST_DWithin)', function () {
    $proche = launch($this->redacteur, ['target' => 'radius', 'lat' => 5.36, 'lng' => -4.05, 'radius_km' => 3, 'communes' => []]);
    $loin = launch($this->redacteur, ['target' => 'radius', 'lat' => 6.5, 'lng' => -4.5, 'radius_km' => 3, 'communes' => []]);

    expect($proche->recipients_count)->toBe(1)->and($loin->recipients_count)->toBe(0);
});

it('cible une région ou tout le réseau', function () {
    expect(launch($this->redacteur, ['target' => 'regions', 'regions' => [$this->bouake->region->uuid]])->recipients_count)->toBe(1)
        ->and(launch($this->redacteur, ['target' => 'national'])->recipients_count)->toBe(2);
});

it('enregistre le type de lieu sans restreindre les destinataires, et le montre dans l’API [ADM-14, MOB-23]', function () {
    $universite = LieuType::query()->create(['name' => 'Université', 'slug' => 'universite']);
    RelaisProfile::factory()->create(['commune_id' => $this->yopougon->id]);

    $appel = launch($this->redacteur, ['lieu_type_id' => $universite->id]);

    expect($appel->recipients_count)->toBe(2)
        ->and($appel->fresh()->lieuType->name)->toBe('Université');

    Sanctum::actingAs($this->relaisYop->user);
    $this->getJson(route('api.v1.appels.show', $appel))->assertOk()
        ->assertJsonPath('data.lieu_type', 'Université')
        ->assertJsonPath('data.zone', ['Yopougon'])
        ->assertJsonPath('data.zone_label', 'Communes : Yopougon');
});

it('touche les Relais inscrits par l’application sans commune, par la commune de leur identité ou de leurs relais récents [MOB-23]', function () {
    $parIdentite = RelaisProfile::factory()->create();
    $parIdentite->forceFill(['commune_id' => null])->save();
    IdentityVerification::query()->forceCreate(['user_id' => $parIdentite->user_id, 'commune_id' => $this->yopougon->id, 'status' => 'draft']);

    $parRelais = RelaisProfile::factory()->create();
    $parRelais->forceFill(['commune_id' => null])->save();
    RelaisItem::factory()->create(['user_id' => $parRelais->user_id, 'relais_profile_id' => $parRelais->id, 'commune_id' => $this->yopougon->id]);

    $ailleurs = RelaisProfile::factory()->create();
    $ailleurs->forceFill(['commune_id' => null])->save();

    $appel = launch($this->redacteur);

    expect($appel->recipients_count)->toBe(3);

    Sanctum::actingAs($parIdentite->user);
    $this->getJson(route('api.v1.appels.index'))->assertJsonCount(1, 'data');
    Sanctum::actingAs($ailleurs->user);
    $this->getJson(route('api.v1.appels.index'))->assertJsonCount(0, 'data');
});

it('cible un rayon aussi par la position GPS des relais récents, sans contour de commune [MOB-23]', function () {
    $sansCommune = RelaisProfile::factory()->create();
    $sansCommune->forceFill(['commune_id' => null])->save();
    RelaisItem::factory()->create([
        'user_id' => $sansCommune->user_id, 'relais_profile_id' => $sansCommune->id, 'commune_id' => null,
        'location' => Point::makeGeodetic(6.82, -5.28),
    ]);

    $appel = launch($this->redacteur, ['target' => 'radius', 'lat' => 6.8, 'lng' => -5.3, 'radius_km' => 10, 'communes' => []]);

    expect($appel->recipients_count)->toBe(1);

    Sanctum::actingAs($sansCommune->user);
    $this->getJson(route('api.v1.appels.show', $appel))->assertOk()
        ->assertJsonPath('data.radius.radius_km', 10)
        ->assertJsonPath('data.zone_label', 'Rayon de 10,0 km autour de 6.8000, -5.3000');
});

it('modifie un appel depuis sa fiche : zone, type de lieu, date ; seuls les nouveaux Relais sont prévenus [ADM-14]', function () {
    $appel = launch($this->redacteur);
    $marche = LieuType::query()->create(['name' => 'Marché', 'slug' => 'marche']);
    expect($this->sms->sent)->toHaveCount(1);

    Livewire::actingAs($this->redacteur)->test(AppelShow::class, ['appel' => $appel])
        ->call('openEdit')
        ->assertSet('title', 'Inondations : l’état des routes')
        ->assertSet('selectedCommunes', [$this->yopougon->uuid])
        ->set('title', 'Inondations : routes et marchés')
        ->set('selectedCommunes', [$this->yopougon->uuid, $this->bouake->uuid])
        ->assertSet('audienceCount', 2)
        ->set('lieuType', $marche->uuid)
        ->set('deadline', now()->addDays(3)->timezone('Africa/Abidjan')->format('Y-m-d\TH:i'))
        ->call('update')
        ->assertHasNoErrors()
        ->assertSet('editing', false)
        ->assertSee('Communes : Yopougon, Bouaké')
        ->assertSee('Marché');

    $appel->refresh();
    expect($appel->title)->toBe('Inondations : routes et marchés')
        ->and($appel->lieuType->name)->toBe('Marché')
        ->and($appel->recipients_count)->toBe(2)
        ->and($this->sms->sent)->toHaveCount(2)
        ->and($this->sms->sent[1]['to'])->toBe($this->relaisBouake->user->phoneNumber()->e164);
    $this->assertDatabaseHas('audit_logs', ['action' => 'appel.updated', 'actor_id' => $this->redacteur->id]);

    Sanctum::actingAs($this->relaisBouake->user);
    $this->getJson(route('api.v1.appels.show', $appel))->assertOk()->assertJsonPath('data.lieu_type', 'Marché');
});

it('passe d’une zone par communes à une zone par régions en modification', function () {
    $appel = launch($this->redacteur);

    Livewire::actingAs($this->redacteur)->test(AppelShow::class, ['appel' => $appel])
        ->call('openEdit')
        ->call('$set', 'target', 'regions')
        ->call('update')
        ->assertHasErrors('selectedRegions')
        ->set('selectedRegions', [$this->bouake->region->uuid])
        ->call('update')
        ->assertHasNoErrors();

    $appel->refresh();
    expect($appel->target->value)->toBe('regions')
        ->and($appel->communes()->count())->toBe(0)
        ->and($appel->regions()->pluck('regions.id')->all())->toBe([$this->bouake->region_id]);
});

it('refuse de modifier un appel clos ou celui d’un autre coordonnateur', function () {
    $appel = launch($this->redacteur);
    app(PublishAppel::class)->close($appel, $this->redacteur);

    expect(fn () => app(PublishAppel::class)->update($appel->fresh(), $this->redacteur, [
        'title' => 'x', 'instructions' => 'y', 'target' => 'national', 'deadline_at' => now()->addDay()->toDateTimeString(),
    ]))->toThrow(InvalidArgumentException::class);

    $other = User::factory()->withTwoFactor()->coordonnateurOf($this->bouake)->create();
    $local = launch($other, ['communes' => [$this->bouake->uuid]]);
    $coordoBouake = User::factory()->withTwoFactor()->coordonnateurOf($this->bouake)->create();

    Livewire::actingAs($coordoBouake)->test(AppelShow::class, ['appel' => $local])
        ->call('openEdit')
        ->assertForbidden();
});

it('affiche la zone, le type de lieu et les Relais prévenus dans la liste de la console', function () {
    $ecole = LieuType::query()->create(['name' => 'École', 'slug' => 'ecole']);
    launch($this->redacteur, ['lieu_type_id' => $ecole->id]);

    Livewire::actingAs($this->redacteur)->test(Appels::class)
        ->assertSee('Communes : Yopougon')
        ->assertSee('École')
        ->assertSee('1 Relais prévenu');
});

it('annonce dans le formulaire combien de Relais seront prévenus', function () {
    Livewire::actingAs($this->redacteur)->test(Appels::class)
        ->call('openCreate')
        ->set('target', 'communes')
        ->assertSet('audienceCount', 0)
        ->assertSee('Aucun Relais dans cette zone')
        ->set('selectedCommunes', [$this->yopougon->uuid])
        ->assertSet('audienceCount', 1)
        ->set('target', 'national')
        ->assertSet('audienceCount', 2);
});

it('montre au Relais les appels de sa zone seulement [MOB-23]', function () {
    $appel = launch($this->redacteur);

    Sanctum::actingAs($this->relaisYop->user);
    $this->getJson(route('api.v1.appels.index'))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Inondations : l’état des routes');
    $this->getJson(route('api.v1.appels.show', $appel))->assertOk();

    Sanctum::actingAs($this->relaisBouake->user);
    $this->getJson(route('api.v1.appels.index'))->assertJsonCount(0, 'data');
    $this->getJson(route('api.v1.appels.show', $appel))->assertNotFound();
});

it('rattache la réponse à l’appel et la fait remonter en priorité dans la file [§3.3]', function () {
    $appel = launch($this->redacteur);
    Sanctum::actingAs($this->relaisYop->user);

    fakeMultipart();
    $this->postJson(route('api.v1.relais.store'), relaisApiPayload(['appel' => $appel->uuid, 'declared_commune' => $this->yopougon->uuid]))
        ->assertCreated();
    expect(RelaisItem::query()->where('appel_relais_id', $appel->id)->count())->toBe(1);

    Sanctum::actingAs($this->relaisBouake->user);
    $this->postJson(route('api.v1.relais.store'), relaisApiPayload(['appel' => $appel->uuid]))
        ->assertJsonValidationErrors(['appel' => 'Cet appel ne concerne pas votre zone ou il est clos.']);

    $sansAppel = RelaisItem::factory()->create(['commune_id' => $this->yopougon->id, 'title' => 'Sans appel', 'received_at' => now()->subHour()]);
    $reponse = RelaisItem::factory()->create(['commune_id' => $this->yopougon->id, 'title' => 'Réponse à l’appel', 'appel_relais_id' => $appel->id, 'received_at' => now()]);
    $coordo = User::factory()->withTwoFactor()->coordonnateurOf($this->yopougon)->create();

    expect(Livewire::actingAs($coordo)->test(Queue::class)->instance()->items->pluck('title')->take(2)->all())->toBe(['Réponse à l’appel', 'Sans appel']);
});

it('limite l’appel local d’un coordonnateur à ses communes et peut exiger l’accord de l’administrateur [COO-21]', function () {
    $coordo = User::factory()->withTwoFactor()->coordonnateurOf($this->yopougon)->create();
    app(Settings::class)->set('appels.local_requires_approval', true);

    $appel = launch($coordo, ['target' => 'national', 'communes' => [$this->bouake->uuid]]);

    expect($appel->is_local)->toBeTrue()
        ->and($appel->status)->toBe(AppelStatus::PendingApproval)
        ->and($appel->communes->pluck('name')->all())->toBe(['Yopougon'])
        ->and($this->sms->sent)->toBe([]);

    $admin = User::factory()->withTwoFactor()->withRole(Role::Admin)->create();
    app(PublishAppel::class)->approve($appel, $admin);

    expect($appel->fresh()->status)->toBe(AppelStatus::Open)->and($this->sms->sent)->toHaveCount(1);
});

it('crée un appel depuis la console', function () {
    Livewire::actingAs($this->redacteur)->test(Appels::class)
        ->call('openCreate')
        ->set('title', 'Rentrée scolaire')
        ->set('instructions', 'Ambiance devant les écoles, sans filmer les enfants de face.')
        ->set('target', 'communes')
        ->set('selectedCommunes', [$this->yopougon->uuid])
        ->call('create')
        ->assertRedirect();

    expect(AppelRelais::query()->sole()->title)->toBe('Rentrée scolaire');
});

it('clôt automatiquement les appels échus', function () {
    $appel = launch($this->redacteur, ['deadline_at' => now()->addHour()->toDateTimeString()]);
    $this->travel(2)->hours();

    $this->artisan('appels:close-expired')->assertSuccessful();

    expect($appel->fresh()->status)->toBe(AppelStatus::Closed);
});

it('réserve la création d’appels aux rôles habilités [Annexe A]', function () {
    $this->actingAs(User::factory()->withTwoFactor()->withRole(Role::LectureSeule)->create())->get(route('console.appels'))->assertForbidden();
});

it('n’ouvre pas à un coordonnateur la fiche d’un appel local d’une autre commune (revue sécurité)', function () {
    $other = User::factory()->withTwoFactor()->coordonnateurOf($this->bouake)->create();
    $appel = launch($other, ['target' => 'communes', 'communes' => [$this->bouake->uuid]]);
    $coordo = User::factory()->withTwoFactor()->coordonnateurOf($this->yopougon)->create();

    $this->actingAs($coordo)->get(route('console.appels.show', $appel))->assertNotFound();
    $this->actingAs($other)->get(route('console.appels.show', $appel))->assertOk();
});
