<?php

namespace App\Domain\Editorial\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Contribution\States\Broadcast;
use App\Domain\Editorial\Enums\ShowcaseVisual;
use App\Domain\Editorial\Exceptions\EditorialRefused;
use App\Domain\Editorial\Support\ShowcaseVisuals;
use App\Domain\Notification\Actions\NotifyUser;
use App\Domain\Notification\Data\PushMessage;
use App\Models\User;
use Illuminate\Http\UploadedFile;

/**
 * Publication d'un relais diffusé sur la vitrine « Vu sur REFLET TV » (WEB-05, ADM-17).
 * Seuls le titre et le texte rédigés par la rédaction, la commune et le crédit choisi sont publics, avec un visuel :
 * aucun, image d'origine (jamais si un floutage est demandé ou en présence de mineurs), image floutée
 * automatiquement, ou version floutée par la rédaction. Jamais la position GPS, jamais les originaux.
 */
class PublishToShowcase
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly NotifyUser $notify,
        private readonly ShowcaseVisuals $visuals,
    ) {}

    /**
     * @param  bool|ShowcaseVisual  $visual  visuel public (true : image d'origine, false : sans image)
     * @param  bool  $notify  prévenir le Relais de la première publication (non : déjà prévenu de la diffusion)
     * @param  UploadedFile|null  $edited  version floutée par la rédaction (visuel « edited »), sinon la précédente
     *
     * @throws EditorialRefused relais non diffusé, image d'origine interdite (floutage, mineurs), rien à flouter,
     *                          version floutée absente ou refusée
     */
    public function publish(RelaisItem $relais, User $editor, string $title, string $text, bool|ShowcaseVisual $visual, bool $notify = true, ?UploadedFile $edited = null): RelaisItem
    {
        $visual = is_bool($visual) ? ($visual ? ShowcaseVisual::Original : ShowcaseVisual::None) : $visual;
        $first = $relais->showcased_at === null;

        if (! $relais->state instanceof Broadcast) {
            throw new EditorialRefused(__('editorial.errors.showcase_not_broadcast'));
        }

        if ($visual === ShowcaseVisual::Original && ($relais->blur_required === true || $relais->has_minors === true)) {
            throw new EditorialRefused(__('editorial.errors.showcase_blur'));
        }

        $files = [];

        if ($visual === ShowcaseVisual::Blurred) {
            $files['showcase_blurred_path'] = $this->visuals->blurred($relais);
        }

        if ($visual === ShowcaseVisual::Edited) {
            if ($edited !== null) {
                $stored = $this->visuals->storeEdited($relais, $edited);
                $files += ['showcase_media_path' => $stored['path'], 'showcase_media_mime' => $stored['mime'], 'showcase_poster_path' => $stored['poster']];
            } elseif ($relais->showcase_media_path === null) {
                throw new EditorialRefused(__('editorial.errors.showcase_edited_required'));
            }
        }

        $relais->forceFill([
            'showcased_at' => $relais->showcased_at ?? now(),
            'showcase_title' => trim($title),
            'showcase_text' => trim($text) ?: null,
            'showcase_with_image' => $visual->hasImage(),
            'showcase_visual' => $visual,
            ...$files,
        ])->save();

        $this->audit->record('relais.showcased', $relais, ['title' => $relais->showcase_title, 'visual' => $visual->value], actor: $editor, communeId: $relais->commune_id);

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
