<?php

use App\Domain\Access\Enums\Role;
use App\Domain\Auth\Data\PhoneNumber;
use App\Domain\Auth\Enums\UserStatus;
use App\Domain\Identity\Actions\DecideIdentityVerification;
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

    fakeMultipart();
    $this->postJson(route('api.v1.relais.store'), relaisApiPayload())->assertCreated();
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
        ->assertJsonPath('data.first_name', 'Awa Mariam');

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

it('enregistre la commune et une localité de cette commune envoyées ensemble [MOB-01]', function () {
    $localite = Localite::query()->create(['commune_id' => $this->commune->id, 'name' => 'Riviera 2', 'type' => 'quartier']);

    $this->putJson(route('api.v1.identity.update'), ['commune' => $this->commune->uuid, 'localite' => $localite->uuid])
        ->assertOk()
        ->assertJsonPath('data.commune.uuid', $this->commune->uuid)
        ->assertJsonPath('data.localite.name', 'Riviera 2');
});

it('explique qu’une localité inconnue doit venir de GET /communes/{commune}/localites', function () {
    $this->putJson(route('api.v1.identity.update'), ['commune' => $this->commune->uuid, 'localite' => '9d2c6f0e-2b3a-4c5d-8e7f-0a1b2c3d4e5f'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['localite' => 'Cette localité n’existe pas : utilisez un uuid renvoyé par GET /communes/{commune}/localites.']);
});

it('refuse une localité d’une autre commune au lieu de l’ignorer en silence', function () {
    $ailleurs = Commune::factory()->create();
    $localite = Localite::query()->create(['commune_id' => $ailleurs->id, 'name' => 'Ailleurs', 'type' => 'quartier']);

    $this->putJson(route('api.v1.identity.update'), ['commune' => $this->commune->uuid, 'localite' => $localite->uuid])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['localite' => 'Cette localité n’appartient pas à la commune choisie.']);
});

it('accepte la pièce en image ou en PDF, mais le selfie en image seulement', function () {
    $this->putJson(route('api.v1.identity.update'), ['commune' => $this->commune->uuid, 'document_type' => 'cni'])->assertOk();

    sendIdentityFile('recto', UploadedFile::fake()->create('cni.pdf', 200, 'application/pdf'))->assertCreated()->assertJsonPath('data.mime', 'application/pdf');
    sendIdentityFile('selfie', UploadedFile::fake()->create('selfie.pdf', 200, 'application/pdf'))->assertJsonValidationErrors('file');
    sendIdentityFile('page')->assertUnprocessable()->assertJsonValidationErrors('kind');

    expect(Storage::disk('identity')->allFiles())->toHaveCount(1);
});

it('retire les faces devenues inutiles quand le type de pièce change', function () {
    $this->putJson(route('api.v1.identity.update'), [
        'last_name' => 'Traoré', 'first_names' => 'Awa', 'gender' => 'femme', 'birth_date' => '2001-04-17',
        'commune' => $this->commune->uuid, 'document_type' => 'cni',
    ])->assertOk();
    foreach (['recto', 'verso', 'selfie'] as $kind) {
        sendIdentityFile($kind)->assertCreated();
    }

    $this->putJson(route('api.v1.identity.update'), ['document_type' => 'passeport'])
        ->assertOk()
        ->assertJsonCount(1, 'data.files')
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.missing', ['files.page' => 'Ajoutez : Page de la pièce.', 'files.profile_photo' => 'Ajoutez : Photo de profil.']);
});

it('envoie le dossier tout seul en vérification dès le dernier fichier reçu, sans POST /identity/submit [MOB-01]', function () {
    $this->putJson(route('api.v1.identity.update'), [
        'last_name' => 'Traoré', 'first_names' => 'Awa', 'gender' => 'femme', 'birth_date' => '26/04/2000',
        'commune' => $this->commune->uuid, 'document_type' => 'cni',
    ])->assertOk()->assertJsonPath('data.status', 'draft');

    foreach (['recto', 'verso', 'selfie'] as $kind) {
        sendIdentityFile($kind)->assertCreated();
    }
    $this->getJson(route('api.v1.identity.show'))->assertJsonPath('data.status', 'draft');

    sendIdentityFile('profile_photo')->assertCreated();

    $this->getJson(route('api.v1.identity.show'))
        ->assertOk()
        ->assertJsonPath('data.status', 'submitted')
        ->assertJsonPath('data.is_editable', false)
        ->assertJsonPath('data.missing', []);
    $this->assertDatabaseHas('audit_logs', ['action' => 'identity.submitted']);

    $this->postJson(route('api.v1.identity.submit'))->assertOk()->assertJsonPath('data.status', 'submitted');
});

it('envoie aussi le dossier quand le dernier champ manquant est rempli après les fichiers [MOB-01]', function () {
    $this->putJson(route('api.v1.identity.update'), ['commune' => $this->commune->uuid, 'document_type' => 'passeport'])->assertOk();
    foreach (['page', 'selfie', 'profile_photo'] as $kind) {
        sendIdentityFile($kind)->assertCreated();
    }
    $this->getJson(route('api.v1.identity.show'))->assertJsonPath('data.status', 'draft');

    $this->putJson(route('api.v1.identity.update'), ['last_name' => 'Traoré', 'first_names' => 'Awa', 'gender' => 'femme', 'birth_date' => '26/04/2000'])
        ->assertOk()
        ->assertJsonPath('data.status', 'submitted');
});

it('renvoie tout seul un dossier refusé une fois le fichier corrigé [MOB-01]', function () {
    completeIdentity('cni');
    $verification = IdentityVerification::query()->sole();
    expect($verification->status->value)->toBe('submitted');

    app(DecideIdentityVerification::class)->reject($verification, User::factory()->withTwoFactor()->withRole(Role::Admin)->create(), 'Selfie flou.');
    $this->getJson(route('api.v1.identity.show'))->assertJsonPath('data.status', 'rejected');

    sendIdentityFile('selfie')->assertCreated();

    $this->getJson(route('api.v1.identity.show'))->assertJsonPath('data.status', 'submitted');
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

/**
 * @param  array<string, mixed>  $overrides
 */
function submitIdentityAtOnce(array $overrides = []): TestResponse
{
    return test()->post(route('api.v1.identity.store'), [
        'last_name' => 'Traoré',
        'first_name' => 'Abou',
        'gender' => 'homme',
        'birth_date' => '26/04/2000',
        'commune' => test()->commune->uuid,
        'document_type' => 'cni',
        'recto' => UploadedFile::fake()->image('recto.jpg', 800, 600),
        'verso' => UploadedFile::fake()->image('verso.jpg', 800, 600),
        'selfie' => UploadedFile::fake()->image('selfie.jpg', 800, 600),
        'profile_photo' => UploadedFile::fake()->image('profil.jpg', 600, 600),
        ...$overrides,
    ], ['Accept' => 'application/json']);
}

it('envoie toute la vérification d’identité en une seule requête, aussitôt en vérification [MOB-01]', function () {
    $localite = Localite::query()->create(['commune_id' => $this->commune->id, 'name' => 'Abatta', 'type' => 'village']);

    submitIdentityAtOnce(['localite' => $localite->uuid])
        ->assertOk()
        ->assertJsonPath('data.status', 'submitted')
        ->assertJsonPath('data.is_editable', false)
        ->assertJsonPath('data.first_name', 'Abou')
        ->assertJsonPath('data.birth_date', '2000-04-26')
        ->assertJsonPath('data.commune.uuid', $this->commune->uuid)
        ->assertJsonPath('data.localite.name', 'Abatta')
        ->assertJsonPath('data.document_type', 'cni')
        ->assertJsonCount(4, 'data.files')
        ->assertJsonPath('data.missing', []);

    expect(Storage::disk('identity')->allFiles())->toHaveCount(4);

    // Visible aussitôt dans la file de l'administration.
    $admin = User::factory()->withTwoFactor()->withRole(Role::Admin)->create();
    $this->actingAs($admin)->get(route('console.identity'))->assertOk()->assertSee('Traoré');
});

it('demande « page » au lieu de recto et verso pour un passeport', function () {
    submitIdentityAtOnce(['document_type' => 'passeport'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['page' => 'Ajoutez : Page de la pièce.', 'recto', 'verso']);

    submitIdentityAtOnce(['document_type' => 'passeport', 'recto' => null, 'verso' => null, 'page' => UploadedFile::fake()->create('passeport.pdf', 300, 'application/pdf')])
        ->assertOk()
        ->assertJsonPath('data.status', 'submitted')
        ->assertJsonCount(3, 'data.files');
});

it('liste tout ce qui manque et n’enregistre rien si la requête est incomplète', function () {
    $this->post(route('api.v1.identity.store'), ['document_type' => 'cni'], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['last_name', 'first_names' => 'Indiquez vos prénoms.', 'gender', 'birth_date', 'commune' => 'Choisissez votre commune.', 'recto', 'verso', 'selfie', 'profile_photo']);

    expect(Storage::disk('identity')->allFiles())->toBeEmpty()
        ->and(IdentityVerification::query()->where('user_id', $this->relais->id)->value('status'))->not->toBe('submitted');
});

it('n’enregistre aucun fichier si l’antivirus en refuse un', function () {
    app()->instance(VirusScanner::class, new class implements VirusScanner
    {
        public function scan(string $path): ?string
        {
            return str_contains((string) file_get_contents($path), 'EICAR') ? 'Win.Test.EICAR_HDB-1' : null;
        }
    });

    submitIdentityAtOnce(['selfie' => UploadedFile::fake()->createWithContent('selfie.jpg', 'EICAR')])
        ->assertUnprocessable();

    expect(Storage::disk('identity')->allFiles())->toBeEmpty();
});

it('refuse un nouvel envoi pendant la vérification, et accepte le renvoi corrigé après un refus [MOB-01]', function () {
    submitIdentityAtOnce()->assertOk();
    submitIdentityAtOnce()->assertConflict();
    $this->post(route('api.v1.identity.store'), ['last_name' => 'X'], ['Accept' => 'application/json'])->assertConflict();

    $verification = IdentityVerification::query()->where('user_id', $this->relais->id)->sole();
    app(DecideIdentityVerification::class)->reject($verification, User::factory()->withTwoFactor()->withRole(Role::Admin)->create(), 'Selfie flou.');

    submitIdentityAtOnce()->assertOk()->assertJsonPath('data.status', 'submitted');
    expect(Storage::disk('identity')->allFiles())->toHaveCount(4);
});
