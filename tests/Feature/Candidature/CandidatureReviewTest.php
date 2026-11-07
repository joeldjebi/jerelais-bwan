<?php

use App\Domain\Access\Enums\Role;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Auth\Models\Device;
use App\Domain\Candidature\Enums\CandidatureStatus;
use App\Domain\Candidature\Models\Candidature;
use App\Domain\Candidature\Models\CandidatureDocument;
use App\Domain\Territoire\Models\Commune;
use App\Domain\Validation\Enums\RejectionTarget;
use App\Domain\Validation\Models\RejectionReason;
use App\Livewire\Console\Candidatures\Index;
use App\Livewire\Console\Candidatures\Show;
use App\Models\User;
use Database\Seeders\ListsSeeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(ListsSeeder::class);
    $this->sms = fakeSms();
    Storage::fake('identity');

    $this->yopougon = Commune::factory()->create(['name' => 'Yopougon']);
    $this->cocody = Commune::factory()->create(['name' => 'Cocody']);
    $this->coordo = User::factory()->withTwoFactor()->coordonnateurOf($this->yopougon)->create();

    $this->dossier = Candidature::factory()->submitted()->create(['commune_id' => $this->yopougon->id, 'last_name' => 'Kouassi', 'first_names' => 'Awa']);
    $this->ailleurs = Candidature::factory()->submitted()->create(['commune_id' => $this->cocody->id, 'last_name' => 'Yao', 'first_names' => 'Koffi']);
});

it('montre au coordonnateur la file de sa seule commune, du plus ancien au plus récent [COO-01]', function () {
    Livewire::actingAs($this->coordo)->test(Index::class)
        ->assertSee('Awa Kouassi')
        ->assertDontSee('Koffi Yao')
        ->assertSet('counts', fn (array $counts) => $counts['a-traiter'] === 1);

    $this->actingAs($this->coordo)->get(route('console.candidatures'))->assertOk();
});

it('refuse l’accès au dossier d’une autre commune (aucune fuite) [COO-01]', function () {
    $this->actingAs($this->coordo)->get(route('console.candidatures.show', $this->ailleurs))->assertNotFound();
    $this->actingAs($this->coordo)->get(route('console.candidatures.show', $this->dossier))->assertOk()->assertSee('Awa Kouassi');
});

it('réserve les candidatures aux rôles habilités [Annexe A]', function () {
    $this->actingAs(User::factory()->withTwoFactor()->withRole(Role::Journaliste)->create())
        ->get(route('console.candidatures'))
        ->assertForbidden();
});

it('passe le dossier en cours d’examen à son ouverture [MOB-06]', function () {
    Livewire::actingAs($this->coordo)->test(Show::class, ['candidature' => $this->dossier]);

    expect($this->dossier->fresh()->status)->toBe(CandidatureStatus::UnderReview)
        ->and(AuditLog::query()->where('action', 'candidature.review_started')->exists())->toBeTrue();
});

it('accepte un candidat : il devient Relais et est prévenu par SMS [COO-02]', function () {
    Livewire::actingAs($this->coordo)->test(Show::class, ['candidature' => $this->dossier])
        ->call('accept')
        ->assertHasNoErrors();

    $dossier = $this->dossier->fresh();
    expect($dossier->status)->toBe(CandidatureStatus::Accepted)
        ->and($dossier->decided_by)->toBe($this->coordo->id)
        ->and($dossier->user->hasRole(Role::Relais->value))->toBeTrue()
        ->and($dossier->user->hasRole(Role::Candidat->value))->toBeFalse()
        ->and($this->sms->sent)->toHaveCount(1)
        ->and($this->sms->sent[0]['message'])->toContain('acceptée')
        ->and(AuditLog::query()->where('action', 'candidature.accepted')->sole()->commune_id)->toBe($this->yopougon->id);
});

it('exige un motif de la liste pour refuser, sans le divulguer par SMS [COO-02]', function () {
    $reason = RejectionReason::query()->activeFor(RejectionTarget::Candidature)->firstOrFail();

    Livewire::actingAs($this->coordo)->test(Show::class, ['candidature' => $this->dossier])
        ->call('openReject')
        ->call('reject')
        ->assertHasErrors('reason')
        ->set('reason', $reason->uuid)
        ->set('comment', 'Pièce d’identité illisible.')
        ->call('reject')
        ->assertHasNoErrors();

    $dossier = $this->dossier->fresh();
    expect($dossier->status)->toBe(CandidatureStatus::Rejected)
        ->and($dossier->rejection_reason_id)->toBe($reason->id)
        ->and($dossier->decision_comment)->toBe('Pièce d’identité illisible.')
        ->and($this->sms->sent[0]['message'])->not->toContain('illisible');
});

it('refuse un motif réservé aux relais', function () {
    $relaisReason = RejectionReason::query()->activeFor(RejectionTarget::Relais)->firstOrFail();

    Livewire::actingAs($this->coordo)->test(Show::class, ['candidature' => $this->dossier])
        ->set('reason', $relaisReason->uuid)
        ->call('reject')
        ->assertHasErrors('reason');

    expect($this->dossier->fresh()->status)->toBe(CandidatureStatus::UnderReview);
});

it('demande un complément avec un message, ce qui rouvre le dossier au candidat [COO-02]', function () {
    Livewire::actingAs($this->coordo)->test(Show::class, ['candidature' => $this->dossier])
        ->call('requestComplement')
        ->assertHasErrors('comment')
        ->set('comment', 'Merci de reprendre la photo du verso.')
        ->call('requestComplement')
        ->assertHasNoErrors();

    $dossier = $this->dossier->fresh();
    expect($dossier->status)->toBe(CandidatureStatus::ComplementRequested)
        ->and($dossier->status->isEditable())->toBeTrue();
});

it('met un dossier en attente sans prévenir le candidat', function () {
    Livewire::actingAs($this->coordo)->test(Show::class, ['candidature' => $this->dossier])->call('hold');

    expect($this->dossier->fresh()->status)->toBe(CandidatureStatus::OnHold)
        ->and($this->sms->sent)->toBe([]);
});

it('ne décide pas deux fois le même dossier', function () {
    $this->dossier->forceFill(['status' => CandidatureStatus::Accepted])->save();

    Livewire::actingAs($this->coordo)->test(Show::class, ['candidature' => $this->dossier])
        ->call('hold')
        ->assertForbidden();
});

it('sert les pièces par lien signé et trace chaque consultation [§9.4]', function () {
    $document = $this->dossier->documents()->firstOrFail();
    Storage::disk('identity')->put($document->path, 'image');
    $signed = URL::temporarySignedRoute('console.candidatures.document', now()->addMinutes(5), $document);

    $this->actingAs($this->coordo)->get(route('console.candidatures.document', $document))->assertForbidden();
    $this->actingAs($this->coordo)->get($signed)->assertOk();

    expect(AuditLog::query()->where('action', 'candidature.document.viewed')->sole()->payload['type'])->toBe($document->type->value);

    $autre = User::factory()->withTwoFactor()->coordonnateurOf($this->cocody)->create();
    $this->actingAs($autre)->get($signed)->assertNotFound();

    // Le superviseur de la région voit le dossier, mais pas les pièces d'identité (Annexe A).
    $superviseur = User::factory()->withTwoFactor()->withRole(Role::Superviseur)->create();
    $superviseur->regions()->attach($this->yopougon->region_id);
    $this->actingAs($superviseur)->get(route('console.candidatures.show', $this->dossier))->assertOk()->assertSee('Accès restreint');
    $this->actingAs($superviseur)->get($signed)->assertForbidden();
});

it('signale les doublons : même pièce, même identité, même appareil [COO-03]', function () {
    $hash = $this->dossier->documents()->firstOrFail()->sha256;
    CandidatureDocument::query()->whereKey($this->ailleurs->documents()->firstOrFail()->id)->update(['sha256' => $hash]);

    $memeIdentite = Candidature::factory()->submitted()->create([
        'commune_id' => $this->yopougon->id, 'last_name' => 'KOUASSI', 'first_names' => 'awa', 'birth_date' => $this->dossier->birth_date,
    ]);

    $device = new Device(['name' => 'Tecno', 'platform' => 'android', 'fcm_token' => 'jeton-commun']);
    $device->user()->associate($this->dossier->user)->save();
    $autreDossier = Candidature::factory()->submitted()->create(['commune_id' => $this->cocody->id]);
    $copie = new Device(['name' => 'Tecno', 'platform' => 'android', 'fcm_token' => 'jeton-commun']);
    $copie->user()->associate($autreDossier->user)->save();

    $component = Livewire::actingAs($this->coordo)->test(Show::class, ['candidature' => $this->dossier]);
    $reasons = collect($component->instance()->duplicates)->mapWithKeys(fn ($match) => [$match->candidature->id => $match->reason]);

    expect($reasons->all())->toBe([
        $this->ailleurs->id => 'document',
        $memeIdentite->id => 'identity',
        $autreDossier->id => 'device',
    ]);
    $component->assertSee('Même pièce d’identité')->assertSee('Cocody');
});
