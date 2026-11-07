<?php

namespace App\Domain\Contribution\Enums;

use App\Domain\Contribution\States\ComplementRequested;
use App\Domain\Contribution\States\Rejected;
use App\Domain\Contribution\States\RelaisState;
use App\Domain\Contribution\States\Sensitive;
use App\Domain\Contribution\States\Validated;

/**
 * Décisions du coordonnateur sur un relais (COO-14).
 */
enum RelaisDecision: string
{
    case Validate = 'validate';
    case RequestComplement = 'complement';
    case Reject = 'reject';
    case Sensitive = 'sensitive';

    /**
     * @return class-string<RelaisState>
     */
    public function state(): string
    {
        return match ($this) {
            self::Validate => Validated::class,
            self::RequestComplement => ComplementRequested::class,
            self::Reject => Rejected::class,
            self::Sensitive => Sensitive::class,
        };
    }

    public function auditAction(): string
    {
        return 'relais.'.match ($this) {
            self::Validate => 'validated',
            self::RequestComplement => 'complement_requested',
            self::Reject => 'rejected',
            self::Sensitive => 'flagged_sensitive',
        };
    }
}
