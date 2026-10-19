<?php

namespace App\Domain\Media\Exceptions;

use RuntimeException;

/**
 * Média refusé avant l'envoi (taille, nombre, format, relais déjà envoyé).
 */
class MediaRefused extends RuntimeException
{
    public function __construct(public readonly string $field, string $message)
    {
        parent::__construct($message);
    }
}
