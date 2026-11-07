<?php

use App\Domain\Access\Concerns\BelongsToCommune;
use App\Domain\Access\Enums\Role;
use App\Domain\Access\Support\CommuneAccess;
use App\Domain\Territoire\Models\Commune;
use App\Domain\Territoire\Models\Region;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modèle de test portant une donnée communale, pour éprouver le mécanisme indépendamment des modules métier.
 */
class DonneeCommunale extends Model
{
    use BelongsToCommune;

    protected $table = 'donnees_communales_test';

    protected $guarded = [];

    public $timestamps = false;
}

beforeEach(function () {
    Schema::create('donnees_communales_test', function (Blueprint $table) {
        $table->id();
        $table->foreignId('commune_id')->constrained();
        $table->string('titre');
    });

    $this->abidjan = Region::factory()->create();
    $this->gbeke = Region::factory()->create();
    $this->yopougon = Commune::factory()->create(['region_id' => $this->abidjan->id]);
    $this->cocody = Commune::factory()->create(['region_id' => $this->abidjan->id]);
    $this->bouake = Commune::factory()->create(['region_id' => $this->gbeke->id]);

    DonneeCommunale::query()->insert([
        ['commune_id' => $this->yopougon->id, 'titre' => 'Relais de Yopougon'],
        ['commune_id' => $this->cocody->id, 'titre' => 'Relais de Cocody'],
        ['commune_id' => $this->bouake->id, 'titre' => 'Relais de Bouaké'],
    ]);
});

it('un coordonnateur ne voit que les données de sa commune [§6, Annexe A]', function () {
    $this->actingAs(User::factory()->coordonnateurOf($this->yopougon)->create());

    expect(DonneeCommunale::query()->pluck('titre')->all())->toBe(['Relais de Yopougon']);
});

it('un coordonnateur ne peut pas lire une donnée d’une autre commune, même par son identifiant', function () {
    $this->actingAs(User::factory()->coordonnateurOf($this->yopougon)->create());
    $cocodyId = DonneeCommunale::withoutCommuneRestriction()->where('commune_id', $this->cocody->id)->value('id');

    expect(DonneeCommunale::query()->find($cocodyId))->toBeNull()
        ->and(app(CommuneAccess::class)->canAccessCommune(auth()->user(), $this->cocody))->toBeFalse();
});

it('un coordonnateur de plusieurs communes (suppléance) les voit toutes', function () {
    $user = User::factory()->coordonnateurOf($this->yopougon)->create();
    $user->communes()->attach($this->cocody->id, ['assignment' => 'suppleant']);
    $this->actingAs($user);

    expect(DonneeCommunale::query()->count())->toBe(2);
});

it('un superviseur voit les communes de sa région', function () {
    $user = User::factory()->withRole(Role::Superviseur)->create();
    $user->regions()->attach($this->abidjan->id);
    $this->actingAs($user);

    expect(DonneeCommunale::query()->pluck('titre')->sort()->values()->all())->toBe(['Relais de Cocody', 'Relais de Yopougon']);
});

it('l’administration et la rédaction voient tout le réseau', function (Role $role) {
    $this->actingAs(User::factory()->withRole($role)->create());

    expect(DonneeCommunale::query()->count())->toBe(3);
})->with([Role::Admin, Role::RedacteurEnChef, Role::Journaliste, Role::LectureSeule]);

it('un coordonnateur sans commune rattachée ne voit rien', function () {
    $this->actingAs(User::factory()->withRole(Role::Coordonnateur)->create());

    expect(DonneeCommunale::query()->count())->toBe(0);
});
