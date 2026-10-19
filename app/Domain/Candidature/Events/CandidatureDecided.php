<?php

namespace App\Domain\Candidature\Events;

use App\Domain\Candidature\Enums\CandidatureDecision;
use App\Domain\Candidature\Models\Candidature;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Décision prise sur un dossier : le candidat est prévenu (COO-02, MOB-06).
 */
class CandidatureDecided
{
    use Dispatchable;

    public function __construct(public readonly Candidature $candidature, public readonly CandidatureDecision $decision) {}
}
