<?php

use App\Domain\Access\Enums\Role;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Candidature\Enums\CandidatureStatus;
use App\Domain\Candidature\Events\CandidatureSubmitted;
use App\Domain\Candidature\Models\Candidature;
use App\Domain\Legal\Models\LegalDocument;
use App\Domain\Settings\Settings;
use App\Domain\Territoire\Enums\LocaliteType;
use App\Domain\Territoire\Models\Commune;
use App\Domain\Territoire\Models\Localite;
use App\Models\User;
use Database\Seeders\LegalDocumentsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->candidat = User::factory()->withRole(Role::Candidat)->create();
    $this->commune = Commune::factory()->create(['name' => 'Yopougon', 'slug' => 'yopougon']);
    $this->localite = Localite::query()->create(['commune_id' => $this->commune->id, 'name' => 'Niangon', 'type' => LocaliteType::cases()[0]]);
    Sanctum::actingAs($this->candidat);
    Storage::fake('identity');
    $this->seed(LegalDocumentsSeeder::class);
});

/**
 * @param  array<string, mixed>  $data
 */
function saveStep(string $step, array $data): TestResponse
{
    return test()->putJson(route('api.v1.candidature.steps.update', $step), $data);
}

function fillAllSteps(): void
{
    saveStep('identite', ['last_name' => 'Kouassi', 'first_names' => 'Awa Marie', 'birth_date' => now()->subYears(24)->toDateString(), 'gender' => 'femme'])->assertOk();
    saveStep('territoire', ['commune' => test()->commune->uuid, 'localite' => test()->localite->uuid])->assertOk();
    saveStep('profil', ['occupation' => 'etudiant', 'languages' => ['fr', 'dioula'], 'motivation' => 'Montrer la vie de mon quartier et alerter vite quand il se passe quelque chose.'])->assertOk();

    foreach (['profile_photo', 'id_front', 'id_back', 'selfie'] as $type) {
        uploadDocument($type)->assertCreated();
    }

    test()->postJson(route('api.v1.consents.store'), [
        'documents' => array_values(array_map(fn (LegalDocument $document) => $document->uuid, LegalDocument::currentSet())),
    ])->assertCreated();
}

function uploadDocument(string $type, ?UploadedFile $file = null): TestResponse
{
    return test()->post(route('api.v1.candidature.documents.store'), [
        'type' => $type,
        'file' => $file ?? UploadedFile::fake()->image("{$type}.jpg", 800, 600),
    ], ['Accept' => 'application/json']);
}

it('ouvre un dossier en brouillon au premier accès [MOB-02]', function () {
    $this->getJson(route('api.v1.candidature.show'))
        ->assertOk()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.status_label', 'En cours de rédaction')
        ->assertJsonPath('data.is_editable', true)
        ->assertJsonCount(5, 'data.checklist')
        ->assertJsonMissingPath('data.id');

    expect(Candidature::query()->where('user_id', $this->candidat->id)->count())->toBe(1);
});

it('enregistre chaque étape séparément, même partiellement (sauvegarde automatique) [MOB-02]', function () {
    saveStep('identite', ['last_name' => 'Kouassi'])
        ->assertOk()
        ->assertJsonPath('data.identite.last_name', 'Kouassi')
        ->assertJsonPath('data.checklist.0.complete', false);

    saveStep('identite', ['first_names' => 'Awa', 'birth_date' => '2000-05-14', 'gender' => 'femme'])
        ->assertOk()
        ->assertJsonPath('data.identite.last_name', 'Kouassi')
        ->assertJsonPath('data.checklist.0.complete', true);
});

it('rattache le dossier à une commune ouverte et à une localité de cette commune [MOB-02]', function () {
    $autre = Commune::factory()->create();
    $localiteAilleurs = Localite::query()->create(['commune_id' => $autre->id, 'name' => 'Ailleurs', 'type' => LocaliteType::cases()[0]]);
    $fermee = Commune::factory()->inactive()->create();

    saveStep('territoire', ['commune' => $fermee->uuid])->assertUnprocessable()->assertJsonValidationErrors('commune');
    saveStep('territoire', ['commune' => $this->commune->uuid, 'localite' => $localiteAilleurs->uuid])->assertUnprocessable()->assertJsonValidationErrors('localite');

    saveStep('territoire', ['commune' => $this->commune->uuid, 'localite' => $this->localite->uuid])
        ->assertOk()
        ->assertJsonPath('data.territoire.commune.name', 'Yopougon')
        ->assertJsonPath('data.territoire.localite.name', 'Niangon');
});

it('rejette proprement des identifiants mal formés', function () {
    saveStep('territoire', ['commune' => 'pas-un-uuid', 'localite' => 'x', 'lieu_type' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['commune', 'localite']);
});

it('efface la localité quand le candidat change de commune', function () {
    saveStep('territoire', ['commune' => $this->commune->uuid, 'localite' => $this->localite->uuid])->assertOk();

    saveStep('territoire', ['commune' => Commune::factory()->create()->uuid])
        ->assertOk()
        ->assertJsonPath('data.territoire.localite', null);
});

it('refuse un candidat trop jeune quand les mineurs ne sont pas admis [§9.3]', function () {
    saveStep('identite', ['birth_date' => now()->subYears(16)->toDateString()])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['birth_date' => 'Il faut avoir au moins 18 ans pour devenir Relais.']);
});

it('admet un mineur si le paramètre l’autorise et le signale dans le dossier [§9.3]', function () {
    $settings = app(Settings::class);
    $settings->set('candidature.minors_allowed', true);
    $settings->set('candidature.minimum_age', 15);

    saveStep('identite', ['birth_date' => now()->subYears(16)->toDateString()])
        ->assertOk()
        ->assertJsonPath('data.identite.is_minor', true);

    saveStep('identite', ['birth_date' => now()->subYears(14)->toDateString()])
        ->assertJsonValidationErrors(['birth_date' => 'Il faut avoir au moins 15 ans pour devenir Relais.']);
});

it('refuse les valeurs hors liste', function () {
    saveStep('profil', ['occupation' => 'pilote', 'languages' => ['klingon']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['occupation', 'languages.0']);

    $this->putJson(route('api.v1.candidature.steps.update', 'inconnue'), [])->assertNotFound();
});

it('refuse le dépôt d’un dossier incomplet en listant ce qui manque [MOB-06]', function () {
    saveStep('identite', ['last_name' => 'Kouassi'])->assertOk();

    $this->postJson(route('api.v1.candidature.submit'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['identite', 'territoire', 'profil', 'documents', 'charte']);
});

it('exige une motivation d’au moins 30 caractères au dépôt', function () {
    fillAllSteps();
    saveStep('profil', ['motivation' => 'Trop court.'])->assertOk();

    $this->postJson(route('api.v1.candidature.submit'))->assertJsonValidationErrors('motivation');
});

it('dépose le dossier complet, le trace et le verrouille [MOB-06]', function () {
    Event::fake([CandidatureSubmitted::class]);
    fillAllSteps();

    $this->postJson(route('api.v1.candidature.submit'))
        ->assertOk()
        ->assertJsonPath('data.status', 'submitted')
        ->assertJsonPath('data.status_label', 'Reçue')
        ->assertJsonPath('data.is_editable', false);

    Event::assertDispatched(CandidatureSubmitted::class);
    expect(AuditLog::query()->where('action', 'candidature.submitted')->sole()->commune_id)->toBe($this->commune->id);

    saveStep('identite', ['last_name' => 'Autre'])->assertConflict();
    $this->postJson(route('api.v1.candidature.submit'))->assertConflict();
});

it('permet de redéposer après une demande de complément', function () {
    fillAllSteps();
    Candidature::query()->where('user_id', $this->candidat->id)->update(['status' => CandidatureStatus::ComplementRequested->value]);

    saveStep('profil', ['motivation' => 'Motivation complétée à la demande du coordonnateur de ma commune.'])->assertOk();
    $this->postJson(route('api.v1.candidature.submit'))->assertOk()->assertJsonPath('data.status', 'submitted');

    expect(AuditLog::query()->where('action', 'candidature.resubmitted')->exists())->toBeTrue();
});

it('réserve le dossier de candidature aux candidats', function () {
    Sanctum::actingAs(User::factory()->withRole(Role::Coordonnateur)->create());

    $this->getJson(route('api.v1.candidature.show'))->assertForbidden();
    saveStep('identite', ['last_name' => 'X'])->assertForbidden();
    $this->postJson(route('api.v1.candidature.submit'))->assertForbidden();
});

it('n’expose que son propre dossier : aucun identifiant de dossier dans l’URL', function () {
    $autre = Candidature::factory()->submitted()->create();

    $this->getJson(route('api.v1.candidature.show'))
        ->assertOk()
        ->assertJsonPath('data.uuid', fn (string $uuid) => $uuid !== $autre->uuid);
});

it('fournit les listes du formulaire et les localités d’une commune', function () {
    $this->getJson(route('api.v1.candidature.options'))
        ->assertOk()
        ->assertJsonCount(3, 'data.steps')
        ->assertJsonPath('data.genders.0.label', 'Femme');

    $this->getJson(route('api.v1.communes.localites', $this->commune))
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Niangon');

    $this->getJson(route('api.v1.communes.localites', Commune::factory()->inactive()->create()))->assertNotFound();
});
