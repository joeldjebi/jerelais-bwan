<?php

namespace App\Domain\Auth\Support;

use App\Domain\Auth\Data\PhoneNumber;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Jetons d'inscription de l'application (MOB-01) : prouvent que le numéro a été vérifié par code SMS,
 * le temps de saisir l'identité et le code PIN. Usage unique, valables 30 minutes, stockés hachés.
 */
class RegistrationTokens
{
    public const int TTL_MINUTES = 30;

    public static function issue(PhoneNumber $phone): string
    {
        $token = Str::random(64);

        Cache::put(self::key($token), $phone->e164, now()->addMinutes(self::TTL_MINUTES));

        return $token;
    }

    /**
     * Numéro vérifié correspondant au jeton, sans le consommer ; null si inconnu ou expiré.
     */
    public static function phoneFor(string $token): ?PhoneNumber
    {
        $e164 = Cache::get(self::key($token));

        return is_string($e164) ? PhoneNumber::tryParse($e164) : null;
    }

    public static function revoke(string $token): void
    {
        Cache::forget(self::key($token));
    }

    private static function key(string $token): string
    {
        return 'registration-token:'.hash('sha256', $token);
    }
}
