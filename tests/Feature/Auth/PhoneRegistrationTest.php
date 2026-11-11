<?php

use App\Domain\Access\Enums\Role;
use App\Domain\Auth\Actions\IssueDeviceToken;
use App\Domain\Auth\Data\DeviceInfo;
use App\Domain\Auth\Data\PhoneNumber;
use App\Domain\Auth\Enums\DevicePlatform;
use App\Domain\Auth\Enums\UserStatus;
use App\Domain\Auth\Support\OtpPolicy;
use App\Domain\Identity\Models\IdentityVerification;
use App\Domain\Relais\Models\RelaisProfile;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    config(['jerelais.otp.fixed_code' => '1234']);
    $this->sms = fakeSms();
});

function signUp(string $phone = '0707070707', string $password = 'MotDePasse2026', ?string $confirmation = null): TestResponse
{
    test()->postJson(route('api.v1.auth.register.request'), ['phone' => $phone])->assertAccepted();
    test()->postJson(route('api.v1.auth.register.verify'), ['phone' => $phone, 'code' => '1234'])->assertOk();

    return completeRegistration($phone, $password, $confirmation);
}

function completeRegistration(string $phone = '0707070707', ?string $password = 'MotDePasse2026', ?string $confirmation = null): TestResponse
{
    return test()->postJson(route('api.v1.auth.register.complete'), array_filter([
        'phone' => $phone,
        'password' => $password,
        'password_confirmation' => $confirmation ?? $password,
    ], fn (?string $value): bool => $value !== null));
}

function passwordLogin(string $password = 'MotDePasse2026', string $phone = '0707070707', string $ip = '127.0.0.1'): TestResponse
{
    return test()->withServerVariables(['REMOTE_ADDR' => $ip])
        ->postJson(route('api.v1.auth.login'), ['phone' => $phone, 'password' => $password]);
}

it('inscrit en trois étapes : numéro, code, mot de passe — compte actif, Relais, sans jeton [MOB-01]', function () {
    signUp()
        ->assertCreated()
        ->assertJsonPath('message', 'Inscription terminée. Connectez-vous avec votre numéro et votre mot de passe.')
        ->assertJsonPath('data.status', 'active')
        ->assertJsonMissingPath('data.token');

    $user = User::findByPhone(PhoneNumber::parse('0707070707'));
    $profile = RelaisProfile::withoutCommuneRestriction()->where('user_id', $user->id)->sole();

    expect($this->sms->sent)->toBe([])
        ->and($user->status)->toBe(UserStatus::Active)
        ->and($user->hasRole(Role::Relais->value))->toBeTrue()
        ->and($user->tokens()->count())->toBe(0)
        ->and($profile->number)->toStartWith('JR-')
        ->and($profile->commune_id)->toBeNull()
        ->and(IdentityVerification::query()->count())->toBe(0);
});

it('connecte juste après l’inscription et permet d’envoyer des relais sans profil complété [MOB-01]', function () {
    signUp()->assertCreated();

    $token = passwordLogin()
        ->assertCreated()
        ->assertJsonPath('data.password_required', false)
        ->assertJsonPath('data.user.roles', [Role::Relais->value])
        ->assertJsonPath('data.user.gains_eligible', false)
        ->json('data.token');

    $this->withToken($token)->postJson(route('api.v1.relais.store'), ['client_uuid' => (string) Str::uuid()])->assertCreated();
    $this->withToken($token)->getJson(route('api.v1.identity.show'))->assertOk()->assertJsonPath('data.status', 'draft');
});

it('exige un mot de passe d’au moins 8 caractères, confirmé', function (?string $password, ?string $confirmation, string $message) {
    test()->postJson(route('api.v1.auth.register.request'), ['phone' => '0707070707'])->assertAccepted();
    test()->postJson(route('api.v1.auth.register.verify'), ['phone' => '0707070707', 'code' => '1234'])->assertOk();

    completeRegistration(password: $password, confirmation: $confirmation)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password' => $message]);

    expect(User::query()->count())->toBe(0);
})->with([
    '7 caractères' => ['Secret7', null, 'Le mot de passe doit contenir au moins 8 caractères.'],
    'confirmation différente' => ['MotDePasse2026', 'AutreChose2026', 'Les deux mots de passe ne correspondent pas.'],
    'mot de passe manquant' => [null, null, 'Le champ mot de passe est obligatoire.'],
]);

it('refuse la dernière étape pour un numéro non vérifié, et n’accepte une vérification qu’une fois', function () {
    completeRegistration('0505050505')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['phone' => 'Ce numéro n’a pas été vérifié ou sa vérification a expiré. Recommencez l’inscription depuis le début.']);

    signUp()->assertCreated();
    completeRegistration()->assertUnprocessable()->assertJsonValidationErrors(['phone']);

    expect(User::query()->count())->toBe(1);
});

it('oublie la vérification du numéro après 30 minutes', function () {
    test()->postJson(route('api.v1.auth.register.request'), ['phone' => '0707070707'])->assertAccepted();
    test()->postJson(route('api.v1.auth.register.verify'), ['phone' => '0707070707', 'code' => '1234'])
        ->assertOk()
        ->assertJsonPath('meta.expires_in_seconds', 1800);

    $this->travel(31)->minutes();

    completeRegistration()->assertUnprocessable()->assertJsonValidationErrors(['phone']);
});

it('refuse l’inscription d’un numéro qui a déjà un compte, sans envoyer de code', function () {
    $user = User::factory()->withRole(Role::Relais)->create();
    $user->setPhoneNumber(PhoneNumber::parse('0707070707'))->save();
    config(['jerelais.otp.fixed_code' => 'off']);

    $this->postJson(route('api.v1.auth.register.request'), ['phone' => '07 07 07 07 07'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['phone' => 'Ce numéro a déjà un compte. Connectez-vous avec votre mot de passe.']);

    $this->postJson(route('api.v1.auth.register.verify'), ['phone' => '0707070707', 'code' => '1234'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['phone']);

    expect($this->sms->sent)->toBe([])->and(User::query()->count())->toBe(1);
});

it('bloque le numéro après 5 mots de passe erronés, même depuis des adresses IP différentes [§10 Sécurité]', function () {
    signUp()->assertCreated();

    foreach (range(1, 5) as $attempt) {
        passwordLogin('mauvais-mot-de-passe', ip: "10.0.0.{$attempt}")->assertUnprocessable();
    }

    passwordLogin(ip: '10.0.0.99')
        ->assertTooManyRequests()
        ->assertJsonPath('message', 'Trop de mots de passe erronés. Réessayez dans 15 minutes ou redéfinissez votre mot de passe par SMS.');

    $this->travel(16)->minutes();

    passwordLogin(ip: '10.0.0.100')->assertCreated();
})->after(fn () => RateLimiter::clear('password-failures:'.PhoneNumber::parse('0707070707')->hash()));

it('redéfinit un mot de passe oublié après une connexion par code SMS, sans l’ancien, pendant 15 minutes seulement [MOB-01]', function () {
    signUp()->assertCreated();

    $smsLogin = function (): string {
        $this->postJson(route('api.v1.auth.otp.request'), ['phone' => '0707070707'])->assertAccepted();

        return $this->postJson(route('api.v1.auth.otp.verify'), ['phone' => '0707070707', 'code' => '1234'])
            ->assertCreated()
            ->json('data.token');
    };

    $token = $smsLogin();
    $this->withToken($token)->putJson(route('api.v1.me.password'), ['password' => 'NouveauSecret!8', 'password_confirmation' => 'NouveauSecret!8'])->assertNoContent();
    passwordLogin('NouveauSecret!8')->assertCreated();

    $token = $smsLogin();
    $this->travel(16)->minutes();
    $this->withToken($token)->putJson(route('api.v1.me.password'), ['password' => 'EncoreUnAutre9', 'password_confirmation' => 'EncoreUnAutre9'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['current_password']);
});

it('ferme l’API à un compte sans mot de passe (créé sur le site) tant qu’il ne l’a pas défini', function () {
    $user = User::factory()->withRole(Role::Candidat)->create(['password' => null]);
    $token = app(IssueDeviceToken::class)->handle($user, new DeviceInfo('Itel A70', DevicePlatform::Android), 'otp')->plainTextToken;

    $this->withToken($token)->getJson(route('api.v1.consents.index'))
        ->assertForbidden()
        ->assertJsonPath('code', 'password_required');

    $this->withToken($token)->getJson(route('api.v1.me.show'))->assertOk();
    $this->withToken($token)->putJson(route('api.v1.me.password'), ['password' => 'MotDePasse2026', 'password_confirmation' => 'MotDePasse2026'])->assertNoContent();
    $this->withToken($token)->getJson(route('api.v1.consents.index'))->assertOk();
});

it('refuse la connexion par code SMS d’un numéro sans compte, sans le créer', function () {
    $this->postJson(route('api.v1.auth.otp.request'), ['phone' => '0707070707'])->assertAccepted();

    $this->postJson(route('api.v1.auth.otp.verify'), ['phone' => '0707070707', 'code' => '1234'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code' => 'Aucun compte n’est associé à ce numéro. Inscrivez-vous d’abord.']);

    expect(User::query()->count())->toBe(0);
});

it('connecte avec seulement le numéro et le mot de passe, sans décrire l’appareil', function () {
    signUp()->assertCreated();

    $this->withHeader('User-Agent', 'JeRelais/1.0 (iPhone; iOS 18.1)')
        ->postJson(route('api.v1.auth.login'), ['phone' => '0707070707', 'password' => 'MotDePasse2026'])
        ->assertCreated()
        ->assertJsonPath('data.device.name', 'Appareil mobile')
        ->assertJsonPath('data.device.platform', 'ios');
});

it('liste publiquement les pièces d’identité acceptées', function () {
    $this->getJson(route('api.v1.auth.register.options'))
        ->assertOk()
        ->assertHeader('ETag')
        ->assertJsonCount(5, 'data')
        ->assertJsonPath('data.0', ['value' => 'cni', 'label' => 'Carte nationale d’identité (CNI)', 'sides' => ['recto', 'verso']])
        ->assertJsonPath('data.1', ['value' => 'passeport', 'label' => 'Passeport', 'sides' => ['page']])
        ->assertJsonPath('data.2.sides', ['recto', 'verso'])
        ->assertJsonPath('data.3.sides', ['recto', 'verso'])
        ->assertJsonPath('data.4', ['value' => 'attestation_identite', 'label' => 'Attestation d’identité', 'sides' => ['page']])
        ->assertJsonPath('meta.max_file_kb', 10240)
        ->assertJsonPath('meta.accepted_mime_types', ['image/jpeg', 'image/png', 'image/heic', 'image/heif', 'application/pdf']);
});

it('envoie le code 1234 par défaut à l’inscription tant qu’aucun fournisseur de SMS n’est branché', function (string $driver) {
    config(['jerelais.otp.fixed_code' => '', 'jerelais.sms.driver' => $driver]);

    $this->postJson(route('api.v1.auth.register.request'), ['phone' => '0707070707'])->assertAccepted();
    $this->postJson(route('api.v1.auth.register.verify'), ['phone' => '0707070707', 'code' => '1234'])->assertOk();

    expect($this->sms->sent)->toBe([]);
})->with(['none', 'log', 'mailpit']);

it('repasse à un code aléatoire envoyé par SMS dès qu’un vrai fournisseur est branché', function () {
    config(['jerelais.otp.fixed_code' => '', 'jerelais.sms.driver' => 'fournisseur-reel']);

    $this->postJson(route('api.v1.auth.register.request'), ['phone' => '0707070707'])->assertAccepted();

    expect($this->sms->sent)->toHaveCount(1)
        ->and(OtpPolicy::isFixedCodeEnabled())->toBeFalse();
});

it('active les comptes restés en attente lors de la migration', function () {
    $user = User::factory()->create(['status' => 'active']);
    DB::table('users')->where('id', $user->id)->update(['status' => 'pending']);

    (require database_path('migrations/2026_11_11_150346_activate_pending_app_registrations.php'))->up();

    expect($user->refresh()->status)->toBe(UserStatus::Active)
        ->and($user->hasRole(Role::Relais->value))->toBeTrue()
        ->and(RelaisProfile::withoutCommuneRestriction()->where('user_id', $user->id)->exists())->toBeTrue();
});
