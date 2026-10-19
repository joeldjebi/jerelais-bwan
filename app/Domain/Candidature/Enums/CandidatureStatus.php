<?php

namespace App\Domain\Candidature\Enums;

/**
 * États d'une candidature, tels que suivis par le candidat (MOB-06) et le coordonnateur (COO-02).
 */
enum CandidatureStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case OnHold = 'on_hold';
    case ComplementRequested = 'complement_requested';
    case Accepted = 'accepted';
    case Rejected = 'rejected';

    public function label(): string
    {
        return __("candidature.statuses.{$this->value}");
    }

    /**
     * Le candidat peut encore modifier son dossier.
     */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::ComplementRequested], true);
    }

    /**
     * Le dossier attend une décision du coordonnateur.
     */
    public function isPending(): bool
    {
        return in_array($this, [self::Submitted, self::UnderReview, self::OnHold], true);
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Accepted, self::Rejected], true);
    }
}
