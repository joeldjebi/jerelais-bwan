<?php

use App\Domain\Access\Enums\Role;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Contribution\Actions\LockRelaisItem;
use App\Domain\Contribution\Events\RelaisQueueChanged;
use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Contribution\States\ComplementRequested;
use App\Domain\Contribution\States\InReview;
use App\Domain\Contribution\States\Received;
use App\Domain\Contribution\States\Rejected;
use App\Domain\Contribution\States\Sensitive;
use App\Domain\Contribution\States\Validated;
use App\Domain\Contribution\Support\RelaisChannels;
use App\Domain\Media\Enums\MediaStatus;
use App\Domain\Territoire\Models\Commune;
use App\Domain\Validation\Enums\RejectionTarget;
use App\Domain\Validation\Models\RejectionReason;
use App\Livewire\Console\Validation\Index;
use App\Livewire\Console\Validation\Show;
use App\Models\User;
use Database\Seeders\ListsSeeder;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(ListsSeeder::class);
    $this->sms = fakeSms();
    fakeMultipart();

    $this->yopougon = Commune::factory()->create(['name' => 'Yopougon']);
    $this->coordo = User::factory()->withTwoFactor()->coordonnateurOf($this->yopougon)->create(['name' => 'Awa Coordo']);
    $this->relais = RelaisItem::factory()->create(['commune_id' => $this->yopougon->id, 'title' => 'Inondation à Niangon']);
    attachReadyMedia($this->relais);
});

it('trie la file par urgence puis par ancienneté, dans la seule commune du coordonnateur [COO-10]', function () {
    $urgent = RelaisItem::factory()->urgent()->create(['commune_id' => $this->yopougon->id, 'title' => 'Incendie au marché', 'received_at' => now()->subMinute()]);
    $ancien = RelaisItem::factory()->create(['commune_id' => $this->yopougon->id, 'title' => 'Route abîmée', 'received_at' => now()->subHour()]);
    RelaisItem::factory()->create(['commune_id' => Commune::factory()->create()->id, 'title' => 'Ailleurs']);

    $titles = Livewire::actingAs($this->coordo)->test(Index::class)
        ->assertDontSee('Ailleurs')
        ->instance()->items->pluck('title')->all();

    expect($titles)->toBe(['Incendie au marché', 'Route abîmée', 'Inondation à Niangon']);
});

it('signale l’arrivée d’un nouveau relais urgent [COO-10]', function () {
    $component = Livewire::actingAs($this->coordo)->test(Index::class);

    RelaisItem::factory()->urgent()->create(['commune_id' => $this->yopougon->id]);

    $component->call('$refresh')->assertDispatched('urgent-relais', count: 1);
});

it('réserve le relais 10 minutes au premier coordonnateur qui l’ouvre [§8.2]', function () {
    Livewire::actingAs($this->coordo)->test(Show::class, ['relais' => $this->relais])->assertSet('lockMessage', null);

    $relais = $this->relais->fresh();
    expect($relais->state)->toBeInstanceOf(InReview::class)
        ->and($relais->locked_by)->toBe($this->coordo->id)
        ->and($relais->locked_until->diffInMinutes(now(), true))->toBeGreaterThan(9.0);

    $suppleant = User::factory()->withTwoFactor()->coordonnateurOf($this->yopougon)->create();
    Livewire::actingAs($suppleant)->test(Show::class, ['relais' => $this->relais])
        ->assertSet('lockMessage', fn (?string $message) => str_contains((string) $message, 'Awa Coordo'))
        ->assertDontSee('Votre décision');
});

it('libère le verrou expiré pour un autre coordonnateur', function () {
    app(LockRelaisItem::class)->handle($this->relais, $this->coordo);
    $this->travel(11)->minutes();

    $suppleant = User::factory()->withTwoFactor()->coordonnateurOf($this->yopougon)->create();
    Livewire::actingAs($suppleant)->test(Show::class, ['relais' => $this->relais])->assertSet('lockMessage', null);

    expect($this->relais->fresh()->locked_by)->toBe($suppleant->id);
});

it('valide avec corrections, trace l’avant/après et prévient le Relais [COO-14]', function () {
    Livewire::actingAs($this->coordo)->test(Show::class, ['relais' => $this->relais])
        ->call('open', 'validate')
        ->set('corrections.title', 'Inondation au carrefour de Niangon')
        ->call('decide', 'validate')
        ->assertHasNoErrors();

    $relais = $this->relais->fresh();
    expect($relais->state)->toBeInstanceOf(Validated::class)
        ->and($relais->title)->toBe('Inondation au carrefour de Niangon')
        ->and($relais->locked_by)->toBeNull()
        ->and(AuditLog::query()->where('action', 'relais.validated')->sole()->payload['corrections']['title'])->toEqualCanonicalizing(['from' => 'Inondation à Niangon', 'to' => 'Inondation au carrefour de Niangon'])
        ->and($this->sms->sent[0]['message'])->toContain('est validé');
});

it('exige l’indication de floutage pour valider un relais avec des mineurs [COO-16]', function () {
    $mineurs = RelaisItem::factory()->withMinors()->create(['commune_id' => $this->yopougon->id]);
    attachReadyMedia($mineurs);

    $component = Livewire::actingAs($this->coordo)->test(Show::class, ['relais' => $mineurs])
        ->call('open', 'validate')
        ->call('decide', 'validate')
        ->assertHasErrors('decision');

    expect($mineurs->fresh()->state)->toBeInstanceOf(InReview::class);

    $component->set('blur', '1')->call('decide', 'validate')->assertHasNoErrors();
    expect($mineurs->fresh()->blur_required)->toBeTrue();
});

it('rejette avec un motif de la liste des relais [COO-14, COO-15]', function () {
    $reason = RejectionReason::query()->activeFor(RejectionTarget::Relais)->firstOrFail();

    Livewire::actingAs($this->coordo)->test(Show::class, ['relais' => $this->relais])
        ->call('open', 'reject')
        ->call('decide', 'reject')
        ->assertHasErrors('decision')
        ->set('reason', $reason->uuid)
        ->call('decide', 'reject')
        ->assertHasNoErrors();

    expect($this->relais->fresh())->state->toBeInstanceOf(Rejected::class)->rejection_reason_id->toBe($reason->id);
});

it('demande un complément ou transmet à la rédaction, avec un commentaire obligatoire [COO-14]', function () {
    Livewire::actingAs($this->coordo)->test(Show::class, ['relais' => $this->relais])
        ->call('open', 'complement')
        ->call('decide', 'complement')
        ->assertHasErrors('decision')
        ->set('comment', 'Pouvez-vous filmer le panneau de la rue ?')
        ->call('decide', 'complement');

    expect($this->relais->fresh()->state)->toBeInstanceOf(ComplementRequested::class);

    $autre = RelaisItem::factory()->create(['commune_id' => $this->yopougon->id]);
    Livewire::actingAs($this->coordo)->test(Show::class, ['relais' => $autre])
        ->call('open', 'sensitive')
        ->set('comment', 'Victimes visibles, à arbitrer.')
        ->call('decide', 'sensitive');

    expect($autre->fresh()->state)->toBeInstanceOf(Sensitive::class);
});

it('enregistre une note interne invisible du Relais [COO-18]', function () {
    Livewire::actingAs($this->coordo)->test(Show::class, ['relais' => $this->relais])
        ->set('note', 'Appelé le Relais : confirme être sur place.')
        ->call('saveNote');

    expect($this->relais->fresh()->internal_note)->toBe('Appelé le Relais : confirme être sur place.');
    $this->assertArrayNotHasKey('internal_note', $this->relais->fresh()->toArray());
});

it('remet le relais dans la file quand le coordonnateur le libère sans décider', function () {
    Livewire::actingAs($this->coordo)->test(Show::class, ['relais' => $this->relais])->call('release');

    expect($this->relais->fresh())->state->toBeInstanceOf(Received::class)->locked_by->toBeNull();
});

it('refuse l’accès au relais d’une autre commune [COO-01, §8.2]', function () {
    $ailleurs = RelaisItem::factory()->create(['commune_id' => Commune::factory()->create()->id]);

    $this->actingAs($this->coordo)->get(route('console.validation.show', $ailleurs))->assertNotFound();
    $this->actingAs($this->coordo)->get(route('console.medias.show', attachReadyMedia($ailleurs)))->assertNotFound();
});

it('laisse la rédaction consulter sans pouvoir valider [Annexe A]', function () {
    $journaliste = User::factory()->withTwoFactor()->withRole(Role::Journaliste)->create();

    Livewire::actingAs($journaliste)->test(Show::class, ['relais' => $this->relais])
        ->assertDontSee('Votre décision');

    expect($this->relais->fresh()->state)->toBeInstanceOf(Received::class);
});

it('signale un média identique déjà envoyé dans un autre relais [COO-13]', function () {
    $media = $this->relais->medias()->sole();
    $media->forceFill(['sha256' => str_repeat('b', 64), 'phash' => 'ffff0000ffff0000'])->save();

    $autre = RelaisItem::factory()->create(['commune_id' => Commune::factory()->create(['name' => 'Cocody'])->id, 'title' => 'Même scène']);
    attachReadyMedia($autre)->forceFill(['sha256' => str_repeat('c', 64), 'phash' => 'ffff0000ffff0003'])->save();

    Livewire::actingAs($this->coordo)->test(Show::class, ['relais' => $this->relais])
        ->assertSee('Média très proche')
        ->assertSee('Cocody')
        ->assertSee('Relais d’une autre commune')
        ->assertDontSee('Même scène');
});

it('diffuse chaque changement de statut en temps réel, sans donnée personnelle [§8.2]', function () {
    Event::fake([RelaisQueueChanged::class]);

    Livewire::actingAs($this->coordo)->test(Show::class, ['relais' => $this->relais]);

    Event::assertDispatched(RelaisQueueChanged::class, function (RelaisQueueChanged $event): bool {
        $channels = array_map(fn ($channel) => $channel->name, $event->broadcastOn());

        return $event->state === 'in_review'
            && in_array('private-communes.'.$this->yopougon->id.'.relais', $channels, true)
            && ! property_exists($event, 'title');
    });
});

it('n’autorise le canal temps réel d’une commune qu’à ses coordonnateurs et à l’équipe nationale', function () {
    $autre = User::factory()->withTwoFactor()->coordonnateurOf(Commune::factory()->create())->create();
    $admin = User::factory()->withTwoFactor()->withRole(Role::Admin)->create();

    expect(RelaisChannels::commune($this->coordo, $this->yopougon->id))->toBeTrue()
        ->and(RelaisChannels::commune($autre, $this->yopougon->id))->toBeFalse()
        ->and(RelaisChannels::console($this->coordo))->toBeFalse()
        ->and(RelaisChannels::console($admin))->toBeTrue()
        ->and(RelaisChannels::commune(User::factory()->withRole(Role::Relais)->create(), $this->yopougon->id))->toBeFalse();
});

it('ne valide pas un relais dont un média est encore en cours de contrôle', function () {
    $this->relais->medias()->update(['status' => MediaStatus::Processing->value]);

    Livewire::actingAs($this->coordo)->test(Show::class, ['relais' => $this->relais])
        ->assertSee('Médias en cours de contrôle');
});

it('garde les médias et leurs vignettes chargés entre deux actions (vidéo comprise)', function () {
    attachReadyMedia($this->relais, 'video');

    Livewire::actingAs($this->coordo)->test(Show::class, ['relais' => $this->relais])
        ->call('open', 'validate')
        ->assertOk()
        ->call('decide', 'validate')
        ->assertHasNoErrors();
});

it('affiche le lecteur intégré : zoom photo, image par image et vitesse réglable [COO-11]', function () {
    attachReadyMedia($this->relais, 'video');

    Livewire::actingAs($this->coordo)->test(Show::class, ['relais' => $this->relais])
        ->assertSeeHtml('cursor-zoom-in')
        ->assertSee('+1 img')
        ->assertSee('2×');

    $this->actingAs($this->coordo)->get(route('console.medias.show', $this->relais->medias()->first()))->assertRedirect();
});

it('alerte sur un média importé, une commune déclarée et un GPS imprécis [COO-12]', function () {
    $this->relais->forceFill(['routed_by' => 'declared', 'location_accuracy_m' => 850])->save();
    $this->relais->medias()->update(['is_imported' => true]);

    Livewire::actingAs($this->coordo)->test(Show::class, ['relais' => $this->relais])
        ->assertSee('importé de la galerie')
        ->assertSee('Commune déclarée par le Relais')
        ->assertSee('Position GPS imprécise (± 850 m)');
});

it('ne sert jamais un média encore en cours de contrôle antivirus (revue sécurité)', function () {
    $media = attachReadyMedia($this->relais);
    $media->forceFill(['status' => MediaStatus::Processing])->save();

    $this->actingAs($this->coordo)->get(route('console.medias.show', $media))->assertNotFound();
});
