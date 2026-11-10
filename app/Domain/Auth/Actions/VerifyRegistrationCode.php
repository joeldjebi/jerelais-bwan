<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Auth\Data\PhoneNumber;
use App\Domain\Auth\Exceptions\AuthenticationRefused;
use App\Domain\Auth\Support\VerifiedRegistrations;
use App\Models\User;

/**
 * Inscription depuis l'application, étape 1 (MOB-01) : vérifie le code SMS du numéro à inscrire.
 * Aucun compte n'est encore créé : le numéro est retenu comme vérifié 30 minutes pour l'étape suivante (CompleteRegistration).
 */
class VerifyRegistrationCode
{
    public function __construct(private readonly ConfirmPhoneOwnership $confirm) {}

    /**
     * @throws AuthenticationRefused numéro déjà inscrit, code faux ou expiré
     */
    public function handle(PhoneNumber $phone, string $code): void
    {
        self::ensureAvailable($phone);

        $this->confirm->consumeCode($phone, $code);

        VerifiedRegistrations::remember($phone);
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
