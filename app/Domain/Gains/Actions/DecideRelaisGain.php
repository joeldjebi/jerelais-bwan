<?php

namespace App\Domain\Gains\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Contribution\States\Archived;
use App\Domain\Contribution\States\Broadcast;
use App\Domain\Contribution\States\Selected;
use App\Domain\Contribution\States\Validated;
use App\Domain\Gains\Enums\CommissionKind;
use App\Domain\Gains\Enums\CommissionStatus;
use App\Domain\Gains\Exceptions\CommissionRefused;
use App\Domain\Gains\Models\Commission;
use App\Domain\Gains\Models\GainDecision;
use App\Domain\Gains\Support\Wallet;
use App\Domain\Notification\Actions\NotifyUser;
use App\Domain\Notification\Data\PushMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Rémunération d'un relais validé, décidée par l'administration ou le superviseur (ADM-27) : montant libre, ou
 * pas de rémunération (motivée). La décision peut être corrigée ou annulée tant que la somme n'est pas retirée.
 * Chaque décision est historisée et tracée ; le Relais est prévenu quand son portefeuille change.
 */
class DecideRelaisGain
{
    public function __construct(
        private readonly Wallet $wallet,
        private readonly AuditLogger $audit,
        private readonly NotifyUser $notify,
    ) {}

    /**
     * @throws CommissionRefused relais non validé, déjà rémunéré, montant invalide
     */
    public function credit(RelaisItem $relais, User $decider, int $amount, ?string $note = null): Commission
    {
        $this->ensureValidated($relais);
        $this->ensureAmount($amount);

        $commission = DB::transaction(function () use ($relais, $decider, $amount, $note): Commission {
            if ($this->current($relais) !== null) {
                throw new CommissionRefused(__('gains.errors.already_rewarded'));
            }

            $commission = Commission::query()->create([
                'user_id' => $relais->user_id,
                'relais_item_id' => $relais->id,
                'kind' => CommissionKind::Reward,
                'amount' => $amount,
                'status' => CommissionStatus::Due,
            ]);

            $this->record($relais, $decider, 'credited', $commission, $amount, null, $note);

            return $commission;
        });

        $this->tell($relais, __('notifications.gains.earned.title'), __('notifications.gains.earned.body', ['amount' => Wallet::fcfa($amount), 'title' => $this->title($relais)]), 'gains.earned', $commission);

        return $commission;
    }

    /**
     * @throws CommissionRefused relais non validé, déjà rémunéré, motif absent
     */
    public function decline(RelaisItem $relais, User $decider, string $reason): void
    {
        $this->ensureValidated($relais);
        $this->ensureReason($reason);

        if ($this->current($relais) !== null) {
            throw new CommissionRefused(__('gains.errors.already_rewarded'));
        }

        $this->record($relais, $decider, 'declined', null, null, null, $reason);
    }

    /**
     * @throws CommissionRefused pas de rémunération en cours, montant invalide, somme déjà retirée
     */
    public function amend(RelaisItem $relais, User $decider, int $amount, string $reason): Commission
    {
        $this->ensureAmount($amount);
        $this->ensureReason($reason);

        $commission = DB::transaction(function () use ($relais, $decider, $amount, $reason): Commission {
            $commission = $this->current($relais) ?? throw new CommissionRefused(__('gains.errors.not_rewarded'));
            $previous = $commission->amount;

            if ($commission->status !== CommissionStatus::Due) {
                throw new CommissionRefused(__('gains.errors.amend_withdrawn'));
            }

            // Une baisse ne peut pas reprendre une somme déjà retirée ou en cours de retrait.
            if ($amount < $previous && $this->wallet->summary($relais->user)['available'] < $previous - $amount) {
                throw new CommissionRefused(__('gains.errors.amend_withdrawn'));
            }

            $commission->forceFill(['amount' => $amount])->save();
            $this->record($relais, $decider, 'amended', $commission, $amount, $previous, $reason);

            return $commission;
        });

        $this->tell($relais, __('Rémunération corrigée'), __('La rémunération de « :title » est maintenant de :amount.', ['title' => $this->title($relais), 'amount' => Wallet::fcfa($amount)]), 'gains.updated', $commission);

        return $commission;
    }

    /**
     * @throws CommissionRefused pas de rémunération en cours, motif absent, somme déjà retirée
     */
    public function cancel(RelaisItem $relais, User $decider, string $reason): void
    {
        $this->ensureReason($reason);
        $commission = $this->current($relais) ?? throw new CommissionRefused(__('gains.errors.not_rewarded'));

        app(CancelCommission::class)->handle($commission, $decider, $reason);
        $this->record($relais, $decider, 'cancelled', $commission, null, $commission->amount, $reason);
    }

    /**
     * Rémunération en cours du relais (créditée, pas annulée).
     */
    public function current(RelaisItem $relais): ?Commission
    {
        return Commission::query()->where('relais_item_id', $relais->id)
            ->whereIn('status', [CommissionStatus::Due->value, CommissionStatus::Paid->value])
            ->latest('id')->first();
    }

    public static function isRewardable(RelaisItem $relais): bool
    {
        return $relais->state instanceof Validated || $relais->state instanceof Selected
            || $relais->state instanceof Broadcast || $relais->state instanceof Archived;
    }

    private function ensureValidated(RelaisItem $relais): void
    {
        if (! self::isRewardable($relais)) {
            throw new CommissionRefused(__('gains.errors.relais_not_validated'));
        }
    }

    private function ensureAmount(int $amount): void
    {
        if ($amount < 1 || $amount > 10_000_000) {
            throw new CommissionRefused(__('gains.errors.amount_invalid'));
        }
    }

    private function ensureReason(string $reason): void
    {
        if (trim($reason) === '') {
            throw new CommissionRefused(__('gains.errors.reason_required'));
        }
    }

    private function record(RelaisItem $relais, User $decider, string $decision, ?Commission $commission, ?int $amount, ?int $previous, ?string $reason): void
    {
        GainDecision::query()->create([
            'relais_item_id' => $relais->id,
            'commission_id' => $commission?->id,
            'decided_by' => $decider->id,
            'decision' => $decision,
            'amount' => $amount,
            'previous_amount' => $previous,
            'reason' => $reason === null ? null : (trim($reason) ?: null),
        ]);

        $this->audit->record("gains.{$decision}", $relais, array_filter([
            'amount' => $amount,
            'previous_amount' => $previous,
            'reason' => $reason === null ? null : (trim($reason) ?: null),
        ], fn (mixed $value): bool => $value !== null), actor: $decider, communeId: $relais->commune_id);
    }

    private function tell(RelaisItem $relais, string $title, string $body, string $type, Commission $commission): void
    {
        $this->notify->handle($relais->loadMissing('user')->user, new PushMessage($title, $body, ['type' => $type, 'uuid' => $commission->uuid, 'amount' => $commission->amount]), null, 'gains');
    }

    private function title(RelaisItem $relais): string
    {
        return (string) str($relais->title ?? '')->limit(40);
    }
}
