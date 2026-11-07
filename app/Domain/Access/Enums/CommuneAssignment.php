<?php

namespace App\Domain\Access\Enums;

enum CommuneAssignment: string
{
    case Titulaire = 'titulaire';
    case Suppleant = 'suppleant';
}
