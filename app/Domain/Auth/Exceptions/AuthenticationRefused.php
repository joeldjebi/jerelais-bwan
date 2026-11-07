<?php

namespace App\Domain\Auth\Exceptions;

use RuntimeException;

/**
 * Connexion refusée. Le message est volontairement générique (pas d'indice sur l'existence du compte).
 */
class AuthenticationRefused extends RuntimeException
{
    public static function invalidCode(): self
    {
        return new self('Code invalide ou expiré.');
    }

    public static function invalidCredentials(): self
    {
        return new self('Identifiants incorrects.');
    }

    public static function accountDisabled(): self
    {
        return new self('Ce compte est suspendu. Contactez votre coordonnateur.');
    }

    /**
     * Les comptes de l'équipe passent par la console et sa double authentification, jamais par l'API.
     */
    public static function staffUseConsole(): self
    {
        return new self(__('auth.staff_use_console'));
    }
}
