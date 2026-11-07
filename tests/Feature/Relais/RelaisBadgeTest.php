<?php

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Candidature\Actions\DecideCandidature;
use App\Domain\Candidature\Enums\CandidatureDecision;
use App\Domain\Candidature\Models\Candidature;
use App\Domain\Relais\Actions\CreateRelaisProfile;
use App\Domain\Relais\Enums\RelaisStatus;
use App\Domain\Relais\Models\RelaisProfile;
use App\Livewire\Relais\Dossier;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;

beforeEach(function () {
    fakeSms();
    Storage::fake('identity');
    $this->candidature = Candidature::factory()->submitted()->create(['first_names' => 'Awa Marie', 'last_name' => 'Kouassi']);
    $this->coordo = User::factory()->withTwoFactor()->coordonnateurOf($this->candidature->commune)->create();
});

function accept(Candidature $candidature, User $by): RelaisProfile
{
    app(DecideCandidature::class)->handle($candidature, CandidatureDecision::Accept, $by);

    return RelaisProfile::withoutCommuneRestriction()->where('user_id', $candidature->user_id)->sole();
}

it('crée le profil de Relais à l’acceptation, avec un numéro unique [MOB-07]', function () {
    $profile = accept($this->candidature, $this->coordo);

    expect($profile->number)->toBe('JR-'.str_pad((string) $profile->id, 6, '0', STR_PAD_LEFT))
        ->and($profile->display_name)->toBe('Awa K.')
        ->and($profile->status)->toBe(RelaisStatus::Active)
        ->and($profile->commune_id)->toBe($this->candidature->commune_id)
        ->and(strlen($profile->badge_token))->toBe(40)
        ->and(AuditLog::query()->where('action', 'relais.created')->exists())->toBeTrue();
});

it('ne crée jamais deux profils pour le même Relais', function () {
    $profile = accept($this->candidature, $this->coordo);

    expect(app(CreateRelaisProfile::class)->handle($this->candidature)->id)->toBe($profile->id)
        ->and(RelaisProfile::withoutCommuneRestriction()->count())->toBe(1);
});

it('fournit le badge à l’application, sans donnée sensible [MOB-07]', function () {
    $profile = accept($this->candidature, $this->coordo);
    Sanctum::actingAs($this->candidature->user->fresh());

    $this->getJson(route('api.v1.me.badge'))
        ->assertOk()
        ->assertJsonPath('data.number', $profile->number)
        ->assertJsonPath('data.display_name', 'Awa K.')
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.qr_payload', route('badge.verify', $profile->badge_token))
        ->assertJsonPath('data.photo_url', fn (string $url) => str_contains($url, 'signature='))
        ->assertJsonMissingPath('data.badge_token')
        ->assertJsonMissingPath('data.reliability');
});

it('répond 404 au badge tant que la candidature n’est pas acceptée', function () {
    Sanctum::actingAs($this->candidature->user);

    $this->getJson(route('api.v1.me.badge'))->assertNotFound();
});

it('vérifie publiquement un badge scanné, sans exposer l’identité complète', function () {
    $profile = accept($this->candidature, $this->coordo);

    $this->get(route('badge.verify', $profile->badge_token))
        ->assertOk()
        ->assertSee('Relais vérifié.')
        ->assertSee('Awa K.')
        ->assertSee($profile->number)
        ->assertDontSee('Kouassi');

    $profile->forceFill(['status' => RelaisStatus::Suspended])->save();
    $this->get(route('badge.verify', $profile->badge_token))->assertSee('Badge inactif.');

    $this->get(route('badge.verify', str_repeat('x', 40)))->assertOk()->assertSee('Badge introuvable.');
});

it('affiche le badge avec son QR code dans l’espace Relais du site', function () {
    $profile = accept($this->candidature, $this->coordo);

    Livewire::actingAs($this->candidature->user->fresh())->test(Dossier::class)
        ->assertSee('Bienvenue parmi les Relais.')
        ->assertSee($profile->number)
        ->assertSeeHtml('<svg');
});

it('sert la photo du badge uniquement par lien signé', function () {
    $profile = accept($this->candidature, $this->coordo);
    $photo = $this->candidature->documents()->where('type', 'profile_photo')->sole();
    Storage::disk('identity')->put($photo->path, 'photo');

    $this->get(route('relais.badge.photo', $profile))->assertForbidden();
    $this->get(URL::temporarySignedRoute('relais.badge.photo', now()->addMinutes(5), $profile))->assertOk();
});
