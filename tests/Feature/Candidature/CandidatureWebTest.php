<?php

use App\Domain\Access\Enums\Role;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Auth\Data\PhoneNumber;
use App\Domain\Candidature\Enums\CandidatureStatus;
use App\Domain\Candidature\Models\Candidature;
use App\Domain\Candidature\Models\CandidatureDocument;
use App\Domain\Territoire\Models\Commune;
use App\Livewire\Relais\Dossier;
use App\Livewire\Relais\Rejoindre;
use App\Models\User;
use Database\Seeders\LegalDocumentsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->sms = fakeSms();
    $this->seed(LegalDocumentsSeeder::class);
});

it('affiche la page Devenir Relais [WEB-02]', function () {
    $this->get(route('relais.join'))->assertOk()->assertSee('Créez votre compte')->assertSee('Se connecter');
});

it('inscrit sur le site comme dans l’application : numéro, code, mot de passe, puis connexion [WEB-02]', function () {
    $component = Livewire::test(Rejoindre::class)
        ->set('phone', '07 07 07 07 07')
        ->call('sendCode')
        ->assertHasNoErrors()
        ->assertSet('step', 'code');

    $component->set('code', $this->sms->lastCodeFor('+2250707070707'))
        ->call('verify')
        ->assertHasNoErrors()
        ->assertSet('step', 'password')
        ->set('password', 'MotDePasse2026')
        ->set('passwordConfirmation', 'MotDePasse2026')
        ->call('savePassword')
        ->assertHasNoErrors()
        ->assertSet('mode', 'connexion')
        ->assertSee('Inscription terminée');

    $user = User::findByPhone(PhoneNumber::parse('0707070707'));
    expect($user)->not->toBeNull()
        ->and($user->hasRole(Role::Relais->value))->toBeTrue()
        ->and(auth()->check())->toBeFalse();

    $component->set('password', 'MotDePasse2026')->call('login')->assertRedirect(route('relais.send'));

    expect(auth()->id())->toBe($user->id)
        ->and(AuditLog::query()->where('action', 'auth.login')->exists())->toBeTrue();
});

it('exige un mot de passe d’au moins 8 caractères, confirmé, à l’inscription sur le site', function () {
    Livewire::test(Rejoindre::class)
        ->set('phone', '0707070707')->call('sendCode')
        ->set('code', $this->sms->lastCodeFor('+2250707070707'))->call('verify')
        ->set('password', 'court')->set('passwordConfirmation', 'court')->call('savePassword')
        ->assertHasErrors(['password' => 'Le mot de passe doit contenir au moins 8 caractères.'])
        ->set('password', 'MotDePasse2026')->set('passwordConfirmation', 'Autre2026xx')->call('savePassword')
        ->assertHasErrors(['password' => 'Les deux mots de passe ne correspondent pas.']);

    expect(User::query()->count())->toBe(0);
});

it('refuse un mauvais code sans connecter personne', function () {
    Livewire::test(Rejoindre::class)
        ->set('phone', '0707070707')
        ->call('sendCode')
        ->set('code', '0000')
        ->call('verify')
        ->assertHasErrors('code')
        ->assertSet('step', 'code');

    expect(auth()->check())->toBeFalse();
});

it('refuse l’inscription sur le site d’un numéro qui a déjà un compte', function () {
    User::factory()->withRole(Role::Relais)->create()->setPhoneNumber(PhoneNumber::parse('0707070707'))->save();

    Livewire::test(Rejoindre::class)
        ->set('phone', '0707070707')
        ->call('sendCode')
        ->assertHasErrors(['phone' => 'Ce numéro a déjà un compte. Connectez-vous avec votre mot de passe.']);

    expect($this->sms->sent)->toBe([]);
});

it('refuse la connexion sur le site d’un compte de l’équipe (pas de contournement de la double authentification)', function () {
    User::factory()->withRole(Role::Admin)->create(['password' => 'MotDePasse2026'])->setPhoneNumber(PhoneNumber::parse('0505050505'))->save();

    Livewire::test(Rejoindre::class, ['mode' => 'connexion'])
        ->set('phone', '0505050505')
        ->set('password', 'MotDePasse2026')
        ->call('login')
        ->assertHasErrors('phone')
        ->assertSee('connectez-vous depuis la console');

    expect(auth()->check())->toBeFalse();
});

it('redéfinit un mot de passe oublié sur le site puis connecte, et fait d’un ancien candidat un Relais', function () {
    $user = User::factory()->withRole(Role::Candidat)->create(['password' => null]);
    $user->setPhoneNumber(PhoneNumber::parse('0707070707'))->save();

    Livewire::test(Rejoindre::class)
        ->call('switchMode', 'oubli')
        ->set('phone', '0707070707')->call('sendCode')->assertHasNoErrors()
        ->set('code', $this->sms->lastCodeFor('+2250707070707'))->call('verify')->assertSet('step', 'password')
        ->set('password', 'NouveauSecret!8')->set('passwordConfirmation', 'NouveauSecret!8')->call('savePassword')
        ->assertRedirect(route('relais.send'));

    expect(auth()->id())->toBe($user->id)
        ->and($user->refresh()->hasRole(Role::Relais->value))->toBeTrue()
        ->and(Hash::check('NouveauSecret!8', $user->password))->toBeTrue();
});

it('envoie le mot de passe oublié vers un code, mais n’en redéfinit aucun pour un numéro sans compte', function () {
    Livewire::test(Rejoindre::class)
        ->call('switchMode', 'oubli')
        ->set('phone', '0707070707')->call('sendCode')
        ->set('code', $this->sms->lastCodeFor('+2250707070707'))->call('verify')
        ->assertHasErrors(['code' => 'Aucun compte n’est associé à ce numéro. Inscrivez-vous d’abord.']);
});

it('limite les envois de code par numéro sur le site aussi [§10 Sécurité]', function () {
    $component = Livewire::test(Rejoindre::class);

    foreach (range(1, 3) as $attempt) {
        $component->set('phone', '0707070707')->call('sendCode')->assertHasNoErrors();
    }

    $component->set('phone', '0707070707')->call('sendCode')->assertHasErrors('phone');
    expect($this->sms->sent)->toHaveCount(3);
});

it('renvoie les visiteurs non connectés de l’espace candidat vers Devenir Relais', function () {
    $this->get(route('relais.profile'))->assertRedirect(route('relais.join', ['mode' => 'connexion']));
});

it('enregistre automatiquement chaque champ du dossier [MOB-02]', function () {
    $candidat = User::factory()->withRole(Role::Candidat)->create();
    $commune = Commune::factory()->create();

    Livewire::actingAs($candidat)->test(Dossier::class)
        ->set('identite.last_name', 'Kouassi')
        ->set('identite.birth_date', now()->subYears(15)->toDateString())
        ->assertHasErrors('identite.birth_date')
        ->set('territoire.commune', $commune->uuid)
        ->assertSet('savedAt', fn (?string $time) => $time !== null);

    $candidature = Candidature::withoutCommuneRestriction()->where('user_id', $candidat->id)->sole();
    expect($candidature->last_name)->toBe('Kouassi')
        ->and($candidature->birth_date)->toBeNull()
        ->and($candidature->commune_id)->toBe($commune->id);
});

it('liste ce qui manque puis dépose le dossier complet [MOB-06]', function () {
    $candidature = Candidature::factory()->create();

    Livewire::actingAs($candidature->user)->test(Dossier::class)
        ->call('goTo', 'recapitulatif')
        ->call('submit')
        ->assertSet('missing', fn (array $missing) => array_key_exists('identite', $missing))
        ->assertSee('Votre dossier n’est pas encore complet.');

    $complete = Candidature::factory()->completed()->create();

    Livewire::actingAs($complete->user)->test(Dossier::class)
        ->call('goTo', 'recapitulatif')
        ->call('submit')
        ->assertSee('Candidature déposée.');

    expect($complete->fresh()->status)->toBe(CandidatureStatus::Submitted);
});

it('affiche le motif d’un refus au candidat [MOB-06]', function () {
    $candidature = Candidature::factory()->submitted()->create();
    $candidature->forceFill(['status' => CandidatureStatus::Rejected, 'decision_comment' => 'Pièce illisible, merci de recommencer plus tard.'])->save();

    Livewire::actingAs($candidature->user)->test(Dossier::class)
        ->assertSee('Votre candidature n’a pas été retenue.')
        ->assertSee('Pièce illisible, merci de recommencer plus tard.');
});

it('interdit l’espace candidat aux comptes de l’équipe', function () {
    $this->actingAs(User::factory()->withRole(Role::Coordonnateur)->create())
        ->get(route('relais.candidature'))
        ->assertForbidden();
});

it('envoie une pièce depuis le site, l’affiche à son propriétaire uniquement [MOB-03, WEB-02]', function () {
    Storage::fake('identity');
    $candidature = Candidature::factory()->create();

    Livewire::actingAs($candidature->user)->test(Dossier::class)
        ->call('goTo', 'documents')
        ->set('uploads.id_front', UploadedFile::fake()->image('recto.jpg', 800, 600))
        ->assertHasNoErrors()
        ->assertSee('Remplacer');

    $document = CandidatureDocument::query()->sole();
    Storage::disk('identity')->assertExists($document->path);

    $this->actingAs($candidature->user)->get(route('relais.documents.show', $document))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg');

    $this->actingAs(Candidature::factory()->create()->user)->get(route('relais.documents.show', $document))->assertNotFound();
});

it('signale une pièce au mauvais format sans l’enregistrer', function () {
    Storage::fake('identity');
    $candidature = Candidature::factory()->create();

    Livewire::actingAs($candidature->user)->test(Dossier::class)
        ->set('uploads.selfie', UploadedFile::fake()->create('selfie.pdf', 50, 'application/pdf'))
        ->assertHasErrors('uploads.selfie');

    expect(CandidatureDocument::query()->count())->toBe(0);
});
