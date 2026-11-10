<?php

namespace App\Domain\Auth\Enums;

enum UserStatus: string
{
    /** Inscrit depuis l'application, en attente de validation par l'équipe : connexion impossible. */
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
    case Banned = 'banned';
    case Deleted = 'deleted';

    public function canSignIn(): bool
    {
        return $this === self::Active;
    }
}
