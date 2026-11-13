<?php

use App\Domain\Access\Enums\Role;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Auth\Data\PhoneNumber;
use App\Domain\Auth\Models\Device;
use App\Domain\Auth\Models\OtpCode;
use App\Livewire\Relais\Rejoindre;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Livewire;

beforeEach(function () {
    $this->sms = fakeSms();
    $this->phone = PhoneNumber::parse('0707070707');
});

function requestOtp(string $phone = '07 07 07 07 07'): TestResponse
{
    return test()->postJson(route('api.v1.auth.otp.request'), ['phone' => $phone]);
}

function verifyOtp(?string $code, string $phone = '07 07 07 07 07'): TestResponse
{
    return test()->postJson(route('api.v1.auth.otp.verify'), ['phone' => $phone, 'code' => $code, ...deviceFields()]);
}

function registerPhone(string $phone = '07 07 07 07 07'): TestResponse
{
    return test()->postJson(route('api.v1.auth.register.request'), ['phone' => $phone]);
}

function confirmRegistration(?string $code, string $phone = '07 07 07 07 07'): TestResponse
{
    return test()->postJson(route('api.v1.auth.register.verify'), ['phone' => $phone, 'code' => $code]);
}

function existingAccount(string $phone = '0707070707'): User
{
    $user = User::factory()->withRole(Role::Relais)->create();
    $user->setPhoneNumber(PhoneNumber::parse($phone))->save();

    return $user;
}

it('envoie un code SMS de 4 chiffres et ne stocke que son empreinte [MOB-01]', function () {
    requestOtp()->assertAccepted()->assertJsonPath('meta.expires_in_seconds', 300);

    $code = $this->sms->lastCodeFor('+2250707070707');
    $otp = OtpCode::query()->sole();

    expect($code)->toMatch('/^\d{4}$/')
        ->and($otp->code_hash)->not->toContain($code)
        ->and($otp->phone_hash)->toBe($this->phone->hash())
        ->and($otp->expires_at->diffInMinutes(now(), true))->toBeLessThanOrEqual(5.0);
});

it('répond de la même façon que le numéro soit inscrit ou non (pas d’énumération)', function () {
    $unknown = requestOtp('0101010101')->json();
    User::factory()->create()->setPhoneNumber(PhoneNumber::parse('0505050505'))->save();
    $known = requestOtp('0505050505')->json();

    expect($known)->toBe($unknown);
});

it('refuse un numéro invalide en français', function () {
    requestOtp('123')->assertUnprocessable()->assertJsonValidationErrors(['phone' => 'Le numéro de téléphone n’est pas valide.']);
});

it('limite à 3 envois par numéro sur 15 minutes [§10 Sécurité]', function () {
    requestOtp()->assertAccepted();
    requestOtp()->assertAccepted();
    requestOtp()->assertAccepted();

    requestOtp()->assertTooManyRequests();
    expect($this->sms->sent)->toHaveCount(3);
});

it('limite les envois par adresse IP, quel que soit le numéro [§10 Sécurité]', function () {
    config(['jerelais.otp.requests_per_ip' => 10]);

    foreach (range(1, 10) as $index) {
        requestOtp('07070707'.str_pad((string) $index, 2, '0', STR_PAD_LEFT))->assertAccepted();
    }

    requestOtp('0708080808')->assertTooManyRequests();
});

it('vérifie le code d’inscription sans encore créer de compte [MOB-01]', function () {
    registerPhone()->assertAccepted();

    confirmRegistration($this->sms->lastCodeFor('+2250707070707'))
        ->assertOk()
        ->assertJsonPath('meta.expires_in_seconds', 1800)
        ->assertJsonPath('message', 'Numéro vérifié. Complétez votre inscription.')
        ->assertJsonMissingPath('data');

    expect(User::query()->count())->toBe(0)
        ->and(Device::query()->count())->toBe(0);
});

it('reconnecte un compte existant par code SMS sans le dupliquer (mot de passe oublié)', function () {
    existingAccount();

    requestOtp();
    verifyOtp($this->sms->lastCodeFor('+2250707070707'))->assertCreated()->assertJsonPath('data.is_new_user', false);

    expect(User::query()->count())->toBe(1);
});

it('refuse un mauvais code puis bloque après 5 essais, même avec le bon code [MOB-01]', function () {
    requestOtp();
    $code = $this->sms->lastCodeFor('+2250707070707');
    $wrong = $code === '0000' ? '1111' : '0000';

    foreach (range(1, 5) as $attempt) {
        verifyOtp($wrong)->assertUnprocessable()->assertJsonValidationErrors(['code' => 'Code invalide ou expiré.']);
    }

    verifyOtp($code)->assertUnprocessable();
    expect(User::query()->count())->toBe(0);
})->after(fn () => RateLimiter::clear('otp-verify:127.0.0.1'));

it('refuse un code expiré', function () {
    requestOtp();
    $code = $this->sms->lastCodeFor('+2250707070707');

    $this->travel(6)->minutes();

    verifyOtp($code)->assertUnprocessable();
});

it('n’accepte un code qu’une seule fois', function () {
    existingAccount();
    requestOtp();
    $code = $this->sms->lastCodeFor('+2250707070707');

    verifyOtp($code)->assertCreated();
    verifyOtp($code)->assertUnprocessable();
});

it('annule l’ancien code quand un nouveau est demandé', function () {
    existingAccount();
    requestOtp();
    $first = $this->sms->lastCodeFor('+2250707070707');
    requestOtp();
    $second = $this->sms->lastCodeFor('+2250707070707');

    if ($first !== $second) {
        verifyOtp($first)->assertUnprocessable();
    }
    verifyOtp($second)->assertCreated();
});

it('refuse la connexion d’un compte suspendu', function () {
    $user = User::factory()->suspended()->create();
    $user->setPhoneNumber($this->phone)->save();

    requestOtp();
    verifyOtp($this->sms->lastCodeFor('+2250707070707'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code' => 'Ce compte est suspendu. Contactez votre coordonnateur.']);
});

it('trace les demandes et connexions dans l’audit sans jamais y écrire le code [ADM-25]', function () {
    existingAccount();
    requestOtp();
    $code = $this->sms->lastCodeFor('+2250707070707');
    verifyOtp($code)->assertCreated();

    $actions = AuditLog::query()->orderBy('id')->pluck('action')->all();
    $serialized = AuditLog::query()->get()->toJson();

    expect($actions)->toContain('auth.otp.requested', 'auth.login')
        ->and($serialized)->not->toContain($code)
        ->and($serialized)->not->toContain('0707070707');
});

it('partage le quota d’envoi par numéro entre le site et l’application (revue sécurité)', function () {
    foreach (range(1, 3) as $attempt) {
        Livewire::test(Rejoindre::class)->set('phone', '0707070707')->call('sendCode')->assertHasNoErrors();
    }

    $this->postJson(route('api.v1.auth.otp.request'), ['phone' => '0707070707'])->assertTooManyRequests();
});

it('accepte le code fixe transitoire sans envoyer de SMS (en attendant un fournisseur)', function () {
    config(['jerelais.otp.fixed_code' => '1234']);

    registerPhone()->assertAccepted();
    expect($this->sms->sent)->toBe([]);

    confirmRegistration('1234')
        ->assertOk()
        ->assertJsonPath('meta.expires_in_seconds', 1800);

    $this->assertDatabaseHas('audit_logs', ['action' => 'auth.otp.requested']);
    expect(AuditLog::query()->where('action', 'auth.otp.requested')->latest('id')->first()->payload['fixed_code'])->toBeTrue();
});

it('refuse un autre code que le code fixe', function () {
    config(['jerelais.otp.fixed_code' => '1234']);

    requestOtp()->assertAccepted();

    $this->postJson(route('api.v1.auth.otp.verify'), ['phone' => '0707070707', 'code' => '4321', ...deviceFields()])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);
});

it('réserve le code fixe aux numéros listés : les autres reçoivent un vrai code par SMS', function () {
    config(['jerelais.otp.fixed_code' => '1234', 'jerelais.otp.fixed_code_numbers' => '0700000001, 0700000002']);

    $this->postJson(route('api.v1.auth.otp.request'), ['phone' => '0700000001'])->assertAccepted();
    expect($this->sms->sent)->toBe([]);

    requestOtp()->assertAccepted();
    expect($this->sms->sent)->toHaveCount(1);

    $this->postJson(route('api.v1.auth.otp.verify'), ['phone' => '0707070707', 'code' => '1234', ...deviceFields()])->assertUnprocessable();
});

it('vérifie le numéro sur le site avec le code fixe à 4 chiffres', function () {
    config(['jerelais.otp.fixed_code' => '1234']);

    Livewire::test(Rejoindre::class)
        ->set('phone', '0707070707')
        ->call('sendCode')
        ->assertSet('codeLength', 4)
        ->set('code', '1234')
        ->call('verify')
        ->assertHasNoErrors()
        ->assertSet('step', 'password');
});

it('accepte par défaut 60 demandes par adresse IP (abonnés mobiles derrière une même adresse)', function () {
    expect(config('jerelais.otp.requests_per_ip'))->toBe(60)
        ->and(config('jerelais.otp.verify_per_ip_per_minute'))->toBe(60);

    foreach (range(1, 15) as $index) {
        requestOtp('07070707'.str_pad((string) $index, 2, '0', STR_PAD_LEFT))->assertAccepted();
    }
});

it('refuse d’envoyer un code à un numéro étranger (fraude aux SMS internationaux) [§10 Sécurité]', function () {
    requestOtp('+33 6 12 34 56 78')->assertUnprocessable()->assertJsonValidationErrors(['phone' => 'Seuls les numéros de Côte d’Ivoire']);
    registerPhone('+1 202 555 0143')->assertUnprocessable()->assertJsonValidationErrors('phone');

    expect($this->sms->sent)->toHaveCount(0)
        ->and(OtpCode::query()->count())->toBe(0);
});

it('autorise d’autres indicatifs quand l’administration les ajoute', function () {
    config(['jerelais.otp.allowed_prefixes' => ['+225', '+33']]);

    requestOtp('+33 6 12 34 56 78')->assertAccepted();
    expect($this->sms->sent)->toHaveCount(1);
});

it('suspend l’envoi des codes au-delà du plafond horaire de la plateforme, avec une seule alerte [§10 Sécurité]', function () {
    config(['jerelais.otp.global_per_hour' => 2]);

    requestOtp('0701010101')->assertAccepted();
    requestOtp('0702020202')->assertAccepted();
    requestOtp('0703030303')->assertServiceUnavailable()->assertJsonPath('message', 'Le service d’envoi des codes est très sollicité. Réessayez dans quelques minutes.');
    registerPhone('0704040404')->assertServiceUnavailable();

    expect($this->sms->sent)->toHaveCount(2)
        ->and(AuditLog::query()->where('action', 'auth.otp.global_cap_reached')->count())->toBe(1);
});

it('ne compte pas le code fixe dans le plafond horaire (aucun SMS envoyé)', function () {
    config(['jerelais.otp.global_per_hour' => 1, 'jerelais.otp.fixed_code' => '1234']);

    requestOtp('0701010101')->assertAccepted();
    requestOtp('0702020202')->assertAccepted();
    expect($this->sms->sent)->toHaveCount(0);
});

it('explique sur le site qu’un numéro étranger ne peut pas recevoir de code', function () {
    Livewire::test(Rejoindre::class)
        ->set('phone', '+33 6 12 34 56 78')
        ->call('sendCode')
        ->assertHasErrors('phone')
        ->assertSee('Seuls les numéros de Côte d’Ivoire');
});

it('redéfinit le mot de passe oublié en 3 étapes : code SMS, validation, nouveau mot de passe [MOB-01]', function () {
    config(['jerelais.otp.fixed_code' => '1234']);
    $user = existingAccount();
    $user->forceFill(['password' => 'AncienSecret2026'])->save();
    $autreAppareil = $user->createToken('autre appareil')->plainTextToken;

    requestOtp()->assertAccepted();
    $token = verifyOtp('1234')->assertCreated()->json('data.token');

    $this->withToken($token)->postJson(route('api.v1.auth.password.reset'), ['password' => 'NouveauSecret2026', 'password_confirmation' => 'NouveauSecret2026'])
        ->assertOk()
        ->assertJsonPath('message', 'Mot de passe enregistré. Utilisez-le désormais pour vous connecter.');

    expect(Hash::check('NouveauSecret2026', $user->fresh()->password))->toBeTrue()
        ->and(PersonalAccessToken::findToken($autreAppareil))->toBeNull();
    $this->assertDatabaseHas('audit_logs', ['action' => 'auth.password.reset']);

    $this->app['auth']->forgetGuards();
    $this->postJson(route('api.v1.auth.login'), ['phone' => '0707070707', 'password' => 'NouveauSecret2026'])->assertCreated();
});

it('refuse un nouveau mot de passe sans code SMS validé, ou mal confirmé', function () {
    config(['jerelais.otp.fixed_code' => '1234']);
    $user = existingAccount();
    $user->forceFill(['password' => 'AncienSecret2026'])->save();

    $login = $this->postJson(route('api.v1.auth.login'), ['phone' => '0707070707', 'password' => 'AncienSecret2026'])->json('data.token');
    $this->withToken($login)->postJson(route('api.v1.auth.password.reset'), ['password' => 'NouveauSecret2026', 'password_confirmation' => 'NouveauSecret2026'])
        ->assertForbidden()
        ->assertJsonPath('message', 'Validez d’abord le code reçu par SMS (POST /auth/verify), puis choisissez le nouveau mot de passe dans les 15 minutes.');

    $this->app['auth']->forgetGuards();
    $this->withoutToken();
    requestOtp()->assertAccepted();
    $token = verifyOtp('1234')->json('data.token');
    $this->app['auth']->forgetGuards();
    $this->withToken($token)->postJson(route('api.v1.auth.password.reset'), ['password' => 'NouveauSecret2026', 'password_confirmation' => 'Autre2026xx'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('password');

    $this->travel(16)->minutes();
    $this->withToken($token)->postJson(route('api.v1.auth.password.reset'), ['password' => 'NouveauSecret2026', 'password_confirmation' => 'NouveauSecret2026'])
        ->assertForbidden();
});
