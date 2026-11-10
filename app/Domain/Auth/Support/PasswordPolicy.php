<?php

namespace App\Domain\Auth\Support;

use App\Domain\Auth\Data\PhoneNumber;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Mot de passe de l'application mobile (MOB-01) : au moins 8 caractères, stocké haché dans `users.password`.
 *
 * Contre les essais en série, le numéro est bloqué lock_minutes après max_failures mots de passe erronés, toutes
 * adresses IP confondues. Un mot de passe oublié se redéfinit après une connexion par code SMS : ce jeton peut
 * le changer sans l'ancien pendant reset_window_minutes.
 */
class PasswordPolicy
{
    public const string SMS_VERIFIED_ABILITY = 'sms-verified';

    public static function minLength(): int
    {
        return (int) config('jerelais.app_password.min_length');
    }

    /**
     * Règles d'un nouveau mot de passe (à confirmer dans `password_confirmation`).
     *
     * @return list<string>
     */
    public static function rules(): array
    {
        return ['required', 'string', 'min:'.self::minLength(), 'max:200', 'confirmed'];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'password.min' => __('auth.password_min', ['min' => self::minLength()]),
            'password.confirmed' => __('auth.password_confirmation'),
        ];
    }

    public static function lockMinutes(): int
    {
        return (int) config('jerelais.app_password.lock_minutes');
    }

    public static function isLocked(PhoneNumber $phone): bool
    {
        return RateLimiter::tooManyAttempts(self::failureKey($phone), (int) config('jerelais.app_password.max_failures'));
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
     * Le jeton vient d'une connexion par code SMS récente : il peut redéfinir le mot de passe sans l'ancien.
     */
    public static function allowsResetWithoutCurrent(mixed $token): bool
    {
        return $token instanceof PersonalAccessToken
            && in_array(self::SMS_VERIFIED_ABILITY, $token->abilities ?? [], true)
            && $token->created_at !== null
            && $token->created_at->gt(now()->subMinutes((int) config('jerelais.app_password.reset_window_minutes')));
    }

    private static function failureKey(PhoneNumber $phone): string
    {
        return 'password-failures:'.$phone->hash();
    }
}
