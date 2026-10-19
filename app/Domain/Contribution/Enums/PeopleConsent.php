<?php

namespace App\Domain\Contribution\Enums;

/**
 * Accord des personnes filmées (MOB-15).
 */
enum PeopleConsent: string
{
    case Yes = 'yes';
    case No = 'no';
    case NotApplicable = 'not_applicable';

    public function label(): string
    {
        return __("relais_items.consents.{$this->value}");
    }
}
