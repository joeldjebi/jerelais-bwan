<?php

use App\Domain\Access\Enums\Role;
use App\Domain\Auth\Data\PhoneNumber;
use App\Domain\Auth\Enums\UserStatus;
use App\Domain\Identity\Enums\IdentityStatus;
use App\Domain\Identity\Models\IdentityVerification;
use App\Domain\Media\Contracts\VirusScanner;
use App\Domain\Privacy\Actions\PseudonymizeUser;
use App\Domain\Relais\Models\RelaisProfile;
use App\Domain\Territoire\Models\Commune;
use App\Domain\Territoire\Models\Localite;
use App\Livewire\Console\Identity\Show as IdentityShowPage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('identity');
    $this->commune = Commune::factory()->create();
    $this->relais = registeredAppRelais();
    Sanctum::actingAs($this->relais);
});

/**
 * Inscrit un numéro depuis l'application (numéro, code, mot de passe) : le compte est aussitôt actif et Relais.
 */
function registeredAppRelais(string $phone = '0707070707'): User
{
    config(['jerelais.otp.fixed_code' => '1234']);
    fakeSms();

    test()->postJson(route('api.v1.auth.register.request'), ['phone' => $phone])->assertAccepted();
    test()->postJson(route('api.v1.auth.register.verify'), ['phone' => $phone, 'code' => '1234'])->assertOk();
    test()->postJson(route('api.v1.auth.register.complete'), [
        'phone' => $phone, 'password' => 'MotDePasse2026', 'password_confirmation' => 'MotDePasse2026',
    ])->assertCreated();

    return User::findByPhone(PhoneNumber::parse($phone));
}

function sendIdentityFile(string $kind, ?UploadedFile $file = null): TestResponse
{
    return test()->post(route('api.v1.identity.files.store'), [
        'kind' => $kind,
        'file' => $file ?? UploadedFile::fake()->image("{$kind}.jpg", 800, 600),
    ], ['Accept' => 'application/json']);
}

function completeIdentity(string $type = 'cni'): void
{
    test()->putJson(route('api.v1.identity.update'), [
        'last_name' => 'Traoré', 'first_names' => 'Awa', 'gender' => 'femme', 'birth_date' => '2001-04-17',
        'commune' => test()->commune->uuid, 'document_type' => $type,
    ])->assertOk();

    $sides = $type === 'passeport' || $type === 'attestation_identite' ? ['page'] : ['recto', 'verso'];

    foreach ([...$sides, 'selfie', 'profile_photo'] as $kind) {
        sendIdentityFile($kind)->assertCreated();
    }
}

it('fait du compte inscrit un Relais sans commune, qui peut envoyer des relais sans profil complété [MOB-01]', function () {
    $profile = RelaisProfile::withoutCommuneRestriction()->where('user_id', $this->relais->id)->sole();

    expect($this->relais->status)->toBe(UserStatus::Active)
        ->and($this->relais->hasRole(Role::Relais->value))->toBeTrue()
        ->and($profile->commune_id)->toBeNull()
        ->and($profile->number)->toStartWith('JR-');

    $this->postJson(route('api.v1.relais.store'), ['client_uuid' => (string) Str::uuid()])->assertCreated();
    $this->getJson(route('api.v1.me.show'))->assertJsonPath('data.identity_status', null)->assertJsonPath('data.gains_eligible', false);
});

it('indique tout ce qui manque au profil d’un nouveau Relais', function () {
    $this->getJson(route('api.v1.identity.show'))
        ->assertOk()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.gains_eligible', false)
        ->assertJsonPath('data.last_name', null)
        ->assertJsonPath('data.document_type', null)
        ->assertJsonPath('data.missing', [
            'last_name' => 'Indiquez votre nom.',
            'first_names' => 'Indiquez vos prénoms.',
            'gender' => 'Indiquez votre sexe.',
            'birth_date' => 'Indiquez votre date de naissance.',
            'commune' => 'Choisissez votre commune.',
            'document_type' => 'Choisissez le type de votre pièce d’identité.',
            'files.selfie' => 'Ajoutez : Selfie avec la pièce.',
            'files.profile_photo' => 'Ajoutez : Photo de profil.',
        ])
        ->assertJsonCount(5, 'data.document_types');

    $this->postJson(route('api.v1.identity.submit'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['last_name', 'first_names', 'gender', 'birth_date', 'commune', 'document_type', 'files.selfie', 'files.profile_photo']);
});

it('complète le profil en plusieurs fois : seuls les champs envoyés changent', function () {
    $this->putJson(route('api.v1.identity.update'), ['last_name' => 'Traoré', 'first_names' => 'Awa Mariam'])
        ->assertOk()
        ->assertJsonPath('data.last_name', 'Traoré')
        ->assertJsonPath('data.first_names', 'Awa Mariam');

    $this->putJson(route('api.v1.identity.update'), ['gender' => 'femme', 'birth_date' => '2001-04-17'])
        ->assertOk()
        ->assertJsonPath('data.last_name', 'Traoré')
        ->assertJsonPath('data.gender', 'femme')
        ->assertJsonPath('data.birth_date', '2001-04-17');

    $this->putJson(route('api.v1.identity.update'), ['commune' => $this->commune->uuid])->assertOk()->assertJsonPath('data.commune.uuid', $this->commune->uuid);
    $this->putJson(route('api.v1.identity.update'), ['document_type' => 'passeport'])
        ->assertOk()
        ->assertJsonPath('data.commune.uuid', $this->commune->uuid)
        ->assertJsonPath('data.document_type', 'passeport');

    expect($this->relais->refresh()->name)->toBe('Awa Mariam Traoré')
        ->and(RelaisProfile::withoutCommuneRestriction()->where('user_id', $this->relais->id)->value('display_name'))->toBe('Awa T.');
});

it('refuse une date de naissance mal formée ou un sexe inconnu', function () {
    $this->putJson(route('api.v1.identity.update'), ['birth_date' => '31/02/2001', 'gender' => 'x'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['birth_date', 'gender']);
});

it('demande recto et verso pour une CNI, une seule page pour un passeport', function (string $type, array $sides) {
    $this->putJson(route('api.v1.identity.update'), ['commune' => $this->commune->uuid, 'document_type' => $type])
        ->assertOk()
        ->assertJsonPath('data.required_files', [...$sides, 'selfie', 'profile_photo']);
})->with([
    'CNI' => ['cni', ['recto', 'verso']],
    'permis' => ['permis', ['recto', 'verso']],
    'carte consulaire' => ['carte_consulaire', ['recto', 'verso']],
    'passeport' => ['passeport', ['page']],
    'attestation d’identité' => ['attestation_identite', ['page']],
]);

it('déduit la commune d’une localité choisie seule', function () {
    $localite = Localite::query()->create(['commune_id' => $this->commune->id, 'name' => 'Riviera 2', 'type' => 'quartier']);

    $this->putJson(route('api.v1.identity.update'), ['localite' => $localite->uuid, 'document_type' => 'cni'])
        ->assertOk()
        ->assertJsonPath('data.commune.uuid', $this->commune->uuid)
        ->assertJsonPath('data.localite.uuid', $localite->uuid);
});

it('accepte la pièce en image ou en PDF, mais le selfie en image seulement', function () {
    $this->putJson(route('api.v1.identity.update'), ['commune' => $this->commune->uuid, 'document_type' => 'cni'])->assertOk();

    sendIdentityFile('recto', UploadedFile::fake()->create('cni.pdf', 200, 'application/pdf'))->assertCreated()->assertJsonPath('data.mime', 'application/pdf');
    sendIdentityFile('selfie', UploadedFile::fake()->create('selfie.pdf', 200, 'application/pdf'))->assertJsonValidationErrors('file');
    sendIdentityFile('page')->assertUnprocessable()->assertJsonValidationErrors('kind');

    expect(Storage::disk('identity')->allFiles())->toHaveCount(1);
});

it('retire les faces devenues inutiles quand le type de pièce change', function () {
    completeIdentity('cni');
    $this->putJson(route('api.v1.identity.update'), ['commune' => $this->commune->uuid, 'document_type' => 'passeport'])
        ->assertOk()
        ->assertJsonCount(2, 'data.files')
        ->assertJsonPath('data.missing', ['files.page' => 'Ajoutez : Page de la pièce.']);
});

it('refuse un fichier infecté sans le stocker [§10]', function () {
    app()->instance(VirusScanner::class, new class implements VirusScanner
    {
        public function scan(string $path): ?string
        {
            return 'Win.Test.EICAR_HDB-1';
        }
    });
    $this->putJson(route('api.v1.identity.update'), ['commune' => $this->commune->uuid, 'document_type' => 'cni'])->assertOk();

    $before = Storage::disk('identity')->allFiles();

    sendIdentityFile('selfie')->assertUnprocessable()->assertJsonValidationErrors('file');

    expect(Storage::disk('identity')->allFiles())->toBe($before);
});

it('envoie la vérification, la verrouille, puis l’administration la valide : éligible aux gains et rattaché à sa commune [MOB-01]', function () {
    completeIdentity();

    $this->postJson(route('api.v1.identity.submit'))->assertOk()->assertJsonPath('data.status', 'submitted');
    sendIdentityFile('selfie')->assertConflict();

    $admin = User::factory()->withTwoFactor()->withRole(Role::Admin)->create();
    $verification = IdentityVerification::query()->where('user_id', $this->relais->id)->sole();

    Livewire::actingAs($admin)->test(IdentityShowPage::class, ['verification' => $verification])->call('verify');

    expect($verification->refresh()->status)->toBe(IdentityStatus::Verified)
        ->and($this->relais->refresh()->isEligibleForGains())->toBeTrue()
        ->and(RelaisProfile::withoutCommuneRestriction()->where('user_id', $this->relais->id)->value('commune_id'))->toBe($this->commune->id);

    Sanctum::actingAs($this->relais);
    $this->getJson(route('api.v1.me.show'))->assertJsonPath('data.identity_status', 'verified')->assertJsonPath('data.gains_eligible', true);
});

it('renvoie la vérification au Relais avec un commentaire, puis il corrige et renvoie', function () {
    completeIdentity();
    $this->postJson(route('api.v1.identity.submit'))->assertOk();

    $admin = User::factory()->withTwoFactor()->withRole(Role::Admin)->create();
    $verification = IdentityVerification::query()->where('user_id', $this->relais->id)->sole();

    Livewire::actingAs($admin)->test(IdentityShowPage::class, ['verification' => $verification])
        ->call('reject')->assertHasErrors('comment')
        ->set('comment', 'Le verso est flou.')->call('reject')->assertHasNoErrors();

    Sanctum::actingAs($this->relais);
    $this->getJson(route('api.v1.identity.show'))
        ->assertJsonPath('data.status', 'rejected')
        ->assertJsonPath('data.decision_comment', 'Le verso est flou.')
        ->assertJsonPath('data.is_editable', true);

    sendIdentityFile('verso')->assertCreated();
    $this->postJson(route('api.v1.identity.submit'))->assertOk()->assertJsonPath('data.status', 'submitted');
});

it('montre les fichiers à l’administration par lien signé, audité, et les refuse sans signature', function () {
    completeIdentity();
    $file = IdentityVerification::query()->where('user_id', $this->relais->id)->sole()->files()->first();
    $admin = User::factory()->withTwoFactor()->withRole(Role::Admin)->create();

    $this->actingAs($admin)->get(URL::temporarySignedRoute('console.identity.file', now()->addMinutes(5), $file))->assertOk();
    $this->actingAs($admin)->get(route('console.identity.file', $file))->assertForbidden();
    $this->assertDatabaseHas('audit_logs', ['action' => 'identity.file.viewed']);
});

it('réserve les vérifications d’identité à l’administration', function () {
    $coordinator = User::factory()->withTwoFactor()->withRole(Role::Coordonnateur)->create();

    $this->actingAs($coordinator)->get(route('console.identity'))->assertForbidden();

    $candidat = User::factory()->withRole(Role::Candidat)->create();
    Sanctum::actingAs($candidat);
    $this->getJson(route('api.v1.identity.show'))->assertForbidden();
});

it('efface les pièces d’identité à la suppression du compte (§9.4)', function () {
    completeIdentity();

    app(PseudonymizeUser::class)->handle($this->relais, $this->relais, 'Demande du titulaire');

    expect(IdentityVerification::query()->count())->toBe(0)
        ->and(Storage::disk('identity')->allFiles())->toBe([]);
});

it('accepte la date de naissance au format JJ/MM/AAAA dans le profil', function () {
    $this->putJson(route('api.v1.identity.update'), ['birth_date' => '26/04/2000'])
        ->assertOk()
        ->assertJsonPath('data.birth_date', '2000-04-26');
});
