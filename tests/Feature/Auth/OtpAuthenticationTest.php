<?php

use App\Domain\Access\Enums\Role;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Auth\Data\PhoneNumber;
use App\Domain\Auth\Models\Device;
use App\Domain\Auth\Models\OtpCode;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;

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

it('envoie un code SMS de 6 chiffres et ne stocke que son empreinte [MOB-01]', function () {
    requestOtp()->assertAccepted()->assertJsonPath('meta.expires_in_seconds', 300);

    $code = $this->sms->lastCodeFor('+2250707070707');
    $otp = OtpCode::query()->sole();

    expect($code)->toMatch('/^\d{6}$/')
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
    foreach (range(1, 10) as $index) {
        requestOtp('07070707'.str_pad((string) $index, 2, '0', STR_PAD_LEFT))->assertAccepted();
    }

    requestOtp('0708080808')->assertTooManyRequests();
});

it('crée le compte candidat et un jeton d’appareil au premier code valide [MOB-01]', function () {
    requestOtp();

    $response = verifyOtp($this->sms->lastCodeFor('+2250707070707'))
        ->assertCreated()
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.is_new_user', true)
        ->assertJsonPath('data.device.platform', 'android')
        ->assertJsonPath('data.user.roles', [Role::Candidat->value])
        ->assertJsonMissingPath('data.user.phone')
        ->assertJsonMissingPath('data.user.phone_hash');

    $user = User::findByPhone($this->phone);

    expect($user)->not->toBeNull()
        ->and($user->phone)->toBe('+2250707070707')
        ->and($user->getRawOriginal('phone'))->not->toContain('0707070707')
        ->and(Device::query()->where('user_id', $user->id)->count())->toBe(1);

    $this->withToken($response->json('data.token'))->getJson(route('api.v1.me.show'))
        ->assertOk()
        ->assertJsonPath('data.uuid', $user->uuid);
});

it('reconnecte un compte existant sans le dupliquer', function () {
    $user = User::factory()->withRole(Role::Relais)->create();
    $user->setPhoneNumber($this->phone)->save();

    requestOtp();
    verifyOtp($this->sms->lastCodeFor('+2250707070707'))->assertCreated()->assertJsonPath('data.is_new_user', false);

    expect(User::query()->count())->toBe(1);
});

it('refuse un mauvais code puis bloque après 5 essais, même avec le bon code [MOB-01]', function () {
    requestOtp();
    $code = $this->sms->lastCodeFor('+2250707070707');
    $wrong = $code === '000000' ? '111111' : '000000';

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
    requestOtp();
    $code = $this->sms->lastCodeFor('+2250707070707');

    verifyOtp($code)->assertCreated();
    verifyOtp($code)->assertUnprocessable();
});

it('annule l’ancien code quand un nouveau est demandé', function () {
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
    requestOtp();
    $code = $this->sms->lastCodeFor('+2250707070707');
    verifyOtp($code)->assertCreated();

    $actions = AuditLog::query()->orderBy('id')->pluck('action')->all();
    $serialized = AuditLog::query()->get()->toJson();

    expect($actions)->toContain('auth.otp.requested', 'auth.account.created', 'auth.login')
        ->and($serialized)->not->toContain($code)
        ->and($serialized)->not->toContain('0707070707');
});
