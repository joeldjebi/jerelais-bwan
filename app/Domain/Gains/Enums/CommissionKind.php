<?php

namespace App\Domain\Gains\Enums;

enum CommissionKind: string
{
    case Validated = 'validated';
    case Broadcast = 'broadcast';

    /** Rémunération décidée par l'administration ou le superviseur, montant libre. */
    case Reward = 'reward';

    public function label(): string
    {
        return __("gains.kinds.{$this->value}");
    }

    /**
     * Paramètre qui fixe le montant (vide : pas de commission).
     */
    public function amountSetting(): string
    {
        return "gains.amount_per_{$this->value}";
    }
}
