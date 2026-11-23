<?php

use App\Domain\Access\Enums\Role;
use App\Domain\Auth\Models\Device;
use App\Domain\Contribution\Actions\TransitionRelaisItem;
use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Contribution\States\Broadcast;
use App\Domain\Contribution\States\InReview;
use App\Domain\Contribution\States\Selected;
use App\Domain\Contribution\States\Validated;
use App\Domain\Editorial\Actions\PublishToShowcase;
use App\Domain\Gains\Actions\CancelCommission;
use App\Domain\Gains\Models\Commission;
use App\Domain\Legal\Actions\PublishLegalDocument;
use App\Domain\Legal\Enums\LegalDocumentType;
use App\Domain\Media\Contracts\VirusScanner;
use App\Domain\Media\Enums\MediaStatus;
use App\Domain\Media\Jobs\ProcessMedia;
use App\Domain\Relais\Actions\ChangeRelaisStatus;
use App\Domain\Relais\Models\RelaisProfile;
use App\Domain\Territoire\Models\Commune;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/*
 * Chaque événement entre l'équipe et un Relais lui envoie une notification push (MOB-21).
 */

beforeEach(function () {
    $this->sms = fakeSms();
    $this->push = fakePush();
    $this->storage = fakeMultipart();
    $this->admin = User::factory()->withTwoFactor()->withRole(Role::Admin)->create();
    $this->commune = Commune::factory()->create(['name' => 'Cocody']);
    $this->profile = RelaisProfile::factory()->create(['commune_id' => $this->commune->id]);
    $this->user = $this->profile->user;
    Device::query()->create(['user_id' => $this->user->id, 'name' => 'Tecno', 'platform' => 'android', 'fcm_token' => 'jeton']);
    $this->relais = RelaisItem::factory()->create(['user_id' => $this->user->id, 'commune_id' => $this->commune->id, 'title' => 'Marché inondé', 'state' => InReview::class]);
});

function pushTypes(): array
{
    return collect(test()->push->sent)->pluck('message.data.type')->all();
}

it('annonce le niveau Bronze au premier relais validé, sans gain automatique', function () {
    app(TransitionRelaisItem::class)->handle($this->relais, Validated::class, $this->admin);
    app(TransitionRelaisItem::class)->handle($this->relais->refresh(), Selected::class, $this->admin);
    app(TransitionRelaisItem::class)->handle($this->relais->refresh(), Broadcast::class, $this->admin);

    expect(pushTypes())->toContain('relais.status_changed', 'account.level')->not->toContain('gains.earned');
    $level = collect($this->push->sent)->first(fn ($sent) => $sent['message']->data['type'] === 'account.level');
    expect($level['message']->data['level'])->toBe('bronze');
});

it('prévient de l’annulation d’une commission par l’administration', function () {
    $commission = Commission::factory()->create(['relais_item_id' => $this->relais->id, 'amount' => 2000]);

    app(CancelCommission::class)->handle($commission, $this->admin, 'Doublon');

    expect(pushTypes())->toBe(['gains.cancelled']);
});

it('prévient du changement de commune, par push (SMS de secours sinon)', function () {
    $yopougon = Commune::factory()->create(['name' => 'Yopougon']);

    app(ChangeRelaisStatus::class)->transfer($this->profile, $yopougon, $this->admin, 'Déménagement');

    expect(pushTypes())->toBe(['account.transferred'])
        ->and($this->push->sent[0]['message']->body)->toContain('Yopougon');
});

it('prévient de la publication sur la vitrine une seule fois', function () {
    $this->relais->forceFill(['state' => Broadcast::class])->save();

    app(PublishToShowcase::class)->publish($this->relais, $this->admin, 'Marché inondé', '', false);
    app(PublishToShowcase::class)->publish($this->relais, $this->admin, 'Marché inondé (mise à jour)', '', false);

    expect(pushTypes())->toBe(['relais.showcased']);
});

it('prévient d’un fichier refusé au contrôle', function () {
    app()->instance(VirusScanner::class, new class implements VirusScanner
    {
        public function scan(string $path): ?string
        {
            return 'Eicar-Test-Signature';
        }
    });
    $media = attachReadyMedia($this->relais);
    $media->forceFill(['status' => MediaStatus::Uploaded])->save();
    Storage::disk('medias')->put($media->path, photoBytesForEvents());

    ProcessMedia::dispatchSync($media->id);

    expect($media->refresh()->status)->toBe(MediaStatus::Rejected)
        ->and(pushTypes())->toBe(['relais.media_rejected']);
});

it('invite les Relais à lire un texte juridique mis à jour, pas à la première publication', function () {
    app(PublishLegalDocument::class)->handle(LegalDocumentType::Charte, 'Charte', 'Version 1', $this->admin);
    expect(pushTypes())->toBeEmpty();

    app(PublishLegalDocument::class)->handle(LegalDocumentType::Charte, 'Charte', 'Version 2', $this->admin);
    expect(pushTypes())->toBe(['legal.updated']);
});

function photoBytesForEvents(): string
{
    $image = imagecreatetruecolor(40, 30);
    ob_start();
    imagejpeg($image);

    return (string) ob_get_clean();
}
