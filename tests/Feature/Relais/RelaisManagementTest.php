<?php

use App\Domain\Access\Enums\Role;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Candidature\Models\Candidature;
use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Contribution\States\Validated;
use App\Domain\Messagerie\Models\Conversation;
use App\Domain\Relais\Enums\RelaisStatus;
use App\Domain\Relais\Models\RelaisProfile;
use App\Domain\Relais\Notifications\RelaisStatusChangedByCoordinator;
use App\Domain\Territoire\Models\Commune;
use App\Livewire\Console\Dashboard;
use App\Livewire\Console\Relais\Index;
use App\Livewire\Console\Relais\Show;
use App\Models\User;
use Database\Seeders\ListsSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(ListsSeeder::class);
    $this->sms = fakeSms();
    Notification::fake();
    $this->yopougon = Commune::factory()->create(['name' => 'Yopougon']);
    $this->coordo = User::factory()->withTwoFactor()->coordonnateurOf($this->yopougon)->create(['name' => 'Coordo Yop']);
    $this->admin = User::factory()->withTwoFactor()->withRole(Role::Admin)->create();
    $this->profile = RelaisProfile::factory()->create(['commune_id' => $this->yopougon->id, 'display_name' => 'Awa K.']);
});

it('liste les Relais de la commune du coordonnateur seulement [COO-04]', function () {
    RelaisProfile::factory()->create(['commune_id' => Commune::factory()->create()->id, 'display_name' => 'Koffi Y.']);

    Livewire::actingAs($this->coordo)->test(Index::class)->assertSee('Awa K.')->assertDontSee('Koffi Y.');
    Livewire::actingAs($this->admin)->test(Index::class)->assertSee('Awa K.')->assertSee('Koffi Y.');
});

it('montre la fiche, l’historique et la fiabilité du Relais [COO-04]', function () {
    RelaisItem::factory()->count(2)->create(['user_id' => $this->profile->user_id, 'relais_profile_id' => $this->profile->id, 'commune_id' => $this->yopougon->id, 'state' => Validated::class]);

    Livewire::actingAs($this->coordo)->test(Show::class, ['profile' => $this->profile])
        ->assertSee($this->profile->number)
        ->assertSet('stats', fn (array $stats) => $stats['sent'] === 2 && $stats['reliability'] === 100);
});

it('suspend un Relais avec un motif, informe l’administration et bloque ses envois [COO-04]', function () {
    Livewire::actingAs($this->coordo)->test(Show::class, ['profile' => $this->profile])
        ->call('open', 'suspended')
        ->call('apply')
        ->assertHasErrors('reason')
        ->set('reason', 'Envois répétés de vidéos anciennes.')
        ->call('apply')
        ->assertHasNoErrors();

    expect($this->profile->fresh()->status)->toBe(RelaisStatus::Suspended)
        ->and(AuditLog::query()->where('action', 'relais.profile_suspended')->sole()->payload['reason'])->toBe('Envois répétés de vidéos anciennes.')
        ->and($this->sms->sent[0]['message'])->toContain('suspendu');
    Notification::assertSentTo($this->admin, RelaisStatusChangedByCoordinator::class);

    Sanctum::actingAs($this->profile->user);
    $this->postJson(route('api.v1.relais.store'), ['client_uuid' => (string) Str::uuid()])->assertForbidden();
});

it('réserve la réintégration d’un Relais radié à l’administration [ADM-22]', function () {
    $this->profile->forceFill(['status' => RelaisStatus::StruckOff])->save();

    Livewire::actingAs($this->coordo)->test(Show::class, ['profile' => $this->profile])
        ->call('open', 'active')->set('reason', 'Erreur')->call('apply')->assertHasErrors('reason');

    Livewire::actingAs($this->admin)->test(Show::class, ['profile' => $this->profile])
        ->call('open', 'active')->set('reason', 'Recours accepté')->call('apply')->assertHasNoErrors();

    expect($this->profile->fresh()->status)->toBe(RelaisStatus::Active);
    Notification::assertNothingSent();
});

it('transfère un Relais de commune (administration) [ADM-22]', function () {
    $cocody = Commune::factory()->create(['name' => 'Cocody']);
    $general = Conversation::withoutCommuneRestriction()->create(['relais_profile_id' => $this->profile->id, 'commune_id' => $this->yopougon->id]);

    Livewire::actingAs($this->admin)->test(Show::class, ['profile' => $this->profile])
        ->call('open', 'transfer')->set('targetCommune', $cocody->uuid)->set('reason', 'Déménagement')->call('apply')->assertHasNoErrors();

    expect($this->profile->fresh()->commune_id)->toBe($cocody->id);

    Livewire::actingAs($this->coordo)->test(Show::class, ['profile' => $this->profile->fresh()])->assertStatus(404);

    // L'ancien coordonnateur perd aussi le dossier de candidature et la conversation générale (revue sécurité).
    $candidature = Candidature::withoutCommuneRestriction()->findOrFail($this->profile->candidature_id);
    expect($candidature->commune_id)->toBe($cocody->id)
        ->and($general->fresh()->commune_id)->toBe($cocody->id)
        ->and($this->coordo->can('view', $candidature))->toBeFalse()
        ->and($this->coordo->can('viewIdentity', $candidature))->toBeFalse();
});

it('révoque les jetons de l’application à la radiation [ADM-22]', function () {
    $this->profile->user->createToken('android');

    Livewire::actingAs($this->admin)->test(Show::class, ['profile' => $this->profile])
        ->call('open', 'struck_off')->set('reason', 'Manquement grave à la charte')->call('apply')->assertHasNoErrors();

    expect($this->profile->user->tokens()->count())->toBe(0);
});

it('affiche le tableau de bord de la commune avec ses vrais chiffres [COO-22]', function () {
    RelaisItem::factory()->create(['commune_id' => $this->yopougon->id, 'title' => 'En attente à Yopougon']);

    Livewire::actingAs($this->coordo)->test(Dashboard::class)
        ->assertSee('En attente à Yopougon')
        ->assertSee('Couverture par quartier')
        ->assertSet('communeStats', fn (array $stats) => $stats['to_validate'] === 1 && $stats['active_relais'] === 1);
});
