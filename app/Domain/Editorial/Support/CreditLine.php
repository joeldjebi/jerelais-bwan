<?php

namespace App\Domain\Editorial\Support;

use App\Domain\Contribution\Enums\CreditMode;
use App\Domain\Contribution\Models\RelaisItem;

/**
 * Crédit à afficher à l'antenne, selon le choix du Relais (§9.2).
 */
class CreditLine
{
    public static function for(RelaisItem $relais): string
    {
        return match ($relais->credit_mode) {
            CreditMode::Named => trim(($relais->relaisProfile?->candidature?->fullName() ?? '') ?: ($relais->relaisProfile->display_name ?? '')).' / JE RELAIS',
            CreditMode::Pseudonym => trim((string) $relais->credit_name).' / JE RELAIS',
            default => __('Un Relais de REFLET TV'),
        };
    }
}
