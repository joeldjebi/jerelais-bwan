<?php

namespace App\Domain\Candidature\Events;

use App\Domain\Candidature\Models\Candidature;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dossier déposé : il rejoint la file du coordonnateur de la commune (COO-01).
 */
class CandidatureSubmitted
{
    use Dispatchable;

    public function __construct(public readonly Candidature $candidature, public readonly bool $isResubmission) {}
}
