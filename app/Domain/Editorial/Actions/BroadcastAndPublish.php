<?php

namespace App\Domain\Editorial\Actions;

use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Contribution\States\Broadcast;
use App\Domain\Contribution\States\Selected;
use App\Domain\Contribution\States\Validated;
use App\Domain\Editorial\Exceptions\EditorialRefused;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Diffuser et publier en un geste (ADM-10, ADM-13, ADM-17, WEB-05) : un relais validé (ou déjà sélectionné) est
 * sélectionné, marqué diffusé, puis publié sur la vitrine « Vu sur REFLET TV » et la page d'accueil.
 * Réservé à la rédaction et à l'administration ; tout ou rien. Le Relais reçoit une seule notification : « diffusé ».
 */
class BroadcastAndPublish
{
    public function __construct(
        private readonly SelectRelaisItem $select,
        private readonly PublishToShowcase $showcase,
    ) {}

    /**
     * @throws EditorialRefused relais ni validé ni sélectionné, ou image refusée (floutage, mineurs)
     */
    public function handle(
        RelaisItem $relais,
        User $editor,
        CarbonImmutable $broadcastAt,
        string $programme,
        ?string $url,
        string $publicTitle,
        string $publicText,
        bool $withImage,
    ): RelaisItem {
        if (! $relais->state instanceof Validated && ! $relais->state instanceof Selected && ! $relais->state instanceof Broadcast) {
            throw new EditorialRefused(__('editorial.errors.not_validated'));
        }

        if ($withImage && ($relais->blur_required === true || $relais->has_minors === true)) {
            throw new EditorialRefused(__('editorial.errors.showcase_blur'));
        }

        return DB::transaction(function () use ($relais, $editor, $broadcastAt, $programme, $url, $publicTitle, $publicText, $withImage): RelaisItem {
            if ($relais->state instanceof Validated) {
                // Diffusion annoncée dès la sélection : la notification « sélectionné » est sautée au profit de « diffusé ».
                $relais->forceFill(['broadcast_at' => $broadcastAt])->save();
                $this->select->select($relais, $editor, null, $programme);
            }

            if ($relais->state instanceof Selected) {
                $this->select->markBroadcast($relais, $editor, $broadcastAt, $programme, $url);
            }

            return $this->showcase->publish($relais, $editor, $publicTitle, $publicText, $withImage);
        });
    }
}
