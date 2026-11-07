<?php

use App\Domain\Access\Enums\Role;
use App\Domain\AppelRelais\Actions\PublishAppel;
use App\Domain\Contribution\Actions\TransitionRelaisItem;
use App\Domain\Contribution\Models\Category;
use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Contribution\States\ComplementRequested;
use App\Domain\Contribution\States\InReview;
use App\Domain\Contribution\States\Received;
use App\Domain\Media\Models\Media;
use App\Domain\Messagerie\Models\Message;
use App\Domain\Relais\Models\RelaisProfile;
use App\Domain\Territoire\Models\Commune;
use App\Livewire\Relais\Appels;
use App\Livewire\Relais\Envoyer;
use App\Livewire\Relais\MesRelais;
use App\Livewire\Relais\Messages;
use App\Models\User;
use Database\Seeders\ListsSeeder;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(ListsSeeder::class);
    fakeSms();
    $this->storage = fakeMultipart();
    $this->commune = Commune::factory()->create(['name' => 'Yopougon']);
    $this->profile = RelaisProfile::factory()->create(['commune_id' => $this->commune->id]);
    $this->user = $this->profile->user;
});

it('prépare un brouillon et envoie un relais depuis le site, avec les questions de protection [WEB-03]', function () {
    $component = Livewire::actingAs($this->user)->test(Envoyer::class);
    $relais = RelaisItem::withoutCommuneRestriction()->where('uuid', $component->get('relaisUuid'))->sole();
    attachReadyMedia($relais);

    $component
        ->set('category', Category::query()->value('uuid'))
        ->set('title', 'Route coupée à Niangon')
        ->call('send')
        ->assertHasErrors(['hasMinors', 'peopleConsent', 'creditMode'])
        ->set('hasMinors', '0')
        ->set('peopleConsent', 'not_applicable')
        ->set('creditMode', 'named')
        ->call('send')
        ->assertRedirect(route('relais.items'));

    $relais->refresh();
    expect($relais->state)->toBeInstanceOf(Received::class)
        ->and($relais->commune_id)->toBe($this->commune->id)
        ->and($relais->device_model)->toBe('Site web');
});

it('refuse l’envoi sans média reçu', function () {
    Livewire::actingAs($this->user)->test(Envoyer::class)
        ->set('category', Category::query()->value('uuid'))->set('title', 'Sans média')
        ->set('hasMinors', '0')->set('peopleConsent', 'yes')->set('creditMode', 'anonymous')
        ->call('send')
        ->assertSet('missing', fn (array $missing) => isset($missing['medias']));
});

it('dépose un média depuis le navigateur par le même protocole que l’application [WEB-04]', function () {
    $relais = RelaisItem::withoutCommuneRestriction()->where('uuid', Livewire::actingAs($this->user)->test(Envoyer::class)->get('relaisUuid'))->sole();
    $content = random_bytes(2048);

    $media = $this->actingAs($this->user)->postJson(route('relais.medias.store', $relais), [
        'client_uuid' => (string) Str::uuid(), 'kind' => 'photo', 'mime' => 'image/jpeg', 'size' => strlen($content), 'filename' => 'photo.jpg',
    ])->assertCreated()->assertJsonPath('data.upload.part_count', 1)->json('data');

    $uploadId = Media::query()->sole()->upload_id;
    $etag = $this->storage->putPart((string) $uploadId, 1, $content);

    $this->actingAs($this->user)->getJson(route('relais.medias.show', $media['uuid']))->assertJsonCount(1, 'data.upload.uploaded_parts');
    $this->actingAs($this->user)->postJson(route('relais.medias.complete', $media['uuid']), ['parts' => [['number' => 1, 'etag' => $etag]]])
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected');

    expect(Media::query()->sole()->declared_sha256)->toBeNull();

    $intrus = RelaisProfile::factory()->create()->user;
    $this->actingAs($intrus)->getJson(route('relais.medias.show', $media['uuid']))->assertNotFound();
});

it('affiche mes relais et me laisse répondre à un complément [MOB-20, WEB-03]', function () {
    $relais = RelaisItem::factory()->create(['user_id' => $this->user->id, 'relais_profile_id' => $this->profile->id, 'commune_id' => $this->commune->id, 'title' => 'Marché inondé']);
    app(TransitionRelaisItem::class)->handle($relais, InReview::class);
    $relais->forceFill(['decision_comment' => 'Pouvez-vous préciser la rue ?'])->save();
    app(TransitionRelaisItem::class)->handle($relais, ComplementRequested::class);

    Livewire::actingAs($this->user)->test(MesRelais::class)
        ->assertSee('Marché inondé')
        ->assertSee('Pouvez-vous préciser la rue ?')
        ->set('answering', $relais->uuid)
        ->set('answer', 'Rue des Jardins.')
        ->call('respond', $relais->uuid)
        ->assertHasNoErrors();

    expect($relais->fresh()->state)->toBeInstanceOf(Received::class);
});

it('montre les appels de ma zone avec un lien pour y répondre [MOB-23]', function () {
    $redacteur = User::factory()->withTwoFactor()->withRole(Role::RedacteurEnChef)->create();
    $appel = app(PublishAppel::class)->handle($redacteur, [
        'title' => 'Rentrée scolaire', 'instructions' => 'Ambiance devant les écoles.', 'target' => 'communes',
        'deadline_at' => now()->addDay()->toDateTimeString(), 'communes' => [$this->commune->uuid],
    ]);

    Livewire::actingAs($this->user)->test(Appels::class)
        ->assertSee('Rentrée scolaire')
        ->assertSeeHtml(route('relais.send', ['appel' => $appel->uuid]));
});

it('écrit à son coordonnateur depuis le site [MOB-22]', function () {
    Livewire::actingAs($this->user)->test(Messages::class)
        ->set('body', 'Bonjour, je suis devant la mairie.')
        ->call('sendMessage')
        ->assertSee('Bonjour, je suis devant la mairie.');

    expect(Message::query()->sole()->from_staff)->toBeFalse();
});

it('réserve l’espace Relais aux Relais recrutés', function () {
    $candidat = User::factory()->withRole(Role::Candidat)->create();

    $this->actingAs($candidat)->get(route('relais.send'))->assertForbidden();
    $this->actingAs($candidat)->get(route('relais.items'))->assertForbidden();
});
