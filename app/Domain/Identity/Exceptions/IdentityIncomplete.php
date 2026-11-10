<?php

namespace App\Domain\Identity\Exceptions;

use RuntimeException;

/**
 * Envoi refusé : il manque des éléments (commune, type de pièce, fichiers).
 */
class IdentityIncomplete extends RuntimeException
{
    /**
     * @param  array<string, string>  $missing  élément manquant => message à afficher
     */
    public function __construct(public readonly array $missing)
    {
        parent::__construct(__('Vérification d’identité incomplète.'));
    }
}
