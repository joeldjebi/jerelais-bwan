<?php

use App\Domain\Access\Enums\Role;
use App\Domain\Contribution\Models\Category;
use App\Domain\Territoire\Models\Commune;
use App\Domain\Territoire\Models\LieuType;
use App\Domain\Territoire\Models\Region;
use App\Domain\Validation\Models\RejectionReason;
use App\Livewire\Console\Communes\Index as CommunesPage;
use App\Livewire\Console\Referentiels\Index as ReferentielsPage;
use App\Models\User;
use Livewire\Livewire;

function referentielsAdmin(): User
{
    return User::factory()->withTwoFactor()->withRole(Role::Admin)->create();
}

it('ajoute une localité à une commune et l’audite [ADM-20]', function () {
    $actor = referentielsAdmin();
    $commune = Commune::factory()->create(['name' => 'Abobo', 'slug' => 'abobo']);

    Livewire::actingAs($actor)->test(ReferentielsPage::class)
        ->set('commune', $commune->uuid)
        ->set('localiteType', 'quartier')
        ->set('name', 'Abobo-Gare')
        ->call('add')
        ->assertHasNoErrors()
        ->assertSee('Abobo-Gare');

    $this->assertDatabaseHas('localites', ['commune_id' => $commune->id, 'name' => 'Abobo-Gare', 'type' => 'quartier']);
    $this->assertDatabaseHas('audit_logs', ['action' => 'referentiel.localites.created', 'actor_id' => $actor->id, 'commune_id' => $commune->id]);
});

it('refuse une localité en double dans la même commune [ADM-20]', function () {
    $commune = Commune::factory()->create();
    $commune->localites()->create(['name' => 'Sogefiha', 'type' => 'quartier']);

    Livewire::actingAs(referentielsAdmin())->test(ReferentielsPage::class)
        ->set('commune', $commune->uuid)
        ->set('name', 'sogefiha')
        ->call('add')
        ->assertHasErrors('name');
});

it('ajoute puis renomme un type de lieu [ADM-20]', function () {
    $actor = referentielsAdmin();

    $component = Livewire::actingAs($actor)->test(ReferentielsPage::class)
        ->set('list', 'lieux')
        ->set('name', 'Gare routière')
        ->call('add');

    $type = LieuType::query()->where('slug', 'gare-routiere')->sole();

    $component->call('startEdit', $type->uuid)
        ->set('editingName', 'Gare routière et autogare')
        ->call('saveEdit')
        ->assertHasNoErrors();

    expect($type->fresh()->name)->toBe('Gare routière et autogare');
    $this->assertDatabaseHas('audit_logs', ['action' => 'referentiel.lieux.renamed', 'actor_id' => $actor->id]);
});

it('désactive une catégorie sans la supprimer [ADM-23]', function () {
    $category = Category::query()->create(['name' => 'Sport', 'slug' => 'sport', 'position' => 1]);

    Livewire::actingAs(referentielsAdmin())->test(ReferentielsPage::class)
        ->set('list', 'categories')
        ->call('toggle', $category->uuid);

    expect($category->fresh()->is_active)->toBeFalse();
    $this->assertDatabaseHas('audit_logs', ['action' => 'referentiel.categories.disabled']);
});

it('ajoute un motif de rejet propre aux relais, en fin de liste [ADM-23, COO-15]', function () {
    RejectionReason::query()->create(['applies_to' => 'relais', 'label' => 'Hors sujet', 'slug' => 'hors-sujet', 'position' => 4]);

    Livewire::actingAs(referentielsAdmin())->test(ReferentielsPage::class)
        ->set('list', 'motifs')
        ->set('target', 'relais')
        ->set('name', 'Visage d’un mineur non flouté')
        ->call('add')
        ->assertHasNoErrors();

    $reason = RejectionReason::query()->where('label', 'Visage d’un mineur non flouté')->sole();

    expect($reason->position)->toBe(5)
        ->and($reason->applies_to->value)->toBe('relais');
});

it('refuse les référentiels à un coordonnateur [Annexe A]', function () {
    $commune = Commune::factory()->create();

    $this->actingAs(User::factory()->withTwoFactor()->coordonnateurOf($commune)->create())
        ->get(route('console.referentiels'))
        ->assertForbidden();
});

it('affiche la page des référentiels à l’administrateur', function () {
    $this->actingAs(referentielsAdmin())
        ->get(route('console.referentiels'))
        ->assertOk()
        ->assertSee('Référentiels')
        ->assertSee('Motifs de rejet');
});

it('crée une commune fermée aux Relais, rattachée à sa région, et l’audite [ADM-20]', function () {
    $actor = referentielsAdmin();
    $region = Region::factory()->create(['name' => 'Gbêkê']);

    Livewire::actingAs($actor)->test(CommunesPage::class)
        ->call('openCreate')
        ->set('newName', 'Bouaké')
        ->set('newRegion', $region->uuid)
        ->set('newCode', 'BKE')
        ->call('create')
        ->assertHasNoErrors()
        ->assertSet('creating', false)
        ->assertSee('Bouaké');

    $commune = Commune::query()->where('slug', 'bouake')->sole();

    expect($commune->is_active)->toBeFalse()
        ->and($commune->region_id)->toBe($region->id)
        ->and($commune->code)->toBe('BKE');
    $this->assertDatabaseHas('audit_logs', ['action' => 'territoire.commune.created', 'actor_id' => $actor->id, 'commune_id' => $commune->id]);
});

it('refuse une commune en double et renomme une commune existante [ADM-20]', function () {
    $commune = Commune::factory()->create(['name' => 'Bingerville', 'slug' => 'bingerville']);

    $page = Livewire::actingAs(referentielsAdmin())->test(CommunesPage::class)
        ->set('newName', 'bingerville')
        ->call('create')
        ->assertHasErrors('newName');

    $page->call('startEdit', $commune->uuid)
        ->set('editName', 'Bingerville-Ville')
        ->call('saveEdit')
        ->assertHasNoErrors();

    expect($commune->fresh()->name)->toBe('Bingerville-Ville');
    $this->assertDatabaseHas('audit_logs', ['action' => 'territoire.commune.updated']);
});

it('ajoute une région avec son district [ADM-20]', function () {
    Livewire::actingAs(referentielsAdmin())->test(ReferentielsPage::class)
        ->set('list', 'regions')
        ->set('name', 'Gbêkê')
        ->set('district', 'Vallée du Bandama')
        ->call('add')
        ->assertHasNoErrors()
        ->assertSee('Vallée du Bandama');

    $this->assertDatabaseHas('regions', ['name' => 'Gbêkê', 'district' => 'Vallée du Bandama', 'slug' => 'gbeke']);
});

it('refuse la création de commune à un coordonnateur [Annexe A]', function () {
    $commune = Commune::factory()->create();

    Livewire::actingAs(User::factory()->withTwoFactor()->coordonnateurOf($commune)->create())
        ->test(CommunesPage::class)
        ->assertForbidden();
});
