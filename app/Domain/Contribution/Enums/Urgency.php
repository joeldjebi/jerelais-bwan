<?php

namespace App\Domain\Contribution\Enums;

/**
 * Niveau d'urgence choisi par le Relais (MOB-14). La file est triée par urgence puis ancienneté (COO-10).
 */
enum Urgency: string
{
    case Normal = 'normal';
    case Important = 'important';
    case Urgent = 'urgent';

    public function label(): string
    {
        return __("relais_items.urgencies.{$this->value}");
    }

    /**
     * Rang de tri : le plus urgent d'abord.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Urgent => 0,
            self::Important => 1,
            self::Normal => 2,
        };
    }

    /**
     * Paramètre du délai cible de traitement (COO-17).
     */
    public function slaSetting(): string
    {
        return $this === self::Urgent ? 'validation.sla_urgent_minutes' : 'validation.sla_normal_minutes';
    }
}
