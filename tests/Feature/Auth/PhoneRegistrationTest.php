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
use App\Domain\Identity\Enums\IdentityDocumentType;
use App\Domain\Identity\Models\IdentityVerification;
use App\Domain\Media\Contracts\VirusScanner;
use App\Livewire\Console\Registrations\Index as RegistrationsPage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;

beforeEach(function () {
    config(['jerelais.otp.fixed_code' => '1234']);
    Storage::fake('identity');
    $this->sms = fakeSms();
});

/**
 * @param  array<string, mixed>  $overrides
 */
function signUp(string $phone = '0707070707', array $overrides = []): TestResponse
{
    test()->postJson(route('api.v1.auth.register.request'), ['phone' => $phone])->assertAccepted();

    test()->postJson(route('api.v1.auth.register.verify'), ['phone' => $phone, 'code' => '1234'])->assertOk();

    return completeRegistration($phone, $overrides);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function completeRegistration(string $phone = '0707070707', array $overrides = []): TestResponse
{
    return test()->post(route('api.v1.auth.register.complete'), array_filter([
        'phone' => $phone,
        'last_name' => 'Traoré',
        'first_names' => 'Awa Mariam',
        'gender' => 'femme',
        'birth_date' => '2001-04-17',
        'document_type' => 'cni',
        'recto' => UploadedFile::fake()->image('cni-recto.jpg', 800, 500),
        'verso' => UploadedFile::fake()->create('cni-verso.pdf', 120, 'application/pdf'),
        'password' => 'MotDePasse2026',
        'password_confirmation' => 'MotDePasse2026',
        ...$overrides,
    ], fn (mixed $value): bool => $value !== null), ['Accept' => 'application/json']);
}

function passwordLogin(string $password = 'MotDePasse2026', string $phone = '0707070707', string $ip = '127.0.0.1'): TestResponse
{
    return test()->withServerVariables(['REMOTE_ADDR' => $ip])
        ->postJson(route('api.v1.auth.login'), ['phone' => $phone, 'password' => $password]);
}

function approveRegistration(string $phone = '0707070707'): User
{
    $user = User::findByPhone(PhoneNumber::parse($phone));
    app(ReviewRegistration::class)->approve($user, User::factory()->withRole(Role::Admin)->create());

    return $user->refresh();
}

it('inscrit en trois étapes : numéro, code 1234, identité, pièce et mot de passe — compte en attente, sans jeton [MOB-01]', function () {
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

    $verification = IdentityVerification::query()->where('user_id', $user->id)->sole();

    expect($verification->document_type)->toBe(IdentityDocumentType::Cni)
        ->and($verification->files()->pluck('kind')->map->value->sort()->values()->all())->toBe(['recto', 'verso'])
        ->and(Storage::disk('identity')->allFiles())->toHaveCount(2);
});

it('refuse la connexion tant que l’inscription n’est pas validée, puis l’accepte [MOB-01]', function () {
    signUp()->assertCreated();

    passwordLogin()
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['phone' => 'Votre inscription est en attente de validation par l’équipe JE RELAIS. Vous pourrez vous connecter dès qu’elle sera validée.']);

    approveRegistration();

    passwordLogin()->assertCreated()->assertJsonPath('data.password_required', false);
});

it('refuse aussi la connexion par code SMS d’une inscription en attente', function () {
    signUp()->assertCreated();

    $this->postJson(route('api.v1.auth.otp.request'), ['phone' => '0707070707'])->assertAccepted();
    $this->postJson(route('api.v1.auth.otp.verify'), ['phone' => '0707070707', 'code' => '1234', ...deviceFields()])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);
});

it('exige toutes les informations d’identité et un mot de passe d’au moins 8 caractères confirmé', function (array $overrides, string $field) {
    signUp(overrides: $overrides)->assertUnprocessable()->assertJsonValidationErrors([$field]);

    expect(User::query()->count())->toBe(0);
})->with([
    'nom manquant' => [['last_name' => ''], 'last_name'],
    'prénoms manquants' => [['first_names' => ''], 'first_names'],
    'sexe inconnu' => [['gender' => 'x'], 'gender'],
    'date de naissance mal formée' => [['birth_date' => '17/04/2001'], 'birth_date'],
    'mot de passe de 7 caractères' => [['password' => 'Secret7', 'password_confirmation' => 'Secret7'], 'password'],
    'mot de passe manquant' => [['password' => null], 'password'],
    'confirmation différente' => [['password_confirmation' => 'AutreChose2026'], 'password'],
    'type de pièce manquant' => [['document_type' => null], 'document_type'],
    'type de pièce inconnu' => [['document_type' => 'carte_vitale'], 'document_type'],
    'verso de la CNI manquant' => [['verso' => null], 'verso'],
    'page du passeport manquante' => [['document_type' => 'passeport'], 'page'],
    'pièce dans un format refusé' => [['recto' => UploadedFile::fake()->create('cni.docx', 50, 'application/msword')], 'recto'],
]);

it('accepte un passeport en une seule page, en PDF', function () {
    signUp(overrides: ['document_type' => 'passeport', 'recto' => null, 'verso' => null, 'page' => UploadedFile::fake()->create('passeport.pdf', 300, 'application/pdf')])
        ->assertCreated();

    $verification = IdentityVerification::query()->sole();

    expect($verification->document_type)->toBe(IdentityDocumentType::Passport)
        ->and($verification->files()->sole()->kind->value)->toBe('page');
});

it('refuse la dernière étape pour un numéro non vérifié, et n’accepte une vérification qu’une fois', function () {
    completeRegistration('0505050505')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['phone' => 'Ce numéro n’a pas été vérifié ou sa vérification a expiré. Recommencez l’inscription depuis le début.']);

    signUp()->assertCreated();
    completeRegistration()->assertUnprocessable()->assertJsonValidationErrors(['phone']);

    expect(User::query()->count())->toBe(1)
        ->and(Storage::disk('identity')->allFiles())->toHaveCount(2);
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
    config(['jerelais.otp.fixed_code' => null]);

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
    approveRegistration();

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
    approveRegistration();

    $smsLogin = function (): string {
        $this->postJson(route('api.v1.auth.otp.request'), ['phone' => '0707070707'])->assertAccepted();

        return $this->postJson(route('api.v1.auth.otp.verify'), ['phone' => '0707070707', 'code' => '1234', ...deviceFields()])
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

it('connecte avec seulement le numéro et le mot de passe, sans décrire l’appareil', function () {
    signUp()->assertCreated();
    approveRegistration();

    $this->withHeader('User-Agent', 'JeRelais/1.0 (iPhone; iOS 18.1)')
        ->postJson(route('api.v1.auth.login'), ['phone' => '0707070707', 'password' => 'MotDePasse2026'])
        ->assertCreated()
        ->assertJsonPath('data.device.name', 'Appareil mobile')
        ->assertJsonPath('data.device.platform', 'ios');
});

it('n’inscrit personne et ne garde aucun fichier si la pièce est refusée par l’antivirus [§10]', function () {
    app()->instance(VirusScanner::class, new class implements VirusScanner
    {
        public function scan(string $path): ?string
        {
            return 'Win.Test.EICAR_HDB-1';
        }
    });

    signUp()->assertUnprocessable()->assertJsonValidationErrors(['recto' => 'Ce fichier a été refusé par l’antivirus.']);

    expect(User::query()->count())->toBe(0)
        ->and(Storage::disk('identity')->allFiles())->toBe([]);
});

it('montre la pièce d’identité envoyée dans la console des inscriptions', function () {
    signUp()->assertCreated();
    $admin = User::factory()->withTwoFactor()->withRole(Role::Admin)->create();

    $this->actingAs($admin)->get(route('console.registrations'))
        ->assertOk()
        ->assertSee('Carte nationale d’identité (CNI)')
        ->assertSee('Recto de la pièce')
        ->assertSee('Verso de la pièce (PDF)');
});

it('liste publiquement les pièces d’identité acceptées pour l’écran d’inscription', function () {
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
