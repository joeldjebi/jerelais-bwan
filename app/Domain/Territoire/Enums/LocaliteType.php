<?php

namespace App\Domain\Territoire\Enums;

enum LocaliteType: string
{
    case Quartier = 'quartier';
    case Village = 'village';
    case SousPrefecture = 'sous_prefecture';

    public function label(): string
    {
        return __("territoire.localite_type.{$this->value}");
    }
}
