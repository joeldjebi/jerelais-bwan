<?php

namespace App\Domain\AppelRelais\Enums;

/**
 * Zone ciblée par un appel à relais (§3.3, ADM-14).
 */
enum AppelTarget: string
{
    case National = 'national';
    case Communes = 'communes';
    case Regions = 'regions';
    case Radius = 'radius';

    public function label(): string
    {
        return __("appels_relais.targets.{$this->value}");
    }
}
