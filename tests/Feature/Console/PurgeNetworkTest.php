<?php

use App\Domain\Access\Enums\Role;
use App\Domain\Auth\Data\PhoneNumber;
use App\Domain\Auth\Enums\UserStatus;
use App\Domain\Candidature\Models\Candidature;
use App\Domain\Identity\Models\IdentityVerification;
use App\Domain\Relais\Models\RelaisProfile;
use App\Livewire\Console\Network\PurgeButton;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('identity');
    config(['jerelais.otp.fixed_code' => '1234']);
    fakeSms();
});

function registerNetworkAccount(string $phone): User
{
    test()->postJson(route('api.v1.auth.register.request'), ['phone' => $phone])->assertAccepted();
    test()->postJson(route('api.v1.auth.register.verify'), ['phone' => $phone, 'code' => '1234'])->assertOk();
    test()->postJson(route('api.v1.auth.register.complete'), ['phone' => $phone, 'password' => 'MotDePasse2026', 'password_confirmation' => 'MotDePasse2026'])->assertCreated();

    return User::findByPhone(PhoneNumber::parse($phone));
}

it('vide tous les comptes du réseau et leurs données, sans toucher à l’équipe', function () {
    $relais = registerNetworkAccount('0707070707');
    Sanctum::actingAs($relais);
    $this->putJson(route('api.v1.identity.update'), ['document_type' => 'passeport', 'last_name' => 'Traoré'])->assertOk();
    $this->post(route('api.v1.identity.files.store'), ['kind' => 'page', 'file' => UploadedFile::fake()->image('page.jpg')], ['Accept' => 'application/json'])->assertCreated();
    $this->postJson(route('api.v1.relais.store'), ['client_uuid' => (string) Str::uuid()])->assertCreated();

    $candidat = User::factory()->withRole(Role::Candidat)->create();
    $coordinator = User::factory()->withTwoFactor()->withRole(Role::Coordonnateur)->create();
    $admin = User::factory()->withTwoFactor()->withRole(Role::Admin)->create();

    Livewire::actingAs($admin)->test(PurgeButton::class)
        ->set('confirmation', 'SUPPRIMER')
        ->call('purge')
        ->assertHasNoErrors();

    expect($relais->refresh()->status)->toBe(UserStatus::Deleted)
        ->and($relais->phone_hash)->toBeNull()
        ->and($candidat->refresh()->status)->toBe(UserStatus::Deleted)
        ->and($coordinator->refresh()->status)->toBe(UserStatus::Active)
        ->and($admin->refresh()->status)->toBe(UserStatus::Active)
        ->and(RelaisProfile::withoutCommuneRestriction()->count())->toBe(0)
        ->and(Candidature::withoutCommuneRestriction()->count())->toBe(0)
        ->and(IdentityVerification::query()->count())->toBe(0)
        ->and(DB::table('relais_items')->count())->toBe(0)
        ->and(Storage::disk('identity')->allFiles())->toBe([]);

    $this->assertDatabaseHas('audit_logs', ['action' => 'network.purged']);

    // Le numéro peut se réinscrire.
    registerNetworkAccount('0707070707');
});

it('exige de taper SUPPRIMER', function () {
    $relais = registerNetworkAccount('0707070707');
    $admin = User::factory()->withTwoFactor()->withRole(Role::Admin)->create();

    Livewire::actingAs($admin)->test(PurgeButton::class)
        ->set('confirmation', 'oui')
        ->call('purge')
        ->assertHasErrors('confirmation');

    expect($relais->refresh()->status)->toBe(UserStatus::Active);
});

it('réserve le bouton aux administrateurs', function () {
    $coordinator = User::factory()->withTwoFactor()->withRole(Role::Coordonnateur)->create();

    Livewire::actingAs($coordinator)->test(PurgeButton::class)->assertDontSee('Tout vider')
        ->set('confirmation', 'SUPPRIMER')->call('purge')->assertForbidden();

    $admin = User::factory()->withTwoFactor()->withRole(Role::Admin)->create();
    $this->actingAs($admin)->get(route('console.relais'))->assertOk()->assertSee('Tout vider');
});
