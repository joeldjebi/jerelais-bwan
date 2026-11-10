<?php

namespace App\Domain\Identity\Exceptions;

use RuntimeException;

/**
 * La vérification d'identité n'est plus modifiable par le Relais (envoyée ou vérifiée).
 */
class IdentityLocked extends RuntimeException
{
    public static function make(): self
    {
        return new self(__('identity.errors.locked'));
    }
}
