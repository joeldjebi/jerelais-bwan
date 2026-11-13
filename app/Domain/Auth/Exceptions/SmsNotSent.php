<?php

namespace App\Domain\Auth\Exceptions;

use RuntimeException;

/**
 * Le fournisseur de SMS n'a pas accepté le message (identifiants, réseau, refus).
 * Le message de l'exception ne contient jamais d'identifiant ni le corps de la requête.
 */
class SmsNotSent extends RuntimeException
{
    /**
     * Message à afficher à l'utilisateur.
     */
    public function userMessage(): string
    {
        return __('auth.sms_failed');
    }
}
