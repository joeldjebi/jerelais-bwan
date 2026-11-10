<?php

namespace App\Domain\Auth\Support;

use App\Domain\Auth\Data\PhoneNumber;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Règles du code PIN de l'application mobile (MOB-01) : 4 chiffres, stocké haché dans `users.password`.
 *
 * Avec 10 000 combinaisons, la protection repose sur le blocage par numéro : après max_failures PIN erronés,
 * toutes adresses IP confondues, le numéro est bloqué lock_minutes. Un PIN oublié se redéfinit après une
 * connexion par code SMS : ce jeton peut changer le PIN sans l'ancien pendant reset_window_minutes.
 */
class PinPolicy
{
    public const string SMS_VERIFIED_ABILITY = 'sms-verified';

    public static function length(): int
    {
        return (int) config('jerelais.pin.length');
    }

    public static function lockMinutes(): int
    {
        return (int) config('jerelais.pin.lock_minutes');
    }

    public static function isLocked(PhoneNumber $phone): bool
    {
        return RateLimiter::tooManyAttempts(self::failureKey($phone), (int) config('jerelais.pin.max_failures'));
    }

    public static function recordFailure(PhoneNumber $phone): void
    {
        RateLimiter::hit(self::failureKey($phone), self::lockMinutes() * 60);
    }

    public static function clearFailures(PhoneNumber $phone): void
    {
        RateLimiter::clear(self::failureKey($phone));
    }

    /**
     * Le jeton vient d'une connexion par code SMS récente : il peut redéfinir le PIN sans l'ancien.
     */
    public static function allowsResetWithoutCurrent(mixed $token): bool
    {
        return $token instanceof PersonalAccessToken
            && in_array(self::SMS_VERIFIED_ABILITY, $token->abilities ?? [], true)
            && $token->created_at !== null
            && $token->created_at->gt(now()->subMinutes((int) config('jerelais.pin.reset_window_minutes')));
    }

    private static function failureKey(PhoneNumber $phone): string
    {
        return 'pin-failures:'.$phone->hash();
    }
}
