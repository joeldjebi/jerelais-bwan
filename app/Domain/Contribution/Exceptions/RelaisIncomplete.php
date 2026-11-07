<?php

namespace App\Domain\Contribution\Exceptions;

use RuntimeException;

/**
 * Envoi refusé : il manque des informations (catégorie, titre, questions de protection, commune…).
 */
class RelaisIncomplete extends RuntimeException
{
    /**
     * @param  array<string, string>  $missing
     */
    public function __construct(public readonly array $missing)
    {
        parent::__construct(__('relais_items.errors.missing'));
    }
}
