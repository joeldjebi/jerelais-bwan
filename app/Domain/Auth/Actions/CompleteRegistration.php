<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Auth\Data\PhoneNumber;
use App\Domain\Auth\Enums\UserStatus;
use App\Domain\Auth\Exceptions\AuthenticationRefused;
use App\Domain\Auth\Support\VerifiedRegistrations;
use App\Domain\Relais\Actions\EnrolAppRelais;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Inscription depuis l'application, dernière étape (MOB-01) : le mot de passe.
 * Le numéro doit avoir été vérifié par code SMS dans les 30 dernières minutes (VerifyRegistrationCode).
 * Crée un compte actif et en fait un Relais : connexion et envoi de relais possibles tout de suite.
 * Le reste (identité, pièce, commune, selfie, photo) se complète plus tard dans le profil (/identity),
 * et n'est exigé que pour recevoir ses commissions.
 */
class CompleteRegistration
{
    public function __construct(
        private readonly EnrolAppRelais $enrol,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @throws AuthenticationRefused numéro non vérifié (ou vérification expirée), numéro inscrit entre-temps
     */
    public function handle(PhoneNumber $phone, string $password): User
    {
        if (! VerifiedRegistrations::isVerified($phone)) {
            throw new AuthenticationRefused(__('auth.registration_not_verified'));
        }

        VerifyRegistrationCode::ensureAvailable($phone);

        $user = DB::transaction(function () use ($phone, $password): User {
            $user = (new User(['status' => UserStatus::Active]))->setPhoneNumber($phone);
            $user->password = $password;
            $user->save();

            $this->enrol->handle($user);
            $this->audit->record('auth.account.registered', $user, actor: $user);

            return $user;
        });

        VerifiedRegistrations::forget($phone);

        return $user;
    }
}
