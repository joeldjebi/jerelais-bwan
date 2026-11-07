<?php

namespace App\Domain\Media\Exceptions;

use RuntimeException;

/**
 * Fichier refusé : l'antivirus y a détecté une menace.
 */
class InfectedFile extends RuntimeException
{
    public function __construct(public readonly string $threat)
    {
        parent::__construct(__('media.infected'));
    }
}
