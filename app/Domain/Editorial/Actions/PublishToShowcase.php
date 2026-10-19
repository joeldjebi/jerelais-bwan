<?php

namespace App\Domain\Editorial\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Contribution\States\Broadcast;
use App\Domain\Editorial\Exceptions\EditorialRefused;
use App\Models\User;

/**
 * Publication d'un relais diffusé sur la vitrine « Vu sur REFLET TV » (WEB-05, ADM-17).
 * Seuls le titre et le texte rédigés par la rédaction, la commune et le crédit choisi sont publics.
 * L'image n'est publiée que si aucun floutage n'est demandé ; jamais la position GPS.
 */
class PublishToShowcase
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function publish(RelaisItem $relais, User $editor, string $title, string $text, bool $withImage): RelaisItem
    {
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

        return $relais;
    }

    public function withdraw(RelaisItem $relais, User $editor): void
    {
        $relais->forceFill(['showcased_at' => null])->save();
        $this->audit->record('relais.showcase_withdrawn', $relais, actor: $editor, communeId: $relais->commune_id);
    }
}
