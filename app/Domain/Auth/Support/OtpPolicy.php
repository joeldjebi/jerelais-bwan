<?php

namespace App\Domain\Auth\Support;

use App\Domain\Auth\Data\PhoneNumber;
use InvalidArgumentException;

/**
 * Règles du code de vérification (MOB-01), dont le code fixe transitoire.
 *
 * OTP_FIXED_CODE (ex. « 1234 ») remplace le code aléatoire tant qu'aucun fournisseur de SMS n'est branché :
 * aucun SMS n'est envoyé et le code est communiqué aux testeurs par l'équipe. OTP_FIXED_CODE_NUMBERS restreint ce code
 * à une liste de numéros ; vide, il s'applique à tous. Toute personne connaissant le code et un numéro peut se connecter
 * à ce compte : mesure de transition, signalée par jerelais:check-production.
 */
class OtpPolicy
{
    public static function fixedCodeFor(PhoneNumber $phone): ?string
    {
        $code = self::fixedCode();

        if ($code === null) {
            return null;
        }

        $numbers = self::fixedCodeNumbers();

        return $numbers === [] || in_array($phone->e164, $numbers, true) ? $code : null;
    }

    public static function isFixedCodeEnabled(): bool
    {
        return self::fixedCode() !== null;
    }

    /**
     * Longueur du code attendu pour ce numéro (celle du code fixe s'il s'applique).
     */
    public static function lengthFor(?PhoneNumber $phone): int
    {
        $fixed = $phone === null ? null : self::fixedCodeFor($phone);

        return $fixed !== null ? strlen($fixed) : (int) config('jerelais.otp.length');
    }

    /**
     * @return array{0: int, 1: int} longueurs minimale et maximale acceptées à la vérification
     */
    public static function acceptedLengths(): array
    {
        $lengths = [(int) config('jerelais.otp.length')];

        if (($fixed = self::fixedCode()) !== null) {
            $lengths[] = strlen($fixed);
        }

        return [min($lengths), max($lengths)];
    }

    private static function fixedCode(): ?string
    {
        $code = trim((string) config('jerelais.otp.fixed_code'));

        if ($code === '') {
            return null;
        }

        if (preg_match('/^\d{4,8}$/', $code) !== 1) {
            throw new InvalidArgumentException('OTP_FIXED_CODE doit contenir de 4 à 8 chiffres.');
        }

        return $code;
    }

    /**
     * @return list<string> numéros au format international (+225…)
     */
    private static function fixedCodeNumbers(): array
    {
        $numbers = [];

        foreach (explode(',', (string) config('jerelais.otp.fixed_code_numbers')) as $raw) {
            $phone = PhoneNumber::tryParse(trim($raw));

            if ($phone !== null) {
                $numbers[] = $phone->e164;
            }
        }

        return $numbers;
    }
}
