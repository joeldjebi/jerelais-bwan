<?php

namespace App\Domain\Gains\Support;

use App\Domain\Gains\Enums\CommissionStatus;
use App\Domain\Gains\Enums\PayoutStatus;
use App\Domain\Gains\Models\Commission;
use App\Domain\Gains\Models\Payout;
use App\Domain\Settings\Settings;
use App\Models\User;

/**
 * Portefeuille d'un Relais (MOB-26) : gains crédités à chaque relais diffusé, moins les retraits demandés ou payés.
 */
class Wallet
{
    public function __construct(private readonly Settings $settings) {}

    /**
     * @return array{earned: int, available: int, pending: int, withdrawn: int}
     */
    public function summary(User $user): array
    {
        $earned = (int) Commission::query()->where('user_id', $user->id)
            ->whereIn('status', [CommissionStatus::Due->value, CommissionStatus::Paid->value])->sum('amount');
        $byStatus = Payout::query()->where('user_id', $user->id)->selectRaw('status, sum(amount) as total')->groupBy('status')->pluck('total', 'status');
        $pending = (int) ($byStatus[PayoutStatus::Requested->value] ?? 0);
        $withdrawn = (int) ($byStatus[PayoutStatus::Paid->value] ?? 0);

        return [
            'earned' => $earned,
            'available' => max(0, $earned - $pending - $withdrawn),
            'pending' => $pending,
            'withdrawn' => $withdrawn,
        ];
    }

    public function minimumWithdrawal(): int
    {
        return max(1, $this->settings->integer('gains.min_withdrawal'));
    }

    /**
     * Raisons qui empêchent un retrait (vide : retrait possible). Montant null : vérifie seulement le compte.
     *
     * @return array<string, string> clé => message prêt à afficher
     */
    public function blockers(User $user, ?int $amount = null): array
    {
        $summary = $this->summary($user);
        $blockers = [];

        if (! $user->isEligibleForGains()) {
            $blockers['identity'] = __('gains.errors.identity_not_verified_relais');
        }

        if (! $user->payoutAccount()->exists()) {
            $blockers['payout_account'] = __('gains.errors.payout_account_missing');
        }

        if ($summary['pending'] > 0) {
            $blockers['pending'] = __('gains.errors.withdrawal_pending');
        }

        if ($summary['available'] < $this->minimumWithdrawal()) {
            $blockers['balance'] = __('gains.errors.balance_below_minimum', ['min' => self::fcfa($this->minimumWithdrawal())]);
        }

        if ($amount !== null && ! isset($blockers['balance'])) {
            if ($amount < $this->minimumWithdrawal()) {
                $blockers['amount'] = __('gains.errors.amount_below_minimum', ['min' => self::fcfa($this->minimumWithdrawal())]);
            } elseif ($amount > $summary['available']) {
                $blockers['amount'] = __('gains.errors.amount_above_balance', ['available' => self::fcfa($summary['available'])]);
            }
        }

        return $blockers;
    }

    public static function fcfa(int $amount): string
    {
        return number_format($amount, 0, ',', ' ').' FCFA';
    }
}
