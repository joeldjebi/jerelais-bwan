<?php

namespace App\Domain\Gains\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Gains\Enums\CommissionStatus;
use App\Domain\Gains\Enums\PaymentMethod;
use App\Domain\Gains\Exceptions\CommissionRefused;
use App\Domain\Gains\Models\Commission;
use App\Domain\Gains\Models\Payout;
use App\Domain\Notification\Actions\NotifyUser;
use App\Domain\Notification\Data\PushMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Paiement des commissions d'un Relais (ADM-27) : l'administrateur verse la somme due par Mobile Money (hors de la
 * plateforme), puis l'enregistre ici avec le moyen et la référence de la transaction. Toutes les commissions dues du
 * Relais sont soldées en une fois par un paiement (historique du Relais) ; le Relais est prévenu. Identité vérifiée
 * exigée (MOB-01).
 */
class PayCommissions
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly NotifyUser $notify,
    ) {}

    /**
     * @return int montant total payé (FCFA)
     *
     * @throws CommissionRefused rien à payer ou identité non vérifiée
     */
    public function pay(User $relais, User $admin, PaymentMethod $method, string $reference, ?string $phone): int
    {
        if (! $relais->isEligibleForGains()) {
            throw new CommissionRefused(__('gains.errors.identity_not_verified'));
        }

        $payout = DB::transaction(function () use ($relais, $admin, $method, $reference, $phone): Payout {
            $due = Commission::query()->where('user_id', $relais->id)->due()->lockForUpdate()->get();

            if ($due->isEmpty()) {
                throw new CommissionRefused(__('gains.errors.not_due'));
            }

            $phone = $phone === null ? null : (trim($phone) ?: null);
            $payout = Payout::query()->create([
                'user_id' => $relais->id,
                'amount' => (int) $due->sum('amount'),
                'method' => $method,
                'reference' => trim($reference),
                'phone' => $phone,
                'paid_at' => now(),
                'paid_by' => $admin->id,
            ]);

            foreach ($due as $commission) {
                $commission->forceFill([
                    'status' => CommissionStatus::Paid,
                    'payout_id' => $payout->id,
                    'paid_at' => $payout->paid_at,
                    'paid_by' => $admin->id,
                    'payment_method' => $method,
                    'payment_reference' => $payout->reference,
                    'payment_phone' => $phone,
                ])->save();
            }

            $this->audit->record('commissions.paid', $relais, [
                'payout' => $payout->uuid,
                'commissions' => $due->pluck('uuid')->all(),
                'amount' => $payout->amount,
                'method' => $method->value,
                'reference' => $payout->reference,
            ], actor: $admin);

            return $payout;
        });

        $total = $payout->amount;

        // Espace ordinaire comme séparateur des milliers : reste dans l'alphabet SMS standard (pas d'Unicode, SMS moins cher).
        $amount = number_format($total, 0, ',', ' ').' FCFA';

        $this->notify->handle(
            $relais,
            new PushMessage(__('Paiement envoyé'), __(':amount vous ont été envoyés par :method. Merci pour vos relais !', ['amount' => $amount, 'method' => $method->label()]), ['type' => 'gains.paid', 'uuid' => $payout->uuid, 'amount' => $total]),
            __('JE RELAIS : :amount vous ont été envoyés par :method (réf. :reference). Merci pour vos relais !', ['amount' => $amount, 'method' => $method->label(), 'reference' => trim($reference)]),
            'gains',
        );

        return $total;
    }

    /**
     * Annulation motivée d'une commission encore due (relais retiré, doublon…).
     *
     * @throws CommissionRefused commission déjà payée ou annulée
     */
    public function cancel(Commission $commission, User $admin, string $reason): void
    {
        if ($commission->status !== CommissionStatus::Due) {
            throw new CommissionRefused(__('gains.errors.not_due'));
        }

        $commission->forceFill(['status' => CommissionStatus::Cancelled, 'cancelled_at' => now(), 'cancel_reason' => trim($reason)])->save();
        $this->audit->record('commissions.cancelled', $commission->user, ['commission' => $commission->uuid, 'amount' => $commission->amount, 'reason' => trim($reason)], actor: $admin);
    }
}
