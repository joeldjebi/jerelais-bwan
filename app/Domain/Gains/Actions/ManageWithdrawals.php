<?php

namespace App\Domain\Gains\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Gains\Contracts\PayoutGateway;
use App\Domain\Gains\Enums\PaymentMethod;
use App\Domain\Gains\Enums\PayoutStatus;
use App\Domain\Gains\Exceptions\CommissionRefused;
use App\Domain\Gains\Exceptions\PayoutFailed;
use App\Domain\Gains\Models\Payout;
use App\Domain\Gains\Models\PayoutAccount;
use App\Domain\Gains\Support\Wallet;
use App\Domain\Notification\Actions\NotifyUser;
use App\Domain\Notification\Data\PushMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Retraits du portefeuille (MOB-26, ADM-27) : le Relais retire vers l'un de ses numéros Mobile Money et la somme part
 * aussitôt par la passerelle de paiement (simulée tant qu'aucun prestataire n'est branché). Si la passerelle ne paie
 * pas (mode manuel, échec du prestataire), le retrait reste en attente : l'administration le paie depuis la console
 * avec la référence de la transaction, ou le refuse avec un motif (la somme revient dans le portefeuille).
 * Le Relais est prévenu à chaque étape.
 */
class ManageWithdrawals
{
    public function __construct(
        private readonly Wallet $wallet,
        private readonly AuditLogger $audit,
        private readonly NotifyUser $notify,
        private readonly PayoutGateway $gateway,
    ) {}

    /**
     * Retire `$amount` vers le numéro choisi (UUID), ou vers le premier numéro enregistré.
     *
     * @throws CommissionRefused retrait impossible (message prêt à afficher, première raison)
     */
    public function request(User $relais, int $amount, ?string $accountUuid = null): Payout
    {
        $payout = DB::transaction(function () use ($relais, $amount, $accountUuid): Payout {
            // Un seul retrait à la fois : verrou sur le compte pendant le contrôle du solde.
            User::query()->whereKey($relais->id)->lockForUpdate()->first();

            $blockers = $this->wallet->blockers($relais, $amount);

            if ($blockers !== []) {
                throw new CommissionRefused((string) reset($blockers));
            }

            /** @var PayoutAccount|null $account */
            $account = $accountUuid === null
                ? $relais->payoutAccounts()->first()
                : $relais->payoutAccounts()->where('uuid', $accountUuid)->first();

            if ($account === null) {
                throw new CommissionRefused(__('gains.errors.payout_account_unknown'));
            }

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

        try {
            $reference = $this->gateway->send($payout);
        } catch (PayoutFailed $exception) {
            report($exception);
            $this->audit->record('withdrawal.failed', $relais, ['payout' => $payout->uuid, 'amount' => $amount, 'provider' => $this->gateway->name()]);

            return $payout;
        }

        return $reference === null ? $payout : $this->settle($payout, $reference, $this->gateway->name());
    }

    /**
     * Paiement manuel depuis la console, avec la référence de la transaction Mobile Money.
     *
     * @throws CommissionRefused demande déjà traitée
     */
    public function pay(Payout $payout, User $admin, string $reference, ?PaymentMethod $method = null, ?string $phone = null): Payout
    {
        return $this->settle($payout, $reference, 'manual', $admin, $method, $phone);
    }

    /**
     * @throws CommissionRefused demande déjà traitée
     */
    private function settle(Payout $payout, string $reference, string $provider, ?User $admin = null, ?PaymentMethod $method = null, ?string $phone = null): Payout
    {
        $paid = DB::transaction(function () use ($payout, $reference, $provider, $admin, $method, $phone): Payout {
            $payout = Payout::query()->lockForUpdate()->findOrFail($payout->id);

            if ($payout->status !== PayoutStatus::Requested) {
                throw new CommissionRefused(__('gains.errors.withdrawal_not_pending'));
            }

            $payout->forceFill([
                'status' => PayoutStatus::Paid,
                'reference' => trim($reference),
                'provider' => $provider,
                'method' => $method ?? $payout->method,
                'phone' => $phone === null ? $payout->phone : (trim($phone) ?: $payout->phone),
                'paid_at' => now(),
                'paid_by' => $admin?->id,
            ])->save();

            $this->audit->record('withdrawal.paid', $payout->user, ['payout' => $payout->uuid, 'amount' => $payout->amount, 'method' => $payout->method->value, 'provider' => $provider, 'reference' => $payout->reference], actor: $admin);

            return $payout;
        });

        $replace = ['amount' => Wallet::fcfa($paid->amount), 'method' => $paid->method->label(), 'phone' => (string) $paid->phone, 'reference' => (string) $paid->reference];
        $this->notify->handle(
            $paid->user,
            new PushMessage(
                $paid->isSimulated() ? __('Retrait effectué (simulation)') : __('Retrait payé'),
                __(':amount envoyés sur votre :method :phone. Merci pour vos relais !', $replace),
                ['type' => 'gains.paid', 'uuid' => $paid->uuid, 'amount' => $paid->amount],
            ),
            __('JE RELAIS : :amount envoyés sur votre :method :phone (réf. :reference). Merci pour vos relais !', $replace),
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
