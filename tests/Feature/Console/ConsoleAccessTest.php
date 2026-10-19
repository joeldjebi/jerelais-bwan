<?php

use App\Domain\Access\Enums\Role;
use App\Domain\Territoire\Models\Commune;
use App\Models\User;

it('renvoie un visiteur non connecté vers la page de connexion', function () {
    $this->get(route('console.home'))->assertRedirect(route('login'));
});

it('affiche la page de connexion en français', function () {
    $this->get(route('login'))->assertOk()->assertSee('Connexion')->assertSee('Mot de passe');
});

it('refuse la console à un Relais ou un candidat [Annexe A]', function (Role $role) {
    $this->actingAs(User::factory()->withRole($role)->create())
        ->get(route('console.home'))
        ->assertForbidden();
})->with([Role::Relais, Role::Candidat]);

it('impose la double authentification à l’équipe avant toute page de la console [ADM-24]', function () {
    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->get(route('console.home'))
        ->assertRedirect(route('console.security'));
});

it('ouvre la console une fois la double authentification confirmée', function () {
    $this->actingAs(User::factory()->withTwoFactor()->withRole(Role::Journaliste)->create(['name' => 'Awa Traoré']))
        ->get(route('console.home'))
        ->assertOk()
        ->assertSee('Bonjour, Awa.')
        ->assertSee('Journaliste')
        ->assertDontSee('Mise en service');
});

it('affiche le périmètre communal d’un coordonnateur', function () {
    $commune = Commune::factory()->create(['name' => 'Yopougon', 'slug' => 'yopougon']);

    $this->actingAs(User::factory()->withTwoFactor()->coordonnateurOf($commune)->create())
        ->get(route('console.home'))
        ->assertOk()
        ->assertSee('Coordination')
        ->assertSee('Yopougon');
});

it('propose l’activation de la double authentification sur la page de sécurité', function () {
    $this->actingAs(User::factory()->withRole(Role::Admin)->create())
        ->get(route('console.security'))
        ->assertOk()
        ->assertSee('Activer la double authentification');
});

it('connecte un membre de l’équipe puis lui demande son code de double authentification', function () {
    User::factory()->withTwoFactor()->withRole(Role::Admin)->create(['email' => 'admin@reflet.tv', 'password' => 'mot-de-passe-console']);

    $this->post(route('login'), ['email' => 'admin@reflet.tv', 'password' => 'mot-de-passe-console'])
        ->assertRedirect(route('two-factor.login'));

    $this->assertGuest();
});

it('refuse la connexion web d’un Relais, même avec le bon mot de passe', function () {
    User::factory()->withRole(Role::Relais)->create(['email' => 'relais@exemple.ci', 'password' => 'mot-de-passe-relais']);

    $this->post(route('login'), ['email' => 'relais@exemple.ci', 'password' => 'mot-de-passe-relais'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('refuse la connexion d’un membre de l’équipe suspendu', function () {
    User::factory()->suspended()->withRole(Role::Admin)->create(['email' => 'ancien@reflet.tv', 'password' => 'mot-de-passe-console']);

    $this->post(route('login'), ['email' => 'ancien@reflet.tv', 'password' => 'mot-de-passe-console'])
        ->assertSessionHasErrors('email');
});

it('crée le premier administrateur en ligne de commande', function () {
    $this->artisan('jerelais:create-admin', ['email' => 'Direction@Reflet.tv', '--name' => 'Direction'])
        ->expectsQuestion('Mot de passe (10 caractères minimum)', 'un-mot-de-passe-solide')
        ->assertSuccessful();

    $admin = User::query()->where('email', 'direction@reflet.tv')->sole();

    expect($admin->hasRole(Role::Admin->value))->toBeTrue()
        ->and($admin->hasConfirmedTwoFactor())->toBeFalse();
});
