<?php

namespace App\Domain\Contribution\Enums;

/**
 * Crédit du Relais à l'antenne (MOB-15, §9.2) : transmis à la rédaction avec chaque média.
 */
enum CreditMode: string
{
    case Named = 'named';
    case Pseudonym = 'pseudonym';
    case Anonymous = 'anonymous';

    public function label(): string
    {
        return __("relais_items.credits.{$this->value}");
    }
}
