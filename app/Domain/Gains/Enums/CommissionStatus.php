<?php

namespace App\Domain\Gains\Enums;

enum CommissionStatus: string
{
    case Due = 'due';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __("gains.statuses.{$this->value}");
    }
}
