<?php

namespace App\Domain\Candidature\Enums;

/**
 * Décisions du coordonnateur sur un dossier (COO-02).
 */
enum CandidatureDecision: string
{
    case Accept = 'accept';
    case Reject = 'reject';
    case Hold = 'hold';
    case RequestComplement = 'complement';

    public function resultingStatus(): CandidatureStatus
    {
        return match ($this) {
            self::Accept => CandidatureStatus::Accepted,
            self::Reject => CandidatureStatus::Rejected,
            self::Hold => CandidatureStatus::OnHold,
            self::RequestComplement => CandidatureStatus::ComplementRequested,
        };
    }

    public function auditAction(): string
    {
        return match ($this) {
            self::Accept => 'candidature.accepted',
            self::Reject => 'candidature.rejected',
            self::Hold => 'candidature.on_hold',
            self::RequestComplement => 'candidature.complement_requested',
        };
    }
}
