<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Auth\Data\DeviceInfo;
use App\Domain\Auth\Data\IssuedToken;
use App\Domain\Auth\Data\PhoneNumber;
use App\Domain\Auth\Exceptions\AuthenticationRefused;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Connexion de l'application par numéro et mot de passe (MOB-01), pour les Relais qui en ont défini un.
 */
class LoginWithPassword
{
    public function __construct(
        private readonly IssueDeviceToken $issueToken,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(PhoneNumber $phone, string $password, DeviceInfo $device): IssuedToken
    {
        $user = User::findByPhone($phone);

        if ($user === null || $user->password === null || ! Hash::check($password, $user->password)) {
            $this->audit->record('auth.login.failed', payload: ['method' => 'password', 'phone_ref' => substr($phone->hash(), 0, 12)]);

            throw AuthenticationRefused::invalidCredentials();
        }

        if (! $user->status->canSignIn()) {
            throw AuthenticationRefused::accountDisabled();
        }

        return $this->issueToken->handle($user, $device, 'password');
    }
}
