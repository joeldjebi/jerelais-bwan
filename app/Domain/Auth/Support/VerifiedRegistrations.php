<?php

namespace App\Domain\Auth\Support;

use App\Domain\Auth\Data\PhoneNumber;
use Illuminate\Support\Facades\Cache;

/**
 * Numéros vérifiés par code SMS pendant l'inscription (MOB-01), le temps de saisir l'identité, la pièce et le PIN.
 * Valable 30 minutes, à usage unique : l'inscription consomme la vérification.
 */
class VerifiedRegistrations
{
    public const int TTL_MINUTES = 30;

    public static function remember(PhoneNumber $phone): void
    {
        Cache::put(self::key($phone), true, now()->addMinutes(self::TTL_MINUTES));
    }

    public static function isVerified(PhoneNumber $phone): bool
    {
        return Cache::get(self::key($phone)) === true;
    }

    public static function forget(PhoneNumber $phone): void
    {
        Cache::forget(self::key($phone));
    }

    private static function key(PhoneNumber $phone): string
    {
        return 'registration-verified:'.$phone->hash();
    }
}
