<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Auth\Data\DeviceInfo;
use App\Domain\Auth\Data\IssuedToken;
use App\Domain\Auth\Data\PhoneNumber;
use App\Domain\Auth\Exceptions\AuthenticationRefused;
use App\Models\User;

/**
 * Inscription depuis l'application (MOB-01) : numéro seul, code SMS, puis code PIN obligatoire.
 * Crée le compte « candidat » et le jeton de l'appareil ; le reste de l'API reste fermé tant que le PIN
 * n'est pas défini (middleware EnsurePinIsSet). Un numéro déjà inscrit est refusé : il faut se connecter.
 */
class RegisterWithPhone
{
    public function __construct(
        private readonly ConfirmPhoneOwnership $confirm,
        private readonly IssueDeviceToken $issueToken,
    ) {}

    /**
     * @throws AuthenticationRefused numéro déjà inscrit, code invalide ou compte refusé
     */
    public function handle(PhoneNumber $phone, string $code, DeviceInfo $device): IssuedToken
    {
        self::ensureAvailable($phone);

        $user = $this->confirm->handle($phone, $code);

        return $this->issueToken->handle($user, $device, 'otp', $user->wasRecentlyCreated);
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
