<?php

namespace App\Domain\Auth\Enums;

enum UserStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Banned = 'banned';
    case Deleted = 'deleted';

    public function canSignIn(): bool
    {
        return $this === self::Active;
    }
}
