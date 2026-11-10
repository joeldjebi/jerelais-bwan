<?php

namespace App\Domain\Auth\Exceptions;

use RuntimeException;

/**
 * Connexion refusée. Le message est volontairement générique (pas d'indice sur l'existence du compte),
 * sauf pour l'inscription et la connexion par code SMS de l'application, où l'utilisateur doit savoir
 * s'il faut s'inscrire ou se connecter.
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
     * Connexion par code SMS d'un numéro sans compte : l'application renvoie vers l'inscription.
     */
    public static function unknownAccount(): self
    {
        return new self(__('auth.unknown_account'));
    }

    /**
     * Inscription d'un numéro qui a déjà un compte : l'application renvoie vers la connexion.
     */
    public static function phoneAlreadyRegistered(): self
    {
        return new self(__('auth.phone_already_registered'));
    }

    /**
     * Les comptes de l'équipe passent par la console et sa double authentification, jamais par l'API.
     */
    public static function staffUseConsole(): self
    {
        return new self(__('auth.staff_use_console'));
    }
}
