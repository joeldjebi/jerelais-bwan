<?php

use App\Domain\Access\Enums\Role;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Auth\Data\PhoneNumber;
use App\Domain\Auth\Enums\UserStatus;
use App\Domain\Candidature\Enums\CandidatureStatus;
use App\Domain\Candidature\Models\Candidature;
use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Contribution\States\Rejected;
use App\Domain\Messagerie\Actions\SendMessage;
use App\Domain\Messagerie\Models\Message;
use App\Domain\Privacy\Actions\PurgeExpiredData;
use App\Domain\Relais\Enums\RelaisStatus;
use App\Domain\Relais\Models\RelaisProfile;
use App\Domain\Settings\Settings;
use App\Livewire\Console\Privacy\Index as PrivacyPage;
use App\Livewire\Relais\MesDonnees;
use App\Models\User;
use Clickbar\Magellan\Data\Geometries\Point;
use Database\Seeders\ListsSeeder;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(ListsSeeder::class);
    fakeSms();
    fakeMultipart();
    Storage::fake('identity');
    $this->profile = RelaisProfile::factory()->create(['display_name' => 'Awa K.']);
    $this->user = $this->profile->user;
    $this->relais = RelaisItem::factory()->create(['user_id' => $this->user->id, 'relais_profile_id' => $this->profile->id, 'commune_id' => $this->profile->commune_id, 'location' => Point::makeGeodetic(5.34, -4.07), 'credit_mode' => 'named']);
});

it('exporte toutes les données du Relais, sans note interne [§9.4]', function () {
    $this->relais->forceFill(['internal_note' => 'Note de l’équipe'])->save();
    Sanctum::actingAs($this->user);

    $response = $this->getJson(route('api.v1.me.export'))->assertOk()
        ->assertJsonPath('data.relais_profile.display_name', 'Awa K.')
        ->assertJsonPath('data.relais.0.title', $this->relais->title)
        ->assertJsonPath('data.candidature.status', 'accepted');

    expect($response->getContent())->not->toContain('Note de l’équipe')
        ->and(AuditLog::query()->where('action', 'privacy.exported')->exists())->toBeTrue();
});

it('supprime le compte par pseudonymisation en gardant la traçabilité [§9.4, §9.5]', function () {
    $document = $this->profile->candidature->documents()->first();
    Storage::disk('identity')->put($document->path, 'piece');
    $send = app(SendMessage::class);
    $send->send($send->conversationFor($this->profile), $this->user, 'J’habite rue des Jardins.');
    $phone = (string) $this->user->phone;
    Sanctum::actingAs($this->user);

    $this->deleteJson(route('api.v1.me.destroy'))->assertJsonValidationErrors('confirm');
    $this->deleteJson(route('api.v1.me.destroy'), ['confirm' => 'SUPPRIMER'])->assertNoContent();

    $user = $this->user->fresh();
    expect($user->status)->toBe(UserStatus::Deleted)
        ->and($user->phone_hash)->toBeNull()
        ->and($user->name)->toBe('Compte supprimé')
        ->and($user->tokens()->count())->toBe(0)
        ->and($this->profile->fresh()->status)->toBe(RelaisStatus::StruckOff)
        ->and($this->profile->candidature->fresh()->last_name)->toBeNull()
        ->and($this->profile->candidature->documents()->count())->toBe(0)
        ->and($this->relais->fresh()->location)->toBeNull()
        ->and($this->relais->fresh()->credit_mode->value)->toBe('anonymous')
        ->and(Message::query()->sole()->body)->toBe('[message supprimé]')
        ->and(RelaisItem::withoutCommuneRestriction()->whereKey($this->relais->id)->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'privacy.pseudonymized')->exists())->toBeTrue();

    Storage::disk('identity')->assertMissing($document->path);
    expect(User::findByPhone(PhoneNumber::parse($phone)))->toBeNull();
});

it('laisse un candidat supprimer son compte depuis le site', function () {
    $candidat = Candidature::factory()->create()->user;

    Livewire::actingAs($candidat)->test(MesDonnees::class)
        ->set('confirm', 'oui')->call('delete')->assertHasErrors('confirm')
        ->set('confirm', 'SUPPRIMER')->call('delete')->assertRedirect(route('home'));

    expect($candidat->fresh()->status)->toBe(UserStatus::Deleted);
    $this->actingAs($candidat->fresh())->get(route('relais.data.download'))->assertOk();
});

it('traite une demande depuis la console, jamais sur un compte de l’équipe [§9.4]', function () {
    $admin = User::factory()->withTwoFactor()->withRole(Role::Admin)->create();

    Livewire::actingAs($admin)->test(PrivacyPage::class)
        ->set('query', (string) $this->user->phone)->call('search')
        ->assertSee($this->user->name)->assertSee('Supprimer le compte')
        ->set('reason', 'Demande écrite du 12 octobre')->call('pseudonymize');

    expect($this->user->fresh()->status)->toBe(UserStatus::Deleted);

    $this->actingAs($admin)->get(route('console.privacy.export', $this->user))->assertOk()->assertHeader('Content-Disposition');

    $staff = User::factory()->withTwoFactor()->withRole(Role::Journaliste)->create(['email' => 'journaliste@reflet.tv']);
    Livewire::actingAs($admin)->test(PrivacyPage::class)->set('query', 'journaliste@reflet.tv')->call('search')->assertDontSeeHtml("\$set('confirming', true)");
});

it('applique les durées de conservation, sans rien purger quand elles sont vides [§9.4]', function () {
    $rejected = Candidature::factory()->submitted()->create();
    $rejected->forceFill(['status' => CandidatureStatus::Rejected, 'decided_at' => now()->subDays(100)])->save();
    $oldRejected = RelaisItem::factory()->create(['state' => Rejected::class]);
    $media = attachReadyMedia($oldRejected);
    Storage::disk('medias')->put($media->path, 'video');
    RelaisItem::withoutCommuneRestriction()->whereKey($oldRejected->id)->update(['updated_at' => now()->subDays(100)]);

    expect(array_sum(app(PurgeExpiredData::class)->handle()))->toBe(0);

    $settings = app(Settings::class);
    $settings->set('retention.rejected_candidatures_days', 90);
    $settings->set('retention.rejected_relais_days', 90);

    $counts = app(PurgeExpiredData::class)->handle();

    expect($counts['candidatures'])->toBe(1)
        ->and($counts['rejected_relais'])->toBe(1)
        ->and($rejected->user->fresh()->status)->toBe(UserStatus::Deleted)
        ->and($media->fresh()->purged_at)->not->toBeNull()
        ->and($media->fresh()->sha256)->toBe($media->sha256);
    Storage::disk('medias')->assertMissing($media->path);
});
