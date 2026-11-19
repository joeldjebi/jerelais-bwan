<?php

namespace App\Domain\Editorial\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Contribution\States\Broadcast;
use App\Domain\Editorial\Exceptions\EditorialRefused;
use App\Domain\Notification\Actions\NotifyUser;
use App\Domain\Notification\Data\PushMessage;
use App\Models\User;

/**
 * Publication d'un relais diffusé sur la vitrine « Vu sur REFLET TV » (WEB-05, ADM-17).
 * Seuls le titre et le texte rédigés par la rédaction, la commune et le crédit choisi sont publics.
 * L'image n'est publiée que si aucun floutage n'est demandé ; jamais la position GPS.
 */
class PublishToShowcase
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly NotifyUser $notify,
    ) {}

    /**
     * @param  bool  $notify  prévenir le Relais de la première publication (non : déjà prévenu de la diffusion)
     */
    public function publish(RelaisItem $relais, User $editor, string $title, string $text, bool $withImage, bool $notify = true): RelaisItem
    {
        $first = $relais->showcased_at === null;

        if (! $relais->state instanceof Broadcast) {
            throw new EditorialRefused(__('editorial.errors.showcase_not_broadcast'));
        }

        if ($withImage && ($relais->blur_required === true || $relais->has_minors === true)) {
            throw new EditorialRefused(__('editorial.errors.showcase_blur'));
        }

        $relais->forceFill([
            'showcased_at' => $relais->showcased_at ?? now(),
            'showcase_title' => trim($title),
            'showcase_text' => trim($text) ?: null,
            'showcase_with_image' => $withImage,
        ])->save();

        $this->audit->record('relais.showcased', $relais, ['title' => $relais->showcase_title, 'image' => $withImage], actor: $editor, communeId: $relais->commune_id);

        if ($first && $notify) {
            $this->notify->handle($relais->loadMissing('user')->user, new PushMessage(
                __('notifications.showcased.title'),
                __('notifications.showcased.body', ['title' => (string) str($relais->showcase_title)->limit(40)]),
                ['type' => 'relais.showcased', 'uuid' => $relais->uuid],
            ), null, 'showcase');
        }

        return $relais;
    }

    public function withdraw(RelaisItem $relais, User $editor): void
    {
        $relais->forceFill(['showcased_at' => null])->save();
        $this->audit->record('relais.showcase_withdrawn', $relais, actor: $editor, communeId: $relais->commune_id);
    }
}
