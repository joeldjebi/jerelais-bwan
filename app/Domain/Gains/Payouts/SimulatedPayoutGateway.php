<?php

namespace App\Domain\Gains\Payouts;

use App\Domain\Gains\Contracts\PayoutGateway;
use App\Domain\Gains\Models\Payout;
use Illuminate\Support\Str;

/**
 * Retrait simulé : accepté aussitôt avec une référence « SIM-… », sans aucun envoi d'argent. En attendant le
 * branchement d'un vrai prestataire de paiement.
 */
class SimulatedPayoutGateway implements PayoutGateway
{
    public function name(): string
    {
        return 'simulation';
    }

    public function send(Payout $payout): ?string
    {
        return 'SIM-'.Str::upper(Str::random(10));
    }
}
