<?php

namespace App\Domain\Validation\Enums;

enum RejectionTarget: string
{
    case Relais = 'relais';
    case Candidature = 'candidature';
}
