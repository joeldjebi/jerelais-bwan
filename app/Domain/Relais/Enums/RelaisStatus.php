<?php

namespace App\Domain\Relais\Enums;

/**
 * Statut d'un Relais (COO-04, ADM-22).
 */
enum RelaisStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case StruckOff = 'struck_off';

    public function label(): string
    {
        return __("relais_profils.statuses.{$this->value}");
    }

    public function canSubmit(): bool
    {
        return $this === self::Active;
    }
}
