<?php

use App\Domain\Access\Enums\Role;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Auth\Data\PhoneNumber;
use App\Domain\Candidature\Enums\CandidatureStatus;
use App\Domain\Candidature\Models\Candidature;
use App\Domain\Territoire\Models\Commune;
use App\Livewire\Relais\Dossier;
use App\Livewire\Relais\Rejoindre;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->sms = fakeSms();
});

it('affiche la page Devenir Relais [WEB-02]', function () {
    $this->get(route('relais.join'))->assertOk()->assertSee('Commençons par votre numéro');
});

it('crée le compte candidat après le code SMS et ouvre le dossier [WEB-02]', function () {
    $component = Livewire::test(Rejoindre::class)
        ->set('phone', '07 07 07 07 07')
        ->call('sendCode')
        ->assertHasNoErrors()
        ->assertSet('maskedPhone', fn (?string $masked) => $masked !== null);

    $component->set('code', $this->sms->lastCodeFor('+2250707070707'))
        ->call('verify')
        ->assertRedirect(route('relais.candidature'));

    $user = User::findByPhone(PhoneNumber::parse('0707070707'));
    expect($user)->not->toBeNull()
        ->and($user->hasRole(Role::Candidat->value))->toBeTrue()
        ->and(auth()->id())->toBe($user->id)
        ->and(AuditLog::query()->where('action', 'auth.login')->exists())->toBeTrue();
});

it('refuse un mauvais code sans connecter personne', function () {
    Livewire::test(Rejoindre::class)
        ->set('phone', '0707070707')
        ->call('sendCode')
        ->set('code', '000000')
        ->call('verify')
        ->assertHasErrors('code');

    expect(auth()->check())->toBeFalse();
});

it('refuse la connexion par SMS d’un compte de l’équipe (pas de contournement de la double authentification)', function () {
    User::factory()->withRole(Role::Admin)->create()->setPhoneNumber(PhoneNumber::parse('0505050505'))->save();

    Livewire::test(Rejoindre::class)
        ->set('phone', '0505050505')
        ->call('sendCode')
        ->set('code', $this->sms->lastCodeFor('+2250505050505'))
        ->call('verify')
        ->assertHasErrors('code')
        ->assertSee('connectez-vous depuis la console');

    expect(auth()->check())->toBeFalse();
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
    $this->get(route('relais.candidature'))->assertRedirect(route('relais.join'));
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
