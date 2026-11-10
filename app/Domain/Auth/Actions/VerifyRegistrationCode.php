<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Auth\Data\PhoneNumber;
use App\Domain\Auth\Exceptions\AuthenticationRefused;
use App\Domain\Auth\Support\RegistrationTokens;
use App\Models\User;

/**
 * Inscription depuis l'application, étape 1 (MOB-01) : vérifie le code SMS du numéro à inscrire.
 * Aucun compte n'est encore créé : renvoie un jeton d'inscription à usage unique pour l'étape 2 (CompleteRegistration).
 */
class VerifyRegistrationCode
{
    public function __construct(private readonly ConfirmPhoneOwnership $confirm) {}

    /**
     * @throws AuthenticationRefused numéro déjà inscrit, code faux ou expiré
     */
    public function handle(PhoneNumber $phone, string $code): string
    {
        self::ensureAvailable($phone);

        $this->confirm->consumeCode($phone, $code);

        return RegistrationTokens::issue($phone);
    }

    /**
     * @throws AuthenticationRefused le numéro a déjà un compte
     */
    public static function ensureAvailable(PhoneNumber $phone): void
    {
        if (User::findByPhone($phone) !== null) {
            throw AuthenticationRefused::phoneAlreadyRegistered();
        }
    }
}
