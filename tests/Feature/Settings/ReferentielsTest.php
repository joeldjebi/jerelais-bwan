<?php

use App\Domain\Access\Enums\Role;
use App\Domain\Contribution\Models\Category;
use App\Domain\Territoire\Models\Commune;
use App\Domain\Territoire\Models\LieuType;
use App\Domain\Validation\Models\RejectionReason;
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
