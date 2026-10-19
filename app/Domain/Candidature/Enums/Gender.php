<?php

namespace App\Domain\Candidature\Enums;

enum Gender: string
{
    case Female = 'femme';
    case Male = 'homme';
    case Unspecified = 'non_precise';

    public function label(): string
    {
        return __("candidature.genders.{$this->value}");
    }
}
