<?php

namespace App\Domain\Candidature\Exceptions;

use RuntimeException;

/**
 * Dépôt refusé : il manque des éléments au dossier.
 */
class CandidatureIncomplete extends RuntimeException
{
    /**
     * @param  array<string, string>  $missing  élément manquant => message
     */
    public function __construct(public readonly array $missing)
    {
        parent::__construct(__('candidature.incomplete'));
    }
}
