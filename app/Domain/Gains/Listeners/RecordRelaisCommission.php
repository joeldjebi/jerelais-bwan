<?php

namespace App\Domain\Gains\Listeners;

use App\Domain\Contribution\Events\RelaisStatusChanged;
use App\Domain\Gains\Enums\CommissionKind;
use App\Domain\Gains\Enums\CommissionStatus;
use App\Domain\Gains\Models\Commission;
use App\Domain\Notification\Actions\NotifyUser;
use App\Domain\Notification\Data\PushMessage;
use App\Domain\Settings\Settings;

/**
 * Portefeuille des Relais (MOB-26, ADM-27) : un gain est crédité quand le relais est diffusé (validé par le
 * coordonnateur puis par la rédaction), selon le montant des Paramètres (vide : aucun gain). Un seul gain par relais.
 * Un relais finalement rejeté voit son gain annulé tant qu'il est encore disponible dans le portefeuille.
 */
class RecordRelaisCommission
{
    public function __construct(
        private readonly Settings $settings,
        private readonly NotifyUser $notify,
    ) {}

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

        // Gain crédité quand toutes les étapes sont franchies : coordonnateur, rédaction, diffusion.
        if ($event->to !== CommissionKind::Broadcast->value) {
            return;
        }

        $kind = CommissionKind::Broadcast;

        $amount = $this->settings->get($kind->amountSetting());

        if (! is_int($amount) || $amount <= 0) {
            return;
        }

        $commission = Commission::query()->firstOrCreate(
            ['relais_item_id' => $relais->id, 'kind' => $kind->value],
            ['user_id' => $relais->user_id, 'amount' => $amount, 'status' => CommissionStatus::Due],
        );

        if ($commission->wasRecentlyCreated) {
            $this->notify->handle($relais->loadMissing('user')->user, new PushMessage(
                __('notifications.gains.earned.title'),
                __('notifications.gains.earned.body', ['amount' => number_format($amount, 0, ',', ' ').' FCFA', 'title' => (string) str($relais->title ?? '')->limit(40)]),
                ['type' => 'gains.earned', 'uuid' => $commission->uuid, 'amount' => $amount],
            ), null, 'gains');
        }
    }
}
