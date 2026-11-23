<?php

namespace App\Domain\Gains\Actions;

use App\Domain\Access\Scopes\CommuneScope;
use App\Domain\Audit\AuditLogger;
use App\Domain\Gains\Enums\CommissionStatus;
use App\Domain\Gains\Exceptions\CommissionRefused;
use App\Domain\Gains\Models\Commission;
use App\Domain\Gains\Support\Wallet;
use App\Domain\Notification\Actions\NotifyUser;
use App\Domain\Notification\Data\PushMessage;
use App\Models\User;

/**
 * Annulation motivée d'un gain crédité (relais retiré, doublon…) par l'administration (ADM-27) : possible tant que
 * la somme est encore disponible dans le portefeuille (pas déjà retirée ni en cours de retrait).
 */
class CancelCommission
{
    public function __construct(
        private readonly Wallet $wallet,
        private readonly AuditLogger $audit,
        private readonly NotifyUser $notify,
    ) {}

    /**
     * @throws CommissionRefused gain déjà annulé, ou somme déjà retirée
     */
    public function handle(Commission $commission, User $admin, string $reason): void
    {
        if ($commission->status !== CommissionStatus::Due) {
            throw new CommissionRefused(__('gains.errors.not_due'));
        }

        $commission->loadMissing(['user', 'relaisItem' => fn ($query) => $query->withoutGlobalScope(CommuneScope::class)]);

        if ($this->wallet->summary($commission->user)['available'] < $commission->amount) {
            throw new CommissionRefused(__('gains.errors.already_withdrawn'));
        }

        $commission->forceFill(['status' => CommissionStatus::Cancelled, 'cancelled_at' => now(), 'cancel_reason' => trim($reason)])->save();
        $this->audit->record('commissions.cancelled', $commission->user, ['commission' => $commission->uuid, 'amount' => $commission->amount, 'reason' => trim($reason)], actor: $admin);

        $body = __('notifications.gains.cancelled.body', ['amount' => Wallet::fcfa($commission->amount), 'title' => (string) str($commission->relaisItem->title ?? '')->limit(40)]);
        $this->notify->handle(
            $commission->user,
            new PushMessage(__('notifications.gains.cancelled.title'), $body, ['type' => 'gains.cancelled', 'uuid' => $commission->uuid]),
            __('JE RELAIS : :body', ['body' => $body]),
            'gains',
        );
    }
}
