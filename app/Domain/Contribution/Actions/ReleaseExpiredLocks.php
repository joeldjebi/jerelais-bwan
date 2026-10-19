<?php

namespace App\Domain\Contribution\Actions;

use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Contribution\States\InReview;
use App\Domain\Contribution\States\Received;

/**
 * Verrou expiré sans décision : le relais redevient « Reçu » et disponible pour toute l'équipe de la commune.
 */
class ReleaseExpiredLocks
{
    public function __construct(private readonly TransitionRelaisItem $transition) {}

    public function handle(): int
    {
        $released = 0;

        RelaisItem::withoutCommuneRestriction()
            ->where('state', InReview::getMorphClass())
            ->where('locked_until', '<', now())
            ->each(function (RelaisItem $relais) use (&$released): void {
                $relais->forceFill(['locked_by' => null, 'locked_until' => null])->save();
                $this->transition->handle($relais, Received::class, reason: __('Verrou expiré'));
                $released++;
            });

        return $released;
    }
}
