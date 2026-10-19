<?php

use App\Domain\Access\Enums\Role;
use App\Domain\Audit\AuditLogger;
use App\Domain\Auth\Enums\UserStatus;
use App\Domain\Settings\Settings;
use App\Domain\Territoire\Models\Commune;
use App\Livewire\Console\Account\Index as AccountPage;
use App\Livewire\Console\Audit\Index as AuditPage;
use App\Livewire\Console\Communes\Index as CommunesPage;
use App\Livewire\Console\Dashboard;
use App\Livewire\Console\Settings\Index as SettingsPage;
use App\Livewire\Console\Team\Index as TeamPage;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

function admin(): User
{
    return User::factory()->withTwoFactor()->withRole(Role::Admin)->create(['name' => 'Awa Admin']);
}

it('affiche les pages de la console à l’administrateur', function (string $route, string $text) {
    $this->actingAs(admin())->get(route($route))->assertOk()->assertSee($text);
})->with([
    ['console.home', 'Mise en service'],
    ['console.communes', 'Ouvertes aux Relais'],
    ['console.team', 'Ajouter un membre'],
    ['console.settings', 'Durée maximale d’une vidéo'],
    ['console.audit', 'Vérifier l’intégrité'],
    ['console.account', 'Changer le mot de passe'],
]);

it('n’affiche à un coordonnateur que les pages de son rôle [Annexe A]', function (string $route) {
    $commune = Commune::factory()->create();

    $this->actingAs(User::factory()->withTwoFactor()->coordonnateurOf($commune)->create())
        ->get(route($route))
        ->assertForbidden();
})->with(['console.communes', 'console.team', 'console.settings', 'console.audit']);

it('montre au coordonnateur sa commune et sa file de validation', function () {
    $commune = Commune::factory()->create(['name' => 'Yopougon', 'slug' => 'yopougon']);

    $this->actingAs(User::factory()->withTwoFactor()->coordonnateurOf($commune)->create())
        ->get(route('console.home'))
        ->assertOk()
        ->assertSee('File de validation')
        ->assertSee('Yopougon')
        ->assertDontSee('Mise en service');
});

it('ouvre puis ferme une commune, avec confirmation et audit [ADM-20]', function () {
    $actor = admin();
    $commune = Commune::factory()->inactive()->create(['name' => 'Cocody', 'slug' => 'cocody']);

    Livewire::actingAs($actor)->test(CommunesPage::class)
        ->call('askToggle', $commune->uuid)
        ->assertSet('confirmingToggle', true)
        ->call('confirmToggle')
        ->assertDispatched('toast');

    expect($commune->fresh()->is_active)->toBeTrue();
    $this->assertDatabaseHas('audit_logs', ['action' => 'territoire.commune.opened', 'actor_id' => $actor->id, 'commune_id' => $commune->id]);
});

it('recherche une commune par son nom', function () {
    Commune::factory()->create(['name' => 'Treichville', 'slug' => 'treichville']);
    Commune::factory()->create(['name' => 'Bingerville', 'slug' => 'bingerville']);

    Livewire::actingAs(admin())->test(CommunesPage::class)
        ->set('search', 'treich')
        ->assertSee('Treichville')
        ->assertDontSee('Bingerville');
});

it('ajoute un coordonnateur rattaché à sa commune et lui envoie un lien d’activation [ADM-21]', function () {
    Notification::fake();
    $commune = Commune::factory()->create();

    Livewire::actingAs(admin())->test(TeamPage::class)
        ->call('openCreate')
        ->set('name', 'Kouassi Yao')
        ->set('email', 'kouassi@reflet.tv')
        ->set('newRole', Role::Coordonnateur->value)
        ->set('communeUuids', [$commune->uuid])
        ->call('create')
        ->assertHasNoErrors()
        ->assertSet('creating', false)
        ->assertDispatched('toast');

    $member = User::query()->where('email', 'kouassi@reflet.tv')->sole();

    expect($member->hasRole(Role::Coordonnateur->value))->toBeTrue()
        ->and($member->communes->pluck('id')->all())->toBe([$commune->id])
        ->and($member->password)->toBeNull();
    Notification::assertSentTo($member, ResetPassword::class);
    $this->assertDatabaseHas('audit_logs', ['action' => 'users.created']);
});

it('exige au moins une commune pour un coordonnateur', function () {
    Livewire::actingAs(admin())->test(TeamPage::class)
        ->set('name', 'Sans commune')
        ->set('email', 'sans@reflet.tv')
        ->set('newRole', Role::Coordonnateur->value)
        ->call('create')
        ->assertHasErrors(['communeUuids']);
});

it('suspend un membre et coupe ses accès, sans pouvoir se suspendre soi-même [ADM-21]', function () {
    $actor = admin();
    $member = User::factory()->withRole(Role::Journaliste)->create();
    $token = $member->createToken('test')->plainTextToken;

    Livewire::actingAs($actor)->test(TeamPage::class)
        ->call('askStatusChange', $member->uuid)
        ->call('confirmStatusChange')
        ->assertDispatched('toast');

    expect($member->fresh()->status)->toBe(UserStatus::Suspended)
        ->and($member->tokens()->count())->toBe(0);

    Livewire::actingAs($actor)->test(TeamPage::class)
        ->call('askStatusChange', $actor->uuid)
        ->call('confirmStatusChange')
        ->assertDispatched('toast', tone: 'error');

    expect($actor->fresh()->status)->toBe(UserStatus::Active);
});

it('enregistre un groupe de paramètres et l’audite [ADM-23]', function () {
    Livewire::actingAs(admin())->test(SettingsPage::class)
        ->set('values.validation__sla_urgent_minutes', 20)
        ->call('save', 'validation')
        ->assertHasNoErrors()
        ->assertDispatched('toast');

    expect(app(Settings::class)->get('validation.sla_urgent_minutes'))->toBe(20);
    $this->assertDatabaseHas('audit_logs', ['action' => 'settings.updated']);
});

it('refuse une valeur de paramètre invalide', function () {
    Livewire::actingAs(admin())->test(SettingsPage::class)
        ->set('values.media__video_max_seconds', 0)
        ->call('save', 'media')
        ->assertHasErrors(['values.media__video_max_seconds']);
});

it('vérifie l’intégrité du journal depuis la console [ADM-25]', function () {
    app(AuditLogger::class)->record('test.action');

    Livewire::actingAs(admin())->test(AuditPage::class)
        ->call('verify')
        ->assertSet('integrity.broken_at', null)
        ->assertSee('Chaîne d’empreintes intacte');
});

it('met à jour son profil et son mot de passe', function () {
    $user = User::factory()->withTwoFactor()->withRole(Role::Journaliste)->create(['password' => 'ancien-mot-de-passe']);

    Livewire::actingAs($user)->test(AccountPage::class)
        ->set('name', 'Nouveau Nom')
        ->call('saveProfile')
        ->assertHasNoErrors()
        ->set('currentPassword', 'ancien-mot-de-passe')
        ->set('password', 'nouveau-mot-de-passe')
        ->set('passwordConfirmation', 'nouveau-mot-de-passe')
        ->call('savePassword')
        ->assertHasNoErrors();

    expect($user->fresh()->name)->toBe('Nouveau Nom')
        ->and(Hash::check('nouveau-mot-de-passe', $user->fresh()->password))->toBeTrue();
});

it('affiche l’activité récente sur l’accueil de l’administrateur', function () {
    $actor = admin();
    app(AuditLogger::class)->record('settings.updated', payload: ['key' => 'media.video_max_seconds'], actor: $actor);

    Livewire::actingAs($actor)->test(Dashboard::class)
        ->assertSee('Paramètre modifié · Durée maximale d’une vidéo');
});
