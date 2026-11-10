<?php

use App\Domain\Access\Actions\ReviewRegistration;
use App\Domain\Access\Enums\Role;
use App\Domain\Auth\Actions\IssueDeviceToken;
use App\Domain\Auth\Data\DeviceInfo;
use App\Domain\Auth\Data\PhoneNumber;
use App\Domain\Auth\Enums\DevicePlatform;
use App\Domain\Auth\Enums\UserStatus;
use App\Domain\Candidature\Enums\Gender;
use App\Domain\Candidature\Models\Candidature;
use App\Livewire\Console\Registrations\Index as RegistrationsPage;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;

beforeEach(function () {
    config(['jerelais.otp.fixed_code' => '1234']);
    $this->sms = fakeSms();
});

/**
 * @param  array<string, mixed>  $overrides
 */
function signUp(string $phone = '0707070707', array $overrides = []): TestResponse
{
    test()->postJson(route('api.v1.auth.register.request'), ['phone' => $phone])->assertAccepted();

    $token = test()->postJson(route('api.v1.auth.register.verify'), ['phone' => $phone, 'code' => '1234'])
        ->assertOk()
        ->json('data.registration_token');

    return test()->postJson(route('api.v1.auth.register.complete'), [
        'registration_token' => $token,
        'last_name' => 'Traoré',
        'first_names' => 'Awa Mariam',
        'gender' => 'femme',
        'birth_date' => '2001-04-17',
        'password' => '4826',
        'password_confirmation' => '4826',
        ...$overrides,
    ]);
}

function pinLogin(string $pin = '4826', string $phone = '0707070707', string $ip = '127.0.0.1'): TestResponse
{
    return test()->withServerVariables(['REMOTE_ADDR' => $ip])
        ->postJson(route('api.v1.auth.login'), ['phone' => $phone, 'password' => $pin, ...deviceFields()]);
}

function approveRegistration(string $phone = '0707070707'): User
{
    $user = User::findByPhone(PhoneNumber::parse($phone));
    app(ReviewRegistration::class)->approve($user, User::factory()->withRole(Role::Admin)->create());

    return $user->refresh();
}

it('inscrit en trois étapes : numéro, code 1234, identité et code PIN — compte en attente, sans jeton [MOB-01]', function () {
    signUp()
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonMissingPath('data.token');

    $user = User::findByPhone(PhoneNumber::parse('0707070707'));
    $candidature = Candidature::withoutCommuneRestriction()->where('user_id', $user->id)->sole();

    expect($this->sms->sent)->toBe([])
        ->and($user->status)->toBe(UserStatus::Pending)
        ->and($user->name)->toBe('Awa Mariam Traoré')
        ->and($user->hasRole(Role::Candidat->value))->toBeTrue()
        ->and($user->tokens()->count())->toBe(0)
        ->and($candidature->last_name)->toBe('Traoré')
        ->and($candidature->first_names)->toBe('Awa Mariam')
        ->and($candidature->gender)->toBe(Gender::Female)
        ->and($candidature->birth_date->toDateString())->toBe('2001-04-17');
});

it('refuse la connexion tant que l’inscription n’est pas validée, puis l’accepte [MOB-01]', function () {
    signUp()->assertCreated();

    pinLogin()
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['phone' => 'Votre inscription est en attente de validation par l’équipe JE RELAIS. Vous pourrez vous connecter dès qu’elle sera validée.']);

    approveRegistration();

    pinLogin()->assertCreated()->assertJsonPath('data.pin_required', false);
});

it('refuse aussi la connexion par code SMS d’une inscription en attente', function () {
    signUp()->assertCreated();

    $this->postJson(route('api.v1.auth.otp.request'), ['phone' => '0707070707'])->assertAccepted();
    $this->postJson(route('api.v1.auth.otp.verify'), ['phone' => '0707070707', 'code' => '1234', ...deviceFields()])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);
});

it('exige toutes les informations d’identité et un code PIN de 4 chiffres confirmé', function (array $overrides, string $field) {
    signUp(overrides: $overrides)->assertUnprocessable()->assertJsonValidationErrors([$field]);

    expect(User::query()->count())->toBe(0);
})->with([
    'nom manquant' => [['last_name' => ''], 'last_name'],
    'prénoms manquants' => [['first_names' => ''], 'first_names'],
    'sexe inconnu' => [['gender' => 'x'], 'gender'],
    'date de naissance mal formée' => [['birth_date' => '17/04/2001'], 'birth_date'],
    'PIN trop court' => [['password' => '123', 'password_confirmation' => '123'], 'password'],
    'PIN avec lettres' => [['password' => '12a4', 'password_confirmation' => '12a4'], 'password'],
    'confirmation différente' => [['password_confirmation' => '4827'], 'password'],
]);

it('n’accepte le jeton d’inscription qu’une seule fois et refuse un jeton inconnu', function () {
    signUp()->assertCreated();

    $this->postJson(route('api.v1.auth.register.complete'), [
        'registration_token' => str_repeat('a', 64), 'last_name' => 'Koné', 'first_names' => 'Ali', 'gender' => 'homme',
        'birth_date' => '1999-01-01', 'password' => '1111', 'password_confirmation' => '1111',
    ])->assertUnprocessable()->assertJsonValidationErrors(['registration_token' => 'Votre vérification a expiré. Recommencez l’inscription depuis le début.']);
});

it('refuse l’inscription d’un numéro qui a déjà un compte, sans envoyer de code', function () {
    $user = User::factory()->withRole(Role::Relais)->create();
    $user->setPhoneNumber(PhoneNumber::parse('0707070707'))->save();
    config(['jerelais.otp.fixed_code' => null]);

    $this->postJson(route('api.v1.auth.register.request'), ['phone' => '07 07 07 07 07'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['phone' => 'Ce numéro a déjà un compte. Connectez-vous avec votre code PIN.']);

    $this->postJson(route('api.v1.auth.register.verify'), ['phone' => '0707070707', 'code' => '1234'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['phone']);

    expect($this->sms->sent)->toBe([])->and(User::query()->count())->toBe(1);
});

it('bloque le numéro après 5 codes PIN erronés, même depuis des adresses IP différentes [§10 Sécurité]', function () {
    signUp()->assertCreated();
    approveRegistration();

    foreach (range(1, 5) as $attempt) {
        pinLogin('0000', ip: "10.0.0.{$attempt}")->assertUnprocessable();
    }

    pinLogin(ip: '10.0.0.99')
        ->assertTooManyRequests()
        ->assertJsonPath('message', 'Trop de codes PIN erronés. Réessayez dans 15 minutes ou redéfinissez votre code PIN par SMS.');

    $this->travel(16)->minutes();

    pinLogin(ip: '10.0.0.100')->assertCreated();
})->after(fn () => RateLimiter::clear('pin-failures:'.PhoneNumber::parse('0707070707')->hash()));

it('redéfinit un code PIN oublié après une connexion par code SMS, sans l’ancien, pendant 15 minutes seulement [MOB-01]', function () {
    signUp()->assertCreated();
    approveRegistration();

    $smsLogin = function (): string {
        $this->postJson(route('api.v1.auth.otp.request'), ['phone' => '0707070707'])->assertAccepted();

        return $this->postJson(route('api.v1.auth.otp.verify'), ['phone' => '0707070707', 'code' => '1234', ...deviceFields()])
            ->assertCreated()
            ->json('data.token');
    };

    $token = $smsLogin();
    $this->withToken($token)->putJson(route('api.v1.me.password'), ['password' => '7351', 'password_confirmation' => '7351'])->assertNoContent();
    pinLogin('7351')->assertCreated();

    $token = $smsLogin();
    $this->travel(16)->minutes();
    $this->withToken($token)->putJson(route('api.v1.me.password'), ['password' => '1593', 'password_confirmation' => '1593'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['current_password']);
});

it('ferme l’API à un compte sans code PIN (créé sur le site) tant qu’il ne l’a pas défini', function () {
    $user = User::factory()->withRole(Role::Candidat)->create(['password' => null]);
    $token = app(IssueDeviceToken::class)->handle($user, new DeviceInfo('Itel A70', DevicePlatform::Android), 'otp')->plainTextToken;

    $this->withToken($token)->getJson(route('api.v1.consents.index'))
        ->assertForbidden()
        ->assertJsonPath('code', 'pin_required');

    $this->withToken($token)->getJson(route('api.v1.me.show'))->assertOk();
    $this->withToken($token)->putJson(route('api.v1.me.password'), ['password' => '4826', 'password_confirmation' => '4826'])->assertNoContent();
    $this->withToken($token)->getJson(route('api.v1.consents.index'))->assertOk();
});

it('refuse la connexion par code SMS d’un numéro sans compte, sans le créer', function () {
    $this->postJson(route('api.v1.auth.otp.request'), ['phone' => '0707070707'])->assertAccepted();

    $this->postJson(route('api.v1.auth.otp.verify'), ['phone' => '0707070707', 'code' => '1234', ...deviceFields()])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code' => 'Aucun compte n’est associé à ce numéro. Inscrivez-vous d’abord.']);

    expect(User::query()->count())->toBe(0);
});

it('liste les inscriptions en attente et permet de les valider dans la console [MOB-01]', function () {
    signUp()->assertCreated();
    $admin = User::factory()->withTwoFactor()->withRole(Role::Admin)->create();

    $this->actingAs($admin)->get(route('console.registrations'))->assertOk()->assertSee('Awa Mariam Traoré');

    $user = User::findByPhone(PhoneNumber::parse('0707070707'));

    Livewire::actingAs($admin)->test(RegistrationsPage::class)
        ->call('ask', $user->uuid, 'approve')
        ->call('confirm');

    expect($user->refresh()->status)->toBe(UserStatus::Active);
    $this->assertDatabaseHas('audit_logs', ['action' => 'users.registration.approved']);
});

it('refuse une inscription : données effacées et numéro de nouveau libre', function () {
    signUp()->assertCreated();
    $admin = User::factory()->withTwoFactor()->withRole(Role::Admin)->create();
    $user = User::findByPhone(PhoneNumber::parse('0707070707'));

    Livewire::actingAs($admin)->test(RegistrationsPage::class)
        ->call('ask', $user->uuid, 'reject')
        ->call('confirm');

    expect($user->refresh()->status)->toBe(UserStatus::Deleted)
        ->and(User::findByPhone(PhoneNumber::parse('0707070707')))->toBeNull();

    $this->postJson(route('api.v1.auth.register.request'), ['phone' => '0707070707'])->assertAccepted();
});

it('réserve la page des inscriptions à l’administration', function () {
    $coordinator = User::factory()->withTwoFactor()->withRole(Role::Coordonnateur)->create();

    $this->actingAs($coordinator)->get(route('console.registrations'))->assertForbidden();
});

it('connecte avec seulement le numéro et le code PIN, sans décrire l’appareil', function () {
    signUp()->assertCreated();
    approveRegistration();

    $this->withHeader('User-Agent', 'JeRelais/1.0 (iPhone; iOS 18.1)')
        ->postJson(route('api.v1.auth.login'), ['phone' => '0707070707', 'password' => '4826'])
        ->assertCreated()
        ->assertJsonPath('data.device.name', 'Appareil mobile')
        ->assertJsonPath('data.device.platform', 'ios');
});
