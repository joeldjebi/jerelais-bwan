<?php

namespace App\Domain\Gains\Enums;

/**
 * Retrait demandé par le Relais depuis son portefeuille.
 */
enum PayoutStatus: string
{
    case Requested = 'requested';
    case Paid = 'paid';
    case Rejected = 'rejected';

    public function label(): string
    {
        return __("gains.payout_statuses.{$this->value}");
    }
}
