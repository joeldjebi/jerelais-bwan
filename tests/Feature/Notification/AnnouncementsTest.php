<?php

use App\Domain\Access\Enums\Role;
use App\Domain\Auth\Models\Device;
use App\Domain\Notification\Enums\AnnouncementStatus;
use App\Domain\Notification\Models\Announcement;
use App\Domain\Notification\Models\AnnouncementRecipient;
use App\Domain\Relais\Enums\RelaisStatus;
use App\Domain\Relais\Models\RelaisProfile;
use App\Domain\Territoire\Models\Commune;
use App\Livewire\Console\Announcements\Index as AnnouncementsPage;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;

beforeEach(function () {
    $this->sms = fakeSms();
    $this->push = fakePush();
    $this->admin = User::factory()->withTwoFactor()->withRole(Role::Admin)->create();
    $this->cocody = Commune::factory()->create(['name' => 'Cocody']);
    $this->yopougon = Commune::factory()->create(['name' => 'Yopougon']);

    // Awa (Cocody) a l'application ; Koffi (Yopougon) non ; Ali est suspendu.
    $this->awa = RelaisProfile::factory()->create(['commune_id' => $this->cocody->id, 'display_name' => 'Awa T.']);
    Device::query()->create(['user_id' => $this->awa->user_id, 'name' => 'iPhone', 'platform' => 'ios', 'fcm_token' => 'jeton-awa']);
    $this->koffi = RelaisProfile::factory()->create(['commune_id' => $this->yopougon->id, 'display_name' => 'Koffi K.']);
    RelaisProfile::factory()->create(['commune_id' => $this->cocody->id, 'status' => RelaisStatus::Suspended]);
});

it('envoie une annonce à tous les Relais actifs : push à ceux qui ont l’application, SMS de secours aux autres [MOB-21]', function () {
    Livewire::actingAs($this->admin)->test(AnnouncementsPage::class)
        ->assertSee('2 Relais')
        ->set('title', 'Inondations')
        ->set('body', 'Filmez les quartiers touchés, en restant à l’abri.')
        ->set('screen', 'appels')
        ->set('withSms', true)
        ->call('review')
        ->assertHasNoErrors()
        ->assertSet('confirming', true)
        ->call('send')
        ->assertSet('confirming', false);

    $announcement = Announcement::query()->sole();

    expect($announcement->status)->toBe(AnnouncementStatus::Sent)
        ->and($announcement->recipients_count)->toBe(2)
        ->and($announcement->push_count)->toBe(1)
        ->and($announcement->sms_count)->toBe(1)
        ->and($this->push->sent)->toHaveCount(1)
        ->and($this->push->sent[0]['token'])->toBe('jeton-awa')
        ->and($this->push->sent[0]['message']->data)->toBe(['type' => 'announcement', 'uuid' => $announcement->uuid, 'screen' => 'appels'])
        ->and($this->sms->sent)->toHaveCount(1)
        ->and($this->sms->sent[0]['message'])->toContain('Inondations');
});

it('cible une commune et n’envoie aucun SMS si le secours n’est pas demandé [MOB-21]', function () {
    Livewire::actingAs($this->admin)->test(AnnouncementsPage::class)
        ->set('title', 'Réunion')
        ->set('body', 'Réunion des Relais de Yopougon samedi.')
        ->set('audience', 'communes')
        ->call('review')
        ->assertHasErrors('communeIds')
        ->set('communeIds', [$this->yopougon->uuid])
        ->assertSee('1 Relais')
        ->call('review')
        ->call('send');

    $announcement = Announcement::query()->sole();

    expect($announcement->recipients_count)->toBe(1)
        ->and($announcement->unreached_count)->toBe(1)
        ->and($this->push->sent)->toBeEmpty()
        ->and($this->sms->sent)->toBeEmpty()
        ->and(AnnouncementRecipient::query()->sole()->user_id)->toBe($this->koffi->user_id);
});

it('écrit à un Relais précis, trouvé par son nom [MOB-21]', function () {
    Livewire::actingAs($this->admin)->test(AnnouncementsPage::class)
        ->set('audience', 'relais')
        ->set('relaisSearch', 'Awa')
        ->assertSee('Awa T.')
        ->call('chooseRelais', $this->awa->user->uuid)
        ->set('title', 'Bravo')
        ->set('body', 'Votre relais d’hier était excellent.')
        ->call('review')
        ->call('send');

    expect(Announcement::query()->sole()->recipients_count)->toBe(1)
        ->and($this->push->sent)->toHaveCount(1);
});

it('programme une annonce, l’envoie à l’heure dite et permet de l’annuler avant [MOB-21]', function () {
    Livewire::actingAs($this->admin)->test(AnnouncementsPage::class)
        ->set('title', 'Rappel')
        ->set('body', 'Pensez à compléter votre profil.')
        ->set('audience', 'identity_pending')
        ->set('when', 'later')
        ->set('scheduledAt', now()->addHour()->format('Y-m-d\TH:i'))
        ->call('review')
        ->call('send');

    $announcement = Announcement::query()->sole();
    expect($announcement->status)->toBe(AnnouncementStatus::Scheduled)->and($this->push->sent)->toBeEmpty();

    $this->artisan('announcements:send-due')->assertSuccessful();
    expect($announcement->refresh()->status)->toBe(AnnouncementStatus::Scheduled);

    $this->travel(2)->hours();
    $this->artisan('announcements:send-due')->assertSuccessful();
    expect($announcement->refresh()->status)->toBe(AnnouncementStatus::Sent)->and($announcement->recipients_count)->toBe(2);

    $other = Announcement::query()->create(['title' => 'Plus tard', 'body' => 'Texte', 'screen' => 'home', 'audience' => 'all', 'status' => 'scheduled', 'scheduled_at' => now()->addDay()]);
    Livewire::actingAs($this->admin)->test(AnnouncementsPage::class)->call('cancel', $other->uuid);
    expect($other->refresh()->status)->toBe(AnnouncementStatus::Cancelled);
});

it('garde l’annonce dans la boîte de réception de l’application, avec lecture [MOB-21]', function () {
    Livewire::actingAs($this->admin)->test(AnnouncementsPage::class)
        ->set('title', 'Bienvenue')->set('body', 'Merci de rejoindre le réseau.')
        ->call('review')->call('send');
    $announcement = Announcement::query()->sole();

    Sanctum::actingAs($this->koffi->user);

    $this->getJson(route('api.v1.announcements.index'))
        ->assertOk()
        ->assertJsonPath('data.0.uuid', $announcement->uuid)
        ->assertJsonPath('data.0.title', 'Bienvenue')
        ->assertJsonPath('data.0.is_read', false)
        ->assertJsonPath('meta.unread_count', 1);

    $this->postJson(route('api.v1.announcements.read', $announcement->uuid))->assertNoContent();
    $this->getJson(route('api.v1.announcements.index'))->assertJsonPath('data.0.is_read', true)->assertJsonPath('meta.unread_count', 0);

    $foreign = Announcement::query()->create(['title' => 'Autre', 'body' => 'Texte', 'screen' => 'home', 'audience' => 'all', 'status' => 'sent']);
    $this->postJson(route('api.v1.announcements.read', $foreign->uuid))->assertNotFound();
});

it('réserve l’envoi de notifications à l’administration [Annexe A]', function () {
    $this->actingAs($this->admin)->get(route('console.announcements'))->assertOk()->assertSee('Notifications push');

    foreach ([Role::RedacteurEnChef, Role::Coordonnateur] as $role) {
        $this->actingAs(User::factory()->withTwoFactor()->withRole($role)->create())->get(route('console.announcements'))->assertForbidden();
    }
});
