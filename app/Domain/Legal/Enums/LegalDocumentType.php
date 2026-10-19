<?php

namespace App\Domain\Legal\Enums;

/**
 * Textes que le candidat accepte à l'inscription (MOB-04) et que le site publie (WEB-07).
 */
enum LegalDocumentType: string
{
    case Charte = 'charte';
    case Conditions = 'conditions';
    case Confidentialite = 'confidentialite';

    public function label(): string
    {
        return __("legal.types.{$this->value}");
    }
}
