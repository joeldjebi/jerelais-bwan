<?php

namespace App\Domain\Gains\Listeners;

use App\Domain\Contribution\Events\RelaisStatusChanged;
use App\Domain\Gains\Enums\CommissionStatus;
use App\Domain\Gains\Models\Commission;
use App\Domain\Gains\Models\GainDecision;

/**
 * Portefeuille des Relais (MOB-26, ADM-27) : la rémunération est décidée relais par relais par l'administration ou
 * le superviseur (DecideRelaisGain), jamais créditée automatiquement. Un relais finalement rejeté voit sa
 * rémunération encore disponible annulée, et l'annulation est historisée.
 */
class RecordRelaisCommission
{
    public function handle(RelaisStatusChanged $event): void
    {
        if ($event->to !== 'rejected') {
            return;
        }

        Commission::query()->where('relais_item_id', $event->relais->id)->due()->get()->each(function (Commission $commission) use ($event): void {
            $commission->forceFill(['status' => CommissionStatus::Cancelled, 'cancelled_at' => now(), 'cancel_reason' => __('gains.cancelled_by_rejection')])->save();
            GainDecision::query()->create([
                'relais_item_id' => $event->relais->id,
                'commission_id' => $commission->id,
                'decision' => 'cancelled',
                'previous_amount' => $commission->amount,
                'reason' => __('gains.cancelled_by_rejection'),
            ]);
        });
    }
}
