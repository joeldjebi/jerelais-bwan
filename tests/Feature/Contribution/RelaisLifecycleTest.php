<?php

use App\Domain\Access\Enums\Role;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Contribution\Actions\EscalateOverdueRelais;
use App\Domain\Contribution\Actions\LockRelaisItem;
use App\Domain\Contribution\Actions\ReleaseExpiredLocks;
use App\Domain\Contribution\Actions\TransitionRelaisItem;
use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Contribution\Notifications\RelaisOverdue;
use App\Domain\Contribution\States\ComplementRequested;
use App\Domain\Contribution\States\InReview;
use App\Domain\Contribution\States\Received;
use App\Domain\Contribution\States\Validated;
use App\Domain\Media\Actions\PurgeAbandonedUploads;
use App\Domain\Media\Enums\MediaStatus;
use App\Domain\Media\Models\Media;
use App\Domain\Territoire\Models\Commune;
use App\Models\User;
use Database\Seeders\ListsSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(ListsSeeder::class);
    fakeSms();
    Notification::fake();

    $this->commune = Commune::factory()->create(['name' => 'Yopougon']);
    $this->titulaire = User::factory()->withTwoFactor()->coordonnateurOf($this->commune)->create();
    $this->suppleant = User::factory()->withTwoFactor()->coordonnateurOf($this->commune, 'suppleant')->create();
    $this->superviseur = User::factory()->withTwoFactor()->withRole(Role::Superviseur)->create();
    $this->superviseur->regions()->attach($this->commune->region_id);
    $this->admin = User::factory()->withTwoFactor()->withRole(Role::Admin)->create();
});

it('escalade un relais urgent en retard au suppléant, puis au superviseur, puis à l’administration [COO-17]', function () {
    $relais = RelaisItem::factory()->urgent()->create(['commune_id' => $this->commune->id, 'sla_due_at' => now()->addMinutes(30)]);
    $escalate = app(EscalateOverdueRelais::class);

    expect($escalate->handle())->toBe(0);

    $this->travel(31)->minutes();
    expect($escalate->handle())->toBe(1);
    Notification::assertSentTo($this->suppleant, RelaisOverdue::class, fn (RelaisOverdue $notification) => $notification->level === 1);
    Notification::assertNotSentTo($this->superviseur, RelaisOverdue::class);

    expect($escalate->handle())->toBe(0);

    $this->travel(30)->minutes();
    $escalate->handle();
    Notification::assertSentTo($this->superviseur, RelaisOverdue::class);

    $this->travel(30)->minutes();
    $escalate->handle();
    Notification::assertSentTo($this->admin, RelaisOverdue::class);

    expect($relais->fresh()->escalation_level)->toBe(3)
        ->and(AuditLog::query()->where('action', 'relais.escalated')->count())->toBe(3);
});

it('passe au niveau suivant quand la commune n’a pas de suppléant', function () {
    $sansSuppleant = Commune::factory()->create();
    $sansSuppleant->update(['region_id' => $this->commune->region_id]);
    $relais = RelaisItem::factory()->create(['commune_id' => $sansSuppleant->id, 'sla_due_at' => now()->subMinute()]);

    app(EscalateOverdueRelais::class)->handle();

    expect($relais->fresh()->escalation_level)->toBe(2);
    Notification::assertSentTo($this->superviseur, RelaisOverdue::class);
});

it('n’escalade pas un relais déjà traité', function () {
    $relais = RelaisItem::factory()->create(['commune_id' => $this->commune->id, 'sla_due_at' => now()->subHour()]);
    app(TransitionRelaisItem::class)->handle($relais, InReview::class);
    $relais->state->transitionTo(Validated::class);

    expect(app(EscalateOverdueRelais::class)->handle())->toBe(0);
});

it('remet dans la file un relais dont le verrou a expiré sans décision [§8.2]', function () {
    $relais = RelaisItem::factory()->create(['commune_id' => $this->commune->id]);
    app(LockRelaisItem::class)->handle($relais, $this->titulaire);

    $this->travel(11)->minutes();
    expect(app(ReleaseExpiredLocks::class)->handle())->toBe(1);

    expect($relais->fresh())->state->toBeInstanceOf(Received::class)->locked_by->toBeNull();
});

it('laisse le Relais répondre à un complément, ce qui remet le relais dans la file avec un nouveau délai [COO-14]', function () {
    $relais = RelaisItem::factory()->create(['commune_id' => $this->commune->id, 'sla_due_at' => now()->subHour(), 'escalation_level' => 2]);
    app(TransitionRelaisItem::class)->handle($relais, InReview::class);
    app(TransitionRelaisItem::class)->handle($relais, ComplementRequested::class, $this->titulaire, 'Pouvez-vous préciser la rue ?');
    Sanctum::actingAs($relais->user);

    $this->postJson(route('api.v1.relais.complement', $relais), [])->assertJsonValidationErrors('message');

    $this->postJson(route('api.v1.relais.complement', $relais), ['message' => 'Rue des Jardins, devant la pharmacie.'])
        ->assertOk()
        ->assertJsonPath('data.status', 'received');

    $relais->refresh();
    expect($relais->escalation_level)->toBe(0)
        ->and($relais->sla_due_at->isFuture())->toBeTrue()
        ->and($relais->statusHistory->last()->reason)->toBe('Rue des Jardins, devant la pharmacie.');

    $this->postJson(route('api.v1.relais.complement', $relais), ['message' => 'Encore'])->assertConflict();
});

it('accepte un média ajouté en complément', function () {
    $storage = fakeMultipart();
    $relais = RelaisItem::factory()->create(['commune_id' => $this->commune->id]);
    app(TransitionRelaisItem::class)->handle($relais, InReview::class);
    app(TransitionRelaisItem::class)->handle($relais, ComplementRequested::class, $this->titulaire, 'Une photo de plus ?');
    Sanctum::actingAs($relais->user);
    $this->travel(1)->seconds();

    $this->postJson(route('api.v1.relais.medias.store', $relais), [
        'client_uuid' => (string) Str::uuid(), 'kind' => 'photo', 'mime' => 'image/jpeg', 'size' => 10, 'sha256' => str_repeat('a', 64),
    ])->assertCreated();

    $this->postJson(route('api.v1.relais.complement', $relais))->assertJsonValidationErrors(['medias' => 'L’envoi d’un média n’est pas terminé.']);

    Media::query()->latest('id')->first()->forceFill(['status' => MediaStatus::Ready])->save();
    $this->postJson(route('api.v1.relais.complement', $relais))->assertOk();
});

it('annule les envois de médias abandonnés depuis plus de 48 heures', function () {
    $storage = fakeMultipart();
    $relais = RelaisItem::factory()->create(['commune_id' => $this->commune->id]);
    $media = attachReadyMedia($relais);
    $media->forceFill(['status' => MediaStatus::Pending, 'upload_id' => $storage->initiate($media->path, 'image/jpeg')])->save();

    expect(app(PurgeAbandonedUploads::class)->handle())->toBe(0);

    $this->travel(49)->hours();
    expect(app(PurgeAbandonedUploads::class)->handle())->toBe(1)
        ->and($media->fresh())->status->toBe(MediaStatus::Rejected)->rejection->toBe('abandoned')
        ->and($storage->parts)->toBe([]);
});

it('planifie l’escalade, la libération des verrous et la purge des envois', function () {
    $this->artisan('schedule:list')
        ->expectsOutputToContain('relais:escalate')
        ->expectsOutputToContain('relais:release-locks')
        ->expectsOutputToContain('medias:purge-abandoned');
});
