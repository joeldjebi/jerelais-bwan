<?php

use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Messagerie\Actions\SendMessage;
use App\Domain\Messagerie\Models\Conversation;
use App\Domain\Messagerie\Models\Message;
use App\Domain\Relais\Models\RelaisProfile;
use App\Domain\Territoire\Models\Commune;
use App\Livewire\Console\Messagerie\Index as Messagerie;
use App\Livewire\Console\Validation\Show;
use App\Models\User;
use Database\Seeders\ListsSeeder;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(ListsSeeder::class);
    $this->sms = fakeSms();
    $this->yopougon = Commune::factory()->create(['name' => 'Yopougon']);
    $this->profile = RelaisProfile::factory()->create(['commune_id' => $this->yopougon->id, 'display_name' => 'Awa K.']);
    $this->coordo = User::factory()->withTwoFactor()->coordonnateurOf($this->yopougon)->create(['name' => 'Coordo Yop']);
});

it('laisse le Relais écrire à son coordonnateur et suivre la conversation [MOB-22]', function () {
    Sanctum::actingAs($this->profile->user);

    $uuid = $this->postJson(route('api.v1.conversations.store'), ['body' => 'Bonjour, il y a un incendie à Niangon.'])
        ->assertCreated()
        ->json('data.uuid');

    $this->postJson(route('api.v1.conversations.reply', $uuid), ['body' => 'Je vous envoie la vidéo.'])->assertCreated();

    $this->getJson(route('api.v1.conversations.messages', $uuid))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.author', 'Vous');

    expect(Conversation::query()->count())->toBe(1);
});

it('ouvre une conversation par relais, et une seule conversation générale', function () {
    $relais = RelaisItem::factory()->create(['user_id' => $this->profile->user_id, 'relais_profile_id' => $this->profile->id, 'commune_id' => $this->yopougon->id]);
    $send = app(SendMessage::class);

    expect($send->conversationFor($this->profile)->id)->toBe($send->conversationFor($this->profile)->id)
        ->and($send->conversationFor($this->profile, $relais)->id)->not->toBe($send->conversationFor($this->profile)->id);

    $autre = RelaisItem::factory()->create(['commune_id' => $this->yopougon->id]);
    Sanctum::actingAs($this->profile->user);
    $this->postJson(route('api.v1.conversations.store'), ['relais' => $autre->uuid, 'body' => 'Test'])->assertJsonValidationErrors('relais');
});

it('répond depuis la console et prévient le Relais par SMS au plus une fois par heure [COO-20]', function () {
    $conversation = app(SendMessage::class)->conversationFor($this->profile);
    app(SendMessage::class)->send($conversation, $this->profile->user, 'Bonjour !');

    $component = Livewire::actingAs($this->coordo)->test(Messagerie::class)
        ->assertSee('Awa K.')
        ->call('select', $conversation->uuid)
        ->set('body', 'Merci, pouvez-vous préciser la rue ?')
        ->call('reply')
        ->set('body', 'Et l’heure ?')
        ->call('reply');

    expect(Message::query()->where('from_staff', true)->count())->toBe(2)
        ->and($this->sms->sent)->toHaveCount(1)
        ->and($this->sms->sent[0]['message'])->toContain('votre coordonnateur vous a écrit');

    Sanctum::actingAs($this->profile->user);
    $this->getJson(route('api.v1.conversations.index'))->assertJsonPath('data.0.unread', true);
    $this->getJson(route('api.v1.conversations.messages', $conversation->uuid));
    $this->getJson(route('api.v1.conversations.index'))->assertJsonPath('data.0.unread', false);
});

it('envoie un message groupé à tous les Relais actifs de la commune [COO-20]', function () {
    RelaisProfile::factory()->count(2)->create(['commune_id' => $this->yopougon->id]);
    RelaisProfile::factory()->suspended()->create(['commune_id' => $this->yopougon->id]);
    RelaisProfile::factory()->create(['commune_id' => Commune::factory()->create()->id]);

    Livewire::actingAs($this->coordo)->test(Messagerie::class)
        ->set('broadcastCommune', $this->yopougon->uuid)
        ->set('broadcastBody', 'Réunion des Relais samedi à 10 h à la mairie.')
        ->call('sendBroadcast')
        ->assertHasNoErrors();

    expect(Message::query()->whereNotNull('broadcast_uuid')->count())->toBe(3)
        ->and(Message::query()->distinct('broadcast_uuid')->count('broadcast_uuid'))->toBe(1);
});

it('ne montre au coordonnateur que les conversations de sa commune', function () {
    $ailleurs = RelaisProfile::factory()->create(['commune_id' => Commune::factory()->create()->id, 'display_name' => 'Koffi Y.']);
    $send = app(SendMessage::class);
    $send->send($send->conversationFor($this->profile), $this->profile->user, 'Chez nous');
    $send->send($send->conversationFor($ailleurs), $ailleurs->user, 'Ailleurs');

    Livewire::actingAs($this->coordo)->test(Messagerie::class)->assertSee('Awa K.')->assertDontSee('Koffi Y.');

    Sanctum::actingAs($this->profile->user);
    $this->getJson(route('api.v1.conversations.messages', Conversation::withoutCommuneRestriction()->where('relais_profile_id', $ailleurs->id)->value('uuid')))->assertNotFound();
});

it('ouvre la conversation d’un relais depuis sa fiche', function () {
    $relais = RelaisItem::factory()->create(['user_id' => $this->profile->user_id, 'relais_profile_id' => $this->profile->id, 'commune_id' => $this->yopougon->id]);

    Livewire::actingAs($this->coordo)->test(Show::class, ['relais' => $relais])
        ->call('writeToRelais')
        ->assertRedirectContains('/console/messagerie?c=');

    expect(Conversation::query()->sole()->relais_item_id)->toBe($relais->id);
});
