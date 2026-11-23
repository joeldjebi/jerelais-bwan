<?php

namespace App\Domain\Gains\Payouts;

use App\Domain\Gains\Contracts\PayoutGateway;
use App\Domain\Gains\Models\Payout;

/**
 * Pas d'envoi automatique : le retrait attend d'être payé (ou refusé) depuis la console.
 */
class ManualPayoutGateway implements PayoutGateway
{
    public function name(): string
    {
        return 'manual';
    }

    public function send(Payout $payout): ?string
    {
        return null;
    }
}
