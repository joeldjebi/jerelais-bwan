<?php

namespace App\Domain\Candidature\Exceptions;

use RuntimeException;

/**
 * Le dossier n'est plus modifiable par le candidat (déposé, en examen ou décidé).
 */
class CandidatureLocked extends RuntimeException
{
    public static function make(): self
    {
        return new self(__('candidature.locked'));
    }
}
