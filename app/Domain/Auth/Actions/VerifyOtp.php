<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Auth\Data\DeviceInfo;
use App\Domain\Auth\Data\IssuedToken;
use App\Domain\Auth\Data\PhoneNumber;

/**
 * Connexion de l'application par code SMS (MOB-01) : vérifie le code puis crée le jeton de l'appareil.
 */
class VerifyOtp
{
    public function __construct(
        private readonly ConfirmPhoneOwnership $confirm,
        private readonly IssueDeviceToken $issueToken,
    ) {}

    public function handle(PhoneNumber $phone, string $code, DeviceInfo $device): IssuedToken
    {
        $user = $this->confirm->handle($phone, $code);

        return $this->issueToken->handle($user, $device, 'otp', $user->wasRecentlyCreated);
    }
}
