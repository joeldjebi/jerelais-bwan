<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Auth\Data\DeviceInfo;
use App\Domain\Auth\Data\IssuedToken;
use App\Domain\Auth\Data\PhoneNumber;
use App\Domain\Auth\Enums\UserStatus;
use App\Domain\Auth\Exceptions\AuthenticationRefused;
use App\Domain\Auth\Support\PinPolicy;
use App\Models\User;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Hash;

/**
 * Connexion de l'application par numéro et code PIN (MOB-01).
 * Après 5 PIN erronés sur un numéro, quelle que soit l'adresse IP, le numéro est bloqué 15 minutes.
 */
class LoginWithPassword
{
    public function __construct(
        private readonly IssueDeviceToken $issueToken,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @throws ThrottleRequestsException numéro bloqué après trop de PIN erronés
     */
    public function handle(PhoneNumber $phone, string $password, DeviceInfo $device): IssuedToken
    {
        if (PinPolicy::isLocked($phone)) {
            throw new ThrottleRequestsException(__('auth.pin_locked', ['minutes' => PinPolicy::lockMinutes()]));
        }

        $user = User::findByPhone($phone);

        if ($user === null || $user->password === null || ! Hash::check($password, $user->password)) {
            PinPolicy::recordFailure($phone);
            $this->audit->record('auth.login.failed', payload: ['method' => 'password', 'phone_ref' => substr($phone->hash(), 0, 12)]);

            throw AuthenticationRefused::invalidCredentials();
        }

        PinPolicy::clearFailures($phone);

        if ($user->status === UserStatus::Pending) {
            throw AuthenticationRefused::accountPending();
        }

        if (! $user->status->canSignIn()) {
            throw AuthenticationRefused::accountDisabled();
        }

        return $this->issueToken->handle($user, $device, 'password');
    }
}
