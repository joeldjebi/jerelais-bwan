<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Auth\Data\DeviceInfo;
use App\Domain\Auth\Data\IssuedToken;
use App\Domain\Auth\Data\PhoneNumber;
use App\Domain\Relais\Actions\EnrolAppRelais;

/**
 * Connexion de l'application par code SMS (MOB-01) : vérifie le code puis crée le jeton de l'appareil.
 * Réservée aux comptes existants (mot de passe oublié, nouvel appareil) : l'inscription passe par CompleteRegistration.
 */
class VerifyOtp
{
    public function __construct(
        private readonly ConfirmPhoneOwnership $confirm,
        private readonly IssueDeviceToken $issueToken,
        private readonly EnrolAppRelais $enrol,
    ) {}

    public function handle(PhoneNumber $phone, string $code, DeviceInfo $device): IssuedToken
    {
        $user = $this->confirm->handle($phone, $code, createAccount: false);
        $this->enrol->ensure($user);

        return $this->issueToken->handle($user, $device, 'otp');
    }
}
