<?php

namespace App\Domain\Gains\Listeners;

use App\Domain\Contribution\Events\RelaisStatusChanged;
use App\Domain\Gains\Enums\CommissionKind;
use App\Domain\Gains\Enums\CommissionStatus;
use App\Domain\Gains\Models\Commission;
use App\Domain\Settings\Settings;

/**
 * Commissions des Relais (MOB-26, ADM-27) : un montant dû à la validation et un autre à la diffusion, selon les
 * Paramètres (vide : pas de commission). Une seule commission de chaque sorte par relais. Un relais finalement
 * rejeté voit ses commissions encore dues annulées ; une commission déjà payée n'est jamais touchée.
 */
class RecordRelaisCommission
{
    public function __construct(private readonly Settings $settings) {}

    public function handle(RelaisStatusChanged $event): void
    {
        $relais = $event->relais;

        if ($event->to === 'rejected') {
            Commission::query()->where('relais_item_id', $relais->id)->due()->update([
                'status' => CommissionStatus::Cancelled->value,
                'cancelled_at' => now(),
                'cancel_reason' => __('gains.cancelled_by_rejection'),
            ]);

            return;
        }

        $kind = CommissionKind::tryFrom($event->to);

        if ($kind === null) {
            return;
        }

        $amount = $this->settings->get($kind->amountSetting());

        if (! is_int($amount) || $amount <= 0) {
            return;
        }

        Commission::query()->firstOrCreate(
            ['relais_item_id' => $relais->id, 'kind' => $kind->value],
            ['user_id' => $relais->user_id, 'amount' => $amount, 'status' => CommissionStatus::Due],
        );
    }
}
