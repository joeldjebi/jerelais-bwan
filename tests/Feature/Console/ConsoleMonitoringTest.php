<?php

use App\Domain\Access\Enums\Role;
use App\Domain\Contribution\Actions\LockRelaisItem;
use App\Domain\Contribution\Models\Category;
use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Contribution\States\Validated;
use App\Domain\Contribution\Support\NetworkAlerts;
use App\Domain\Settings\Settings;
use App\Domain\Territoire\Models\Commune;
use App\Livewire\Console\Dashboard;
use App\Livewire\Console\Flux\Index as Flux;
use App\Livewire\Console\Map\Index as Map;
use App\Livewire\Console\Newsroom\Index as Newsroom;
use App\Livewire\Console\Validation\Index;
use App\Models\User;
use Clickbar\Magellan\Data\Geometries\Point;
use Database\Seeders\ListsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(ListsSeeder::class);
    $this->yopougon = Commune::factory()->create(['name' => 'Yopougon']);
    $this->cocody = Commune::factory()->create(['name' => 'Cocody']);
    $this->journaliste = User::factory()->withTwoFactor()->withRole(Role::Journaliste)->create();
});

it('montre à la rédaction tous les relais du réseau, avec leur statut [ADM-01]', function () {
    RelaisItem::factory()->create(['commune_id' => $this->yopougon->id, 'title' => 'Marché inondé']);
    RelaisItem::factory()->create(['commune_id' => $this->cocody->id, 'title' => 'Embouteillage géant', 'state' => Validated::class]);

    Livewire::actingAs($this->journaliste)->test(Flux::class)
        ->assertSee('Marché inondé')
        ->assertSee('Embouteillage géant')
        ->assertSee('Validé');
});

it('filtre par texte, commune, catégorie, statut, urgence et Relais [ADM-04]', function () {
    $category = Category::query()->where('slug', '!=', '')->skip(1)->firstOrFail();
    $cible = RelaisItem::factory()->urgent()->create(['commune_id' => $this->yopougon->id, 'title' => 'Incendie à Siporex', 'category_id' => $category->id]);
    RelaisItem::factory()->create(['commune_id' => $this->cocody->id, 'title' => 'Route coupée']);

    $titles = fn ($component) => $component->instance()->items->pluck('title')->all();
    $component = Livewire::actingAs($this->journaliste)->test(Flux::class);

    expect($titles($component->set('filters.q', 'siporex')))->toBe(['Incendie à Siporex']);
    expect($titles($component->set('filters.q', '')->set('filters.commune', $this->cocody->uuid)))->toBe(['Route coupée']);
    expect($titles($component->set('filters.commune', '')->set('filters.category', $category->uuid)))->toBe(['Incendie à Siporex']);
    expect($titles($component->set('filters.category', '')->set('filters.urgency', 'urgent')))->toBe(['Incendie à Siporex']);
    expect($titles($component->set('filters.urgency', '')->set('filters.relais', $cible->relaisProfile->number)))->toBe(['Incendie à Siporex']);
    expect($titles($component->call('resetFilters')))->toHaveCount(2);
});

it('limite le flux et la carte d’un coordonnateur à sa commune', function () {
    $coordo = User::factory()->withTwoFactor()->coordonnateurOf($this->yopougon)->create();
    RelaisItem::factory()->create(['commune_id' => $this->yopougon->id, 'title' => 'Chez nous', 'location' => Point::makeGeodetic(5.34, -4.07)]);
    RelaisItem::factory()->create(['commune_id' => $this->cocody->id, 'title' => 'Ailleurs', 'location' => Point::makeGeodetic(5.36, -3.98)]);

    Livewire::actingAs($coordo)->test(Flux::class)->assertSee('Chez nous')->assertDontSee('Ailleurs');

    $points = Livewire::actingAs($coordo)->test(Map::class)->instance()->points;
    expect(array_column($points, 'title'))->toBe(['Chez nous']);
});

it('signale un afflux inhabituel de relais sur une même commune [ADM-03]', function () {
    app(Settings::class)->set('alerts.surge_threshold', 3);
    RelaisItem::factory()->count(3)->create(['commune_id' => $this->yopougon->id, 'received_at' => now()->subMinutes(5)]);
    RelaisItem::factory()->count(2)->create(['commune_id' => $this->cocody->id, 'received_at' => now()->subMinutes(5)]);
    RelaisItem::factory()->urgent()->create(['commune_id' => $this->cocody->id, 'received_at' => now()->subHours(2), 'sla_due_at' => now()->subMinute()]);

    $alerts = app(NetworkAlerts::class)->current();

    expect($alerts['surges'])->toBe([['commune' => 'Yopougon', 'count' => 3]])
        ->and($alerts['urgent'])->toBe(1)
        ->and($alerts['overdue'])->toBe(1);

    $this->actingAs($this->journaliste)->get(route('console.flux'))->assertSee('Afflux à Yopougon');
});

it('place les relais localisés sur la carte, filtrables par période [ADM-02]', function () {
    RelaisItem::factory()->create(['commune_id' => $this->yopougon->id, 'title' => 'Récent', 'location' => Point::makeGeodetic(5.34, -4.07)]);
    RelaisItem::factory()->create(['commune_id' => $this->yopougon->id, 'title' => 'Ancien', 'location' => Point::makeGeodetic(5.35, -4.06), 'received_at' => now()->subDays(20)]);
    RelaisItem::factory()->create(['commune_id' => $this->yopougon->id, 'title' => 'Sans GPS']);

    $component = Livewire::actingAs($this->journaliste)->test(Map::class);
    expect(array_column($component->instance()->points, 'title'))->toBe(['Récent']);

    $component->set('period', '30')->assertDispatched('map-points');
    expect(array_column($component->instance()->points, 'title'))->toBe(['Récent', 'Ancien'])
        ->and($component->instance()->points[0])->toHaveKeys(['lat', 'lng', 'urgency', 'url']);
});

it('affiche la salle de rédaction en plein écran [ADM-05]', function () {
    RelaisItem::factory()->urgent()->create(['commune_id' => $this->yopougon->id, 'title' => 'Effondrement d’un immeuble']);

    $this->actingAs($this->journaliste)->get(route('console.newsroom'))
        ->assertOk()
        ->assertSee('Salle de rédaction')
        ->assertSee('Effondrement d’un immeuble')
        ->assertSee('Urgents en attente');

    expect(Livewire::actingAs($this->journaliste)->test(Newsroom::class)->instance()->today['received'])->toBe(1);
});

it('refuse le flux aux Relais et aux candidats', function () {
    $this->actingAs(User::factory()->withRole(Role::Relais)->create())->get(route('console.flux'))->assertForbidden();
});

it('affiche un relais verrouillé par un autre membre dans toutes les listes, sans chargement paresseux', function () {
    $coordo = User::factory()->withTwoFactor()->coordonnateurOf($this->yopougon)->create();
    $suppleant = User::factory()->withTwoFactor()->coordonnateurOf($this->yopougon)->create(['name' => 'Suppléant Yop']);
    $relais = RelaisItem::factory()->create(['commune_id' => $this->yopougon->id, 'title' => 'Relais réservé']);
    app(LockRelaisItem::class)->handle($relais, $suppleant);

    Livewire::actingAs($coordo)->test(Flux::class)->assertSee('Relais réservé')->assertSee('Suppléant Yop');
    Livewire::actingAs($coordo)->test(Dashboard::class)->assertSee('Relais réservé');
    Livewire::actingAs($coordo)->test(Index::class)->assertSee('Suppléant Yop');
});
