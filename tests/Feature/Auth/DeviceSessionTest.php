<?php

use App\Domain\Access\Enums\Role;
use App\Domain\Auth\Actions\IssueDeviceToken;
use App\Domain\Auth\Actions\RevokeDevice;
use App\Domain\Auth\Data\DeviceInfo;
use App\Domain\Auth\Data\IssuedToken;
use App\Domain\Auth\Data\PhoneNumber;
use App\Domain\Auth\Enums\DevicePlatform;
use App\Models\User;

function issueTokenFor(User $user): IssuedToken
{
    return app(IssueDeviceToken::class)->handle($user, new DeviceInfo('Itel A70', DevicePlatform::Android), 'otp');
}

it('se déconnecte : le jeton de l’appareil devient inutilisable [MOB-31]', function () {
    $issued = issueTokenFor(User::factory()->withRole(Role::Relais)->create());

    $this->withToken($issued->plainTextToken)->postJson(route('api.v1.auth.logout'))->assertNoContent();

    expect($issued->device->fresh()->revoked_at)->not->toBeNull();
    $this->app['auth']->forgetGuards();
    $this->withToken($issued->plainTextToken)->getJson(route('api.v1.me.show'))->assertUnauthorized();
});

it('permet à l’administrateur de révoquer un appareil perdu ou volé [MOB-31]', function () {
    $issued = issueTokenFor(User::factory()->withRole(Role::Relais)->create());
    $admin = User::factory()->withRole(Role::Admin)->create();

    app(RevokeDevice::class)->handle($issued->device, $admin, 'lost');

    $this->withToken($issued->plainTextToken)->getJson(route('api.v1.me.show'))->assertUnauthorized();
    $this->assertDatabaseHas('audit_logs', ['action' => 'auth.device.revoked', 'actor_id' => $admin->id]);
});

it('n’affecte que l’appareil révoqué', function () {
    $user = User::factory()->withRole(Role::Relais)->create();
    $phone = issueTokenFor($user);
    $tablet = issueTokenFor($user);

    app(RevokeDevice::class)->handle($phone->device);

    $this->withToken($tablet->plainTextToken)->getJson(route('api.v1.me.show'))->assertOk();
});

it('enregistre le jeton de notifications push de l’appareil courant [MOB-21]', function () {
    $issued = issueTokenFor(User::factory()->withRole(Role::Relais)->create());

    $this->withToken($issued->plainTextToken)
        ->putJson(route('api.v1.devices.current'), ['fcm_token' => 'fcm-token-de-test', 'app_version' => '1.1.0'])
        ->assertOk()
        ->assertJsonPath('data.push_enabled', true)
        ->assertJsonPath('data.app_version', '1.1.0')
        ->assertJsonMissingPath('data.fcm_token');

    expect($issued->device->fresh()->fcm_token)->toBe('fcm-token-de-test');
});

it('connecte par mot de passe un Relais qui en a défini un [MOB-01]', function () {
    $user = User::factory()->withRole(Role::Relais)->create(['password' => 'un-mot-de-passe-solide']);
    $user->setPhoneNumber(PhoneNumber::parse('0505050505'))->save();

    $this->postJson(route('api.v1.auth.login'), ['phone' => '05 05 05 05 05', 'password' => 'un-mot-de-passe-solide', ...deviceFields()])
        ->assertCreated()
        ->assertJsonStructure(['data' => ['token', 'device' => ['uuid'], 'user' => ['uuid']]]);
});

it('refuse un mauvais mot de passe avec un message générique', function () {
    $user = User::factory()->create(['password' => 'un-mot-de-passe-solide']);
    $user->setPhoneNumber(PhoneNumber::parse('0505050505'))->save();

    $this->postJson(route('api.v1.auth.login'), ['phone' => '0505050505', 'password' => 'mauvais', ...deviceFields()])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['phone' => 'Identifiants incorrects.']);

    $this->postJson(route('api.v1.auth.login'), ['phone' => '0101010101', 'password' => 'mauvais', ...deviceFields()])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['phone' => 'Identifiants incorrects.']);
});

it('limite les tentatives de connexion par mot de passe [§10 Sécurité]', function () {
    foreach (range(1, 5) as $attempt) {
        $this->postJson(route('api.v1.auth.login'), ['phone' => '0505050505', 'password' => 'x', ...deviceFields()])->assertUnprocessable();
    }

    $this->postJson(route('api.v1.auth.login'), ['phone' => '0505050505', 'password' => 'x', ...deviceFields()])->assertTooManyRequests();
});

it('permet de définir puis changer son mot de passe', function () {
    $issued = issueTokenFor(User::factory()->withRole(Role::Relais)->create(['password' => null]));

    $this->withToken($issued->plainTextToken)
        ->putJson(route('api.v1.me.password'), ['password' => 'nouveau-mot-de-passe', 'password_confirmation' => 'nouveau-mot-de-passe'])
        ->assertNoContent();

    $this->withToken($issued->plainTextToken)
        ->putJson(route('api.v1.me.password'), ['password' => 'encore-un-autre', 'password_confirmation' => 'encore-un-autre'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['current_password']);
});

it('refuse les routes protégées sans jeton', function (string $method, string $route) {
    $this->json($method, route($route))->assertUnauthorized();
})->with([
    ['GET', 'api.v1.me.show'],
    ['POST', 'api.v1.auth.logout'],
    ['PUT', 'api.v1.me.password'],
    ['PUT', 'api.v1.devices.current'],
]);

it('refuse un jeton d’application à un compte de l’équipe, qui passe par la console et sa double authentification (revue sécurité)', function () {
    $staff = User::factory()->withTwoFactor()->withRole(Role::Coordonnateur)->create(['password' => 'mot-de-passe-console']);
    $staff->setPhoneNumber(PhoneNumber::parse('0505050505'))->save();

    $this->postJson(route('api.v1.auth.login'), ['phone' => '0505050505', 'password' => 'mot-de-passe-console', ...deviceFields()])
        ->assertUnprocessable();

    expect($staff->tokens()->count())->toBe(0);
});
