<?php

namespace App\Domain\Candidature\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Candidature\Enums\CandidatureStatus;
use App\Domain\Candidature\Models\Candidature;
use App\Models\User;

/**
 * Premier examen d'un dossier reçu : il passe « en cours d'examen » (MOB-06).
 */
class StartCandidatureReview
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(Candidature $candidature, User $reviewer): void
    {
        if ($candidature->status !== CandidatureStatus::Submitted) {
            return;
        }

        $candidature->forceFill(['status' => CandidatureStatus::UnderReview])->save();
        $this->audit->record('candidature.review_started', $candidature, actor: $reviewer, communeId: $candidature->commune_id);
    }
}
