<?php

namespace App\Domain\Contribution\Actions;

use App\Domain\Contribution\Events\RelaisStatusChanged;
use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Contribution\States\RelaisState;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Seule porte de sortie pour changer le statut d'un relais : transition autorisée par le cycle de vie,
 * inscrite dans l'historique, puis annoncée (notification, temps réel).
 */
class TransitionRelaisItem
{
    /**
     * @param  class-string<RelaisState>  $to
     */
    public function handle(RelaisItem $relais, string $to, ?User $actor = null, ?string $reason = null): RelaisItem
    {
        $from = $relais->state->getValue();

        DB::transaction(function () use ($relais, $to, $actor, $reason, $from): void {
            $relais->state->transitionTo($to);

            $relais->statusHistory()->create([
                'from' => $from,
                'to' => $relais->state->getValue(),
                'actor_id' => $actor?->id,
                'reason' => $reason,
                'created_at' => now(),
            ]);
        });

        RelaisStatusChanged::dispatch($relais, $from, $relais->state->getValue());

        return $relais;
    }
}
