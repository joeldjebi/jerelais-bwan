<?php

use App\Domain\Access\Enums\Role;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Auth\Models\Device;
use App\Domain\Gains\Models\PayoutAccount;
use App\Domain\Relais\Models\RelaisProfile;
use App\Domain\Territoire\Models\Commune;
use App\Livewire\Console\Relais\Index;
use App\Livewire\Console\Relais\Show;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->commune = Commune::factory()->create();
    $this->admin = User::factory()->withTwoFactor()->withRole(Role::Admin)->create();
    $this->profile = RelaisProfile::factory()->create(['commune_id' => $this->commune->id, 'display_name' => 'Awa T.']);
    $this->other = RelaisProfile::factory()->create(['commune_id' => $this->commune->id, 'display_name' => 'Koffi K.']);
});

it('affiche les informations personnelles du Relais à la demande, et trace la consultation [§9.4]', function () {
    $user = $this->profile->user;
    PayoutAccount::query()->create(['user_id' => $user->id, 'method' => 'wave', 'phone' => '+2250707070707', 'holder_name' => 'Awa Traoré']);

    $component = Livewire::actingAs($this->admin)->test(Show::class, ['profile' => $this->profile])
        ->assertSee('Informations personnelles')
        ->assertDontSee('Awa Traoré');

    expect(AuditLog::query()->where('action', 'relais.personal_data.viewed')->exists())->toBeFalse();

    $component->call('revealPersonal')
        ->assertSee((string) $user->phoneNumber()?->e164)
        ->assertSee('Wave · +2250707070707 · Awa Traoré')
        ->assertSee('Non soumise');

    expect(AuditLog::query()->where('action', 'relais.personal_data.viewed')->sole()->actor_id)->toBe($this->admin->id);
});

it('montre aussi les informations personnelles au coordonnateur de la commune', function () {
    $coordo = User::factory()->withTwoFactor()->coordonnateurOf($this->commune)->create();

    Livewire::actingAs($coordo)->test(Show::class, ['profile' => $this->profile])
        ->call('revealPersonal')
        ->assertSee((string) $this->profile->user->phoneNumber()?->e164);
});

it('indique si le Relais a enregistré son jeton de notifications push, sur la fiche et dans la liste [MOB-21]', function () {
    Device::query()->create(['user_id' => $this->profile->user_id, 'name' => 'iPhone', 'platform' => 'ios', 'fcm_token' => 'jeton']);
    Device::query()->create(['user_id' => $this->profile->user_id, 'name' => 'Ancien Tecno', 'platform' => 'android', 'fcm_token' => null]);

    Livewire::actingAs($this->admin)->test(Show::class, ['profile' => $this->profile])
        ->assertSee('Activées sur 1 appareil')
        ->assertSee('Jeton enregistré')
        ->assertSee('Pas de jeton');

    Livewire::actingAs($this->admin)->test(Show::class, ['profile' => $this->other])->assertSee('Non activées');

    Livewire::actingAs($this->admin)->test(Index::class)
        ->set('push', 'avec')->assertSee('Awa T.')->assertDontSee('Koffi K.')
        ->set('push', 'sans')->assertSee('Koffi K.')->assertDontSee('Awa T.');
});
