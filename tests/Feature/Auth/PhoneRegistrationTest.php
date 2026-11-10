<?php

use App\Domain\Access\Enums\Role;
use App\Domain\Auth\Data\PhoneNumber;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    config(['jerelais.otp.fixed_code' => '1234']);
    $this->sms = fakeSms();
});

function signUp(string $phone = '0707070707'): TestResponse
{
    test()->postJson(route('api.v1.auth.register.request'), ['phone' => $phone])->assertAccepted();

    return test()->postJson(route('api.v1.auth.register.verify'), ['phone' => $phone, 'code' => '1234', ...deviceFields()]);
}

function choosePin(string $token, string $pin = '4826', ?string $confirmation = null): TestResponse
{
    return test()->withToken($token)->putJson(route('api.v1.me.password'), ['password' => $pin, 'password_confirmation' => $confirmation ?? $pin]);
}

function pinLogin(string $pin, string $phone = '0707070707', string $ip = '127.0.0.1'): TestResponse
{
    return test()->withServerVariables(['REMOTE_ADDR' => $ip])
        ->postJson(route('api.v1.auth.login'), ['phone' => $phone, 'password' => $pin, ...deviceFields()]);
}

it('inscrit un numéro avec le code 1234 sans envoyer de SMS, puis exige le code PIN [MOB-01]', function () {
    signUp()
        ->assertCreated()
        ->assertJsonPath('data.is_new_user', true)
        ->assertJsonPath('data.pin_required', true)
        ->assertJsonPath('data.user.roles', [Role::Candidat->value]);

    expect($this->sms->sent)->toBe([])
        ->and(User::findByPhone(PhoneNumber::parse('0707070707'))?->password)->toBeNull();
});

it('refuse l’inscription d’un numéro qui a déjà un compte, sans envoyer de code', function () {
    $user = User::factory()->withRole(Role::Relais)->create();
    $user->setPhoneNumber(PhoneNumber::parse('0707070707'))->save();
    config(['jerelais.otp.fixed_code' => null]);

    $this->postJson(route('api.v1.auth.register.request'), ['phone' => '07 07 07 07 07'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['phone' => 'Ce numéro a déjà un compte. Connectez-vous avec votre code PIN.']);

    $this->postJson(route('api.v1.auth.register.verify'), ['phone' => '0707070707', 'code' => '1234', ...deviceFields()])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['phone']);

    expect($this->sms->sent)->toBe([])->and(User::query()->count())->toBe(1);
});

it('ferme l’API tant que le code PIN n’est pas défini, sauf les routes utiles pour le définir [MOB-01]', function () {
    $token = signUp()->json('data.token');

    $this->withToken($token)->getJson(route('api.v1.consents.index'))
        ->assertForbidden()
        ->assertJsonPath('code', 'pin_required')
        ->assertJsonPath('message', 'Choisissez votre code PIN pour continuer.');

    $this->withToken($token)->getJson(route('api.v1.me.show'))->assertOk()->assertJsonPath('data.has_password', false);
    $this->withToken($token)->putJson(route('api.v1.devices.current'), ['fcm_token' => 'fcm-test'])->assertOk();

    choosePin($token)->assertNoContent();

    $this->withToken($token)->getJson(route('api.v1.consents.index'))->assertOk();
});

it('exige un code PIN d’exactement 4 chiffres, confirmé', function (string $pin, ?string $confirmation, string $message) {
    $token = signUp()->json('data.token');

    choosePin($token, $pin, $confirmation)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password' => $message]);
})->with([
    'trop court' => ['123', null, 'Le code PIN comporte 4 chiffres.'],
    'trop long' => ['12345', null, 'Le code PIN comporte 4 chiffres.'],
    'lettres' => ['12a4', null, 'Le code PIN comporte 4 chiffres.'],
    'confirmation différente' => ['4826', '4827', 'Les deux codes PIN ne correspondent pas.'],
]);

it('connecte ensuite avec le numéro et le code PIN [MOB-01]', function () {
    choosePin(signUp()->json('data.token'))->assertNoContent();

    pinLogin('4826')
        ->assertCreated()
        ->assertJsonPath('data.is_new_user', false)
        ->assertJsonPath('data.pin_required', false);
});

it('bloque le numéro après 5 codes PIN erronés, même depuis des adresses IP différentes [§10 Sécurité]', function () {
    choosePin(signUp()->json('data.token'))->assertNoContent();

    foreach (range(1, 5) as $attempt) {
        pinLogin('0000', ip: "10.0.0.{$attempt}")->assertUnprocessable();
    }

    pinLogin('4826', ip: '10.0.0.99')
        ->assertTooManyRequests()
        ->assertJsonPath('message', 'Trop de codes PIN erronés. Réessayez dans 15 minutes ou redéfinissez votre code PIN par SMS.');

    $this->travel(16)->minutes();

    pinLogin('4826', ip: '10.0.0.100')->assertCreated();
})->after(fn () => RateLimiter::clear('pin-failures:'.PhoneNumber::parse('0707070707')->hash()));

it('redéfinit un code PIN oublié après une connexion par code SMS, sans l’ancien [MOB-01]', function () {
    choosePin(signUp()->json('data.token'))->assertNoContent();

    $this->postJson(route('api.v1.auth.otp.request'), ['phone' => '0707070707'])->assertAccepted();
    $token = $this->postJson(route('api.v1.auth.otp.verify'), ['phone' => '0707070707', 'code' => '1234', ...deviceFields()])
        ->assertCreated()
        ->json('data.token');

    choosePin($token, '7351')->assertNoContent();
    pinLogin('7351')->assertCreated();
});

it('n’autorise la redéfinition sans l’ancien PIN que dans les 15 minutes qui suivent la connexion par SMS', function () {
    choosePin(signUp()->json('data.token'))->assertNoContent();

    $this->postJson(route('api.v1.auth.otp.request'), ['phone' => '0707070707'])->assertAccepted();
    $token = $this->postJson(route('api.v1.auth.otp.verify'), ['phone' => '0707070707', 'code' => '1234', ...deviceFields()])->json('data.token');

    $this->travel(16)->minutes();

    choosePin($token, '7351')->assertUnprocessable()->assertJsonValidationErrors(['current_password']);
});

it('refuse la connexion par code SMS d’un numéro sans compte, sans le créer', function () {
    $this->postJson(route('api.v1.auth.otp.request'), ['phone' => '0707070707'])->assertAccepted();

    $this->postJson(route('api.v1.auth.otp.verify'), ['phone' => '0707070707', 'code' => '1234', ...deviceFields()])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code' => 'Aucun compte n’est associé à ce numéro. Inscrivez-vous d’abord.']);

    expect(User::query()->count())->toBe(0);
});
