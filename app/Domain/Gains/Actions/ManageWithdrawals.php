<?php

namespace App\Domain\Gains\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Gains\Enums\PaymentMethod;
use App\Domain\Gains\Enums\PayoutStatus;
use App\Domain\Gains\Exceptions\CommissionRefused;
use App\Domain\Gains\Models\Payout;
use App\Domain\Gains\Models\PayoutAccount;
use App\Domain\Gains\Support\Wallet;
use App\Domain\Notification\Actions\NotifyUser;
use App\Domain\Notification\Data\PushMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Retraits du portefeuille (MOB-26, ADM-27) : le Relais demande un retrait vers son compte Mobile Money ;
 * l'administration verse la somme (hors plateforme) et l'enregistre avec la référence de la transaction, ou refuse
 * la demande avec un motif (la somme revient dans le portefeuille). Le Relais est prévenu à chaque étape.
 */
class ManageWithdrawals
{
    public function __construct(
        private readonly Wallet $wallet,
        private readonly AuditLogger $audit,
        private readonly NotifyUser $notify,
    ) {}

    /**
     * @throws CommissionRefused retrait impossible (message prêt à afficher, première raison)
     */
    public function request(User $relais, int $amount): Payout
    {
        return DB::transaction(function () use ($relais, $amount): Payout {
            // Un seul retrait à la fois : verrou sur le compte pendant le contrôle du solde.
            User::query()->whereKey($relais->id)->lockForUpdate()->first();

            $blockers = $this->wallet->blockers($relais, $amount);

            if ($blockers !== []) {
                throw new CommissionRefused((string) reset($blockers));
            }

            /** @var PayoutAccount $account */
            $account = $relais->payoutAccount()->firstOrFail();

            $payout = Payout::query()->create([
                'user_id' => $relais->id,
                'amount' => $amount,
                'status' => PayoutStatus::Requested,
                'method' => $account->method,
                'phone' => $account->phone,
                'holder_name' => $account->holder_name,
                'requested_at' => now(),
            ]);

            $this->audit->record('withdrawal.requested', $relais, ['payout' => $payout->uuid, 'amount' => $amount, 'method' => $account->method->value], actor: $relais);

            return $payout;
        });
    }

    /**
     * @throws CommissionRefused demande déjà traitée
     */
    public function pay(Payout $payout, User $admin, string $reference, ?PaymentMethod $method = null, ?string $phone = null): Payout
    {
        $paid = DB::transaction(function () use ($payout, $admin, $reference, $method, $phone): Payout {
            $payout = Payout::query()->lockForUpdate()->findOrFail($payout->id);

            if ($payout->status !== PayoutStatus::Requested) {
                throw new CommissionRefused(__('gains.errors.withdrawal_not_pending'));
            }

            $payout->forceFill([
                'status' => PayoutStatus::Paid,
                'reference' => trim($reference),
                'method' => $method ?? $payout->method,
                'phone' => $phone === null ? $payout->phone : (trim($phone) ?: $payout->phone),
                'paid_at' => now(),
                'paid_by' => $admin->id,
            ])->save();

            $this->audit->record('withdrawal.paid', $payout->user, ['payout' => $payout->uuid, 'amount' => $payout->amount, 'method' => $payout->method->value, 'reference' => $payout->reference], actor: $admin);

            return $payout;
        });

        $amount = Wallet::fcfa($paid->amount);
        $this->notify->handle(
            $paid->user,
            new PushMessage(__('Retrait payé'), __(':amount vous ont été envoyés par :method. Merci pour vos relais !', ['amount' => $amount, 'method' => $paid->method->label()]), ['type' => 'gains.paid', 'uuid' => $paid->uuid, 'amount' => $paid->amount]),
            __('JE RELAIS : :amount vous ont été envoyés par :method (réf. :reference). Merci pour vos relais !', ['amount' => $amount, 'method' => $paid->method->label(), 'reference' => $paid->reference]),
            'gains',
        );

        return $paid;
    }

    /**
     * @throws CommissionRefused demande déjà traitée
     */
    public function reject(Payout $payout, User $admin, string $reason): Payout
    {
        $rejected = DB::transaction(function () use ($payout, $admin, $reason): Payout {
            $payout = Payout::query()->lockForUpdate()->findOrFail($payout->id);

            if ($payout->status !== PayoutStatus::Requested) {
                throw new CommissionRefused(__('gains.errors.withdrawal_not_pending'));
            }

            $payout->forceFill(['status' => PayoutStatus::Rejected, 'rejection_reason' => trim($reason), 'rejected_at' => now(), 'paid_by' => $admin->id])->save();
            $this->audit->record('withdrawal.rejected', $payout->user, ['payout' => $payout->uuid, 'amount' => $payout->amount, 'reason' => trim($reason)], actor: $admin);

            return $payout;
        });

        $this->notify->handle(
            $rejected->user,
            new PushMessage(__('Retrait refusé'), __('Votre demande de retrait de :amount n’a pas abouti : la somme reste dans votre portefeuille. Ouvrez l’application pour le motif.', ['amount' => Wallet::fcfa($rejected->amount)]), ['type' => 'gains.withdrawal_rejected', 'uuid' => $rejected->uuid]),
            null,
            'gains',
        );

        return $rejected;
    }
}
