<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Access\Enums\Role;
use App\Domain\Audit\AuditLogger;
use App\Domain\Auth\Data\DeviceInfo;
use App\Domain\Auth\Data\IssuedToken;
use App\Domain\Auth\Data\PhoneNumber;
use App\Domain\Auth\Enums\UserStatus;
use App\Domain\Auth\Exceptions\AuthenticationRefused;
use App\Domain\Auth\Models\OtpCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Vérifie le code SMS et connecte l'appareil (MOB-01).
 * Première connexion d'un numéro = création du compte avec le rôle « candidat ».
 * Au-delà de 5 essais, le code est annulé même si la bonne valeur est ensuite saisie.
 */
class VerifyOtp
{
    public function __construct(
        private readonly IssueDeviceToken $issueToken,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(PhoneNumber $phone, string $code, DeviceInfo $device): IssuedToken
    {
        $phoneHash = $phone->hash();

        $outcome = DB::transaction(function () use ($phoneHash, $code): string {
            $otp = OtpCode::query()->usable()->where('phone_hash', $phoneHash)->latest('id')->lockForUpdate()->first();

            if ($otp === null) {
                return 'missing';
            }

            $otp->increment('attempts');

            if ($otp->attempts > (int) config('jerelais.otp.max_attempts')) {
                $otp->update(['consumed_at' => now()]);
                $this->audit->record('auth.otp.locked', payload: ['phone_ref' => substr($phoneHash, 0, 12)]);

                return 'locked';
            }

            if (! $otp->matches($code)) {
                $this->audit->record('auth.otp.failed', payload: ['phone_ref' => substr($phoneHash, 0, 12), 'attempt' => $otp->attempts]);

                return 'mismatch';
            }

            $otp->update(['consumed_at' => now()]);

            return 'valid';
        });

        if ($outcome !== 'valid') {
            throw AuthenticationRefused::invalidCode();
        }

        return DB::transaction(function () use ($phone, $device): IssuedToken {
            $user = User::findByPhone($phone);
            $isNewUser = $user === null;

            if ($user === null) {
                $user = (new User(['status' => UserStatus::Active]))->setPhoneNumber($phone);
                $user->save();
                $user->assignRole(Role::Candidat->value);
                $this->audit->record('auth.account.created', $user, actor: $user);
            }

            if (! $user->status->canSignIn()) {
                throw AuthenticationRefused::accountDisabled();
            }

            return $this->issueToken->handle($user, $device, 'otp', $isNewUser);
        });
    }
}
