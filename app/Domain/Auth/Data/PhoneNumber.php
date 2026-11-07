<?php

namespace App\Domain\Auth\Data;

use InvalidArgumentException;

/**
 * Numéro de téléphone normalisé au format E.164 (+225XXXXXXXXXX pour la Côte d'Ivoire).
 * Accepte les saisies courantes : « 07 07 07 07 07 », « +225 07… », « 00225… ».
 */
final readonly class PhoneNumber
{
    private function __construct(public string $e164) {}

    public static function parse(string $input): self
    {
        $digits = preg_replace('/[^\d+]/', '', $input) ?? '';

        if (str_starts_with($digits, '00')) {
            $digits = '+'.substr($digits, 2);
        }

        if (! str_starts_with($digits, '+')) {
            $local = ltrim($digits, '+');

            // Numéro ivoirien local à 10 chiffres (plan de numérotation 2021).
            if (preg_match('/^0\d{9}$/', $local) !== 1) {
                throw new InvalidArgumentException('Numéro de téléphone invalide.');
            }

            $digits = '+'.config('jerelais.phone.default_country_code').$local;
        }

        if (preg_match('/^\+225\d+$/', $digits) === 1 && preg_match('/^\+2250\d{9}$/', $digits) !== 1) {
            throw new InvalidArgumentException('Numéro ivoirien invalide : 10 chiffres attendus après +225.');
        }

        if (preg_match('/^\+[1-9]\d{7,14}$/', $digits) !== 1) {
            throw new InvalidArgumentException('Numéro de téléphone invalide.');
        }

        return new self($digits);
    }

    public static function tryParse(?string $input): ?self
    {
        try {
            return $input === null ? null : self::parse($input);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /**
     * Empreinte HMAC : permet la recherche et la détection de doublons sans déchiffrer.
     */
    public function hash(): string
    {
        return hash_hmac('sha256', $this->e164, (string) config('jerelais.phone.hash_key'));
    }

    /**
     * Version masquée pour l'affichage : +225 07 •• •• •• 42.
     */
    public function masked(): string
    {
        return substr($this->e164, 0, 6).' •• •• •• '.substr($this->e164, -2);
    }
}
