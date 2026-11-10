<?php

namespace App\Domain\Identity\Enums;

/**
 * Cycle de la vérification d'identité : à compléter, envoyée, vérifiée (éligible aux gains) ou refusée (à corriger).
 */
enum IdentityStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Verified = 'verified';
    case Rejected = 'rejected';

    public function label(): string
    {
        return __("identity.statuses.{$this->value}");
    }

    /**
     * Le Relais peut encore modifier ses informations et ses fichiers.
     */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Rejected], true);
    }
}
