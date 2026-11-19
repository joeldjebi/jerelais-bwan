<?php

namespace App\Domain\Gains\Enums;

enum PaymentMethod: string
{
    case Wave = 'wave';
    case OrangeMoney = 'orange_money';
    case MtnMomo = 'mtn_momo';
    case MoovMoney = 'moov_money';
    case Cash = 'cash';
    case Other = 'other';

    public function label(): string
    {
        return __("gains.methods.{$this->value}");
    }
}
