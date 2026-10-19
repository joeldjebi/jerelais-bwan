<?php

namespace App\Domain\Audit\Support;

/**
 * Retire du journal d'audit les données qui ne doivent jamais y figurer
 * (secrets, codes, numéros, positions précises, pièces d'identité).
 */
class PayloadSanitizer
{
    /** @var list<string> */
    private const FORBIDDEN_KEYS = [
        'password', 'password_confirmation', 'current_password', 'code', 'otp', 'token', 'secret',
        'phone', 'phone_number', 'two_factor_secret', 'two_factor_recovery_codes', 'recovery_code',
        'fcm_token', 'latitude', 'longitude', 'location', 'id_number', 'remember_token',
    ];

    /**
     * @param  array<array-key, mixed>  $payload
     * @return array<array-key, mixed>
     */
    public function clean(array $payload): array
    {
        $clean = [];

        foreach ($payload as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::FORBIDDEN_KEYS, true)) {
                $clean[$key] = '[retiré]';

                continue;
            }

            $clean[$key] = is_array($value) ? $this->clean($value) : $value;
        }

        return $clean;
    }
}
