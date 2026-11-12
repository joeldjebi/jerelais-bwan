<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Auth\Data\DeviceInfo;
use App\Domain\Auth\Data\IssuedToken;
use App\Domain\Auth\Data\PhoneNumber;
use App\Domain\Auth\Exceptions\AuthenticationRefused;
use App\Domain\Auth\Support\PasswordPolicy;
use App\Domain\Relais\Actions\EnrolAppRelais;
use App\Models\User;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Hash;

/**
 * Connexion de l'application par numéro et mot de passe (MOB-01).
 * Après 5 mots de passe erronés sur un numéro, quelle que soit l'adresse IP, le numéro est bloqué 15 minutes.
 */
class LoginWithPassword
{
    public function __construct(
        private readonly IssueDeviceToken $issueToken,
        private readonly EnrolAppRelais $enrol,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Connexion de l'application : vérifie le mot de passe puis crée le jeton de l'appareil.
     *
     * @throws ThrottleRequestsException numéro bloqué après trop de mots de passe erronés
     */
    public function handle(PhoneNumber $phone, string $password, DeviceInfo $device): IssuedToken
    {
        return $this->issueToken->handle($this->verify($phone, $password), $device, 'password');
    }

    /**
     * Vérification commune à l'application et au site : numéro, mot de passe, blocage, statut du compte.
     * Un ancien compte « candidat » devient Relais, comme les inscrits d'aujourd'hui.
     *
     * @throws ThrottleRequestsException numéro bloqué après trop de mots de passe erronés
     * @throws AuthenticationRefused identifiants incorrects ou compte suspendu
     */
    public function verify(PhoneNumber $phone, string $password): User
    {
        if (PasswordPolicy::isLocked($phone)) {
            throw new ThrottleRequestsException(__('auth.password_locked', ['minutes' => PasswordPolicy::lockMinutes()]));
        }

        $user = User::findByPhone($phone);

        if ($user === null || $user->password === null || ! Hash::check($password, $user->password)) {
            PasswordPolicy::recordFailure($phone);
            $this->audit->record('auth.login.failed', payload: ['method' => 'password', 'phone_ref' => substr($phone->hash(), 0, 12)]);

            throw AuthenticationRefused::invalidCredentials();
        }

        PasswordPolicy::clearFailures($phone);

        if (! $user->status->canSignIn()) {
            throw AuthenticationRefused::accountDisabled();
        }

        $this->enrol->ensure($user);

        return $user;
    }
}
