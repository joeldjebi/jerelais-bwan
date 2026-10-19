<?php

use App\Domain\Access\Enums\Role;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Candidature\Models\Candidature;
use App\Domain\Legal\Actions\AcceptLegalDocuments;
use App\Domain\Legal\Actions\PublishLegalDocument;
use App\Domain\Legal\Enums\LegalDocumentType;
use App\Domain\Legal\Models\Consent;
use App\Domain\Legal\Models\LegalDocument;
use App\Livewire\Console\Legal\Index;
use App\Livewire\Relais\Dossier;
use App\Models\User;
use Database\Seeders\LegalDocumentsSeeder;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(LegalDocumentsSeeder::class);
    $this->candidat = User::factory()->withRole(Role::Candidat)->create();
});

/**
 * @return list<string>
 */
function currentUuids(): array
{
    return array_values(array_map(fn (LegalDocument $document) => $document->uuid, LegalDocument::currentSet()));
}

it('publie les trois textes en vigueur, sans HTML injectable [MOB-04, WEB-07]', function () {
    app(PublishLegalDocument::class)->handle(LegalDocumentType::Charte, 'Charte', 'Texte <script>alert(1)</script> **important**');

    $this->getJson(route('api.v1.legal.index'))
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.type', 'charte')
        ->assertJsonPath('data.0.version', 2)
        ->assertJsonPath('data.0.body_html', fn (string $html) => ! str_contains($html, '<script>') && str_contains($html, '<strong>important</strong>'));
});

it('enregistre une acceptation horodatée par version, avec le canal et l’adresse IP [MOB-04]', function () {
    Sanctum::actingAs($this->candidat);

    $this->postJson(route('api.v1.consents.store'), ['documents' => currentUuids()])
        ->assertCreated()
        ->assertJsonPath('meta.up_to_date', true);

    expect(Consent::query()->where('user_id', $this->candidat->id)->count())->toBe(3)
        ->and(Consent::query()->first()->channel)->toBe('app')
        ->and(Consent::query()->first()->ip_address)->toBe('127.0.0.1')
        ->and(AuditLog::query()->where('action', 'legal.accepted')->sole()->payload['versions'])->toContain('charte v1');

    $this->getJson(route('api.v1.consents.index'))->assertJsonPath('meta.up_to_date', true)->assertJsonCount(3, 'data');
});

it('refuse une acceptation partielle ou d’une version dépassée', function () {
    Sanctum::actingAs($this->candidat);
    $uuids = currentUuids();

    $this->postJson(route('api.v1.consents.store'), ['documents' => array_slice($uuids, 0, 2)])->assertJsonValidationErrors('documents');

    app(PublishLegalDocument::class)->handle(LegalDocumentType::Conditions, 'Conditions v2', 'Nouveau texte.');

    $this->postJson(route('api.v1.consents.store'), ['documents' => $uuids])
        ->assertJsonValidationErrors(['documents' => 'Les textes ont été mis à jour : relisez la dernière version avant de l’accepter.']);
    expect(Consent::query()->count())->toBe(0);
});

it('demande une nouvelle acceptation quand un texte change de version', function () {
    app(AcceptLegalDocuments::class)->handle($this->candidat, currentUuids(), 'web');
    expect(AcceptLegalDocuments::isUpToDate($this->candidat))->toBeTrue();

    app(PublishLegalDocument::class)->handle(LegalDocumentType::Charte, 'Charte v2', 'Texte révisé.');

    expect(AcceptLegalDocuments::isUpToDate($this->candidat))->toBeFalse()
        ->and(LegalDocument::query()->where('type', 'charte')->count())->toBe(2)
        ->and(AuditLog::query()->where('action', 'legal.published')->count())->toBe(4);
});

it('exige l’acceptation des textes avant le dépôt du dossier [MOB-04]', function () {
    $candidature = Candidature::factory()->completed()->create();
    $candidature->user->consents()->delete();

    Sanctum::actingAs($candidature->user);

    $this->postJson(route('api.v1.candidature.submit'))->assertJsonValidationErrors('charte');
});

it('fait accepter les textes depuis le site, case par case [MOB-04, WEB-02]', function () {
    Candidature::factory()->for($this->candidat)->create();

    Livewire::actingAs($this->candidat)->test(Dossier::class)
        ->call('goTo', 'charte')
        ->assertSee('Transmettre uniquement ce que j')
        ->set('accepted.charte', true)
        ->call('acceptLegal')
        ->assertHasErrors('accepted')
        ->set('accepted.conditions', true)
        ->set('accepted.confidentialite', true)
        ->call('acceptLegal')
        ->assertHasNoErrors()
        ->assertSee('Textes acceptés');

    expect(Consent::query()->where('user_id', $this->candidat->id)->pluck('channel')->unique()->all())->toBe(['web']);
});

it('affiche les textes sur le site public [WEB-07]', function () {
    $this->get(route('legal.show', 'charte'))->assertOk()->assertSee('Charte du Relais')->assertSee('Version 1');
    $this->get(route('legal.show', 'confidentialite'))->assertOk()->assertSee('ARTCI');
    $this->get('/textes/inconnu')->assertNotFound();
});

it('publie une nouvelle version depuis la console, après confirmation [ADM-23]', function () {
    $admin = User::factory()->withRole(Role::Admin)->withTwoFactor()->create();

    Livewire::actingAs($admin)->test(Index::class)
        ->call('edit', 'charte')
        ->assertSet('title', 'Charte du Relais')
        ->set('body', 'Trop court.')
        ->call('askPublish')
        ->assertHasErrors('body')
        ->set('body', str_repeat('Je transmets uniquement ce que j’ai vu moi-même. ', 3))
        ->call('askPublish')
        ->assertSet('confirming', true)
        ->call('publish')
        ->assertSet('editing', false);

    $charte = LegalDocument::current(LegalDocumentType::Charte);
    expect($charte->version)->toBe(2)->and($charte->published_by)->toBe($admin->id);
});

it('réserve la page des textes juridiques à l’administration', function () {
    $this->actingAs(User::factory()->withRole(Role::Coordonnateur)->withTwoFactor()->create())
        ->get(route('console.legal'))
        ->assertForbidden();
});
