<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Auth\Data\PhoneNumber;
use App\Domain\Auth\Enums\UserStatus;
use App\Domain\Auth\Exceptions\AuthenticationRefused;
use App\Domain\Auth\Support\VerifiedRegistrations;
use App\Domain\Candidature\Actions\SaveCandidatureStep;
use App\Domain\Candidature\Actions\StartCandidature;
use App\Domain\Candidature\Enums\CandidatureStep;
use App\Domain\Relais\Actions\EnrolAppRelais;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Inscription depuis l'application, dernière étape (MOB-01) : le mot de passe.
 * Le numéro doit avoir été vérifié par code SMS dans les 30 dernières minutes (VerifyRegistrationCode).
 * Crée un compte actif et en fait un Relais : connexion et envoi de relais possibles tout de suite.
 * Nom, prénoms, genre et date de naissance peuvent être fournis ici ; le reste (pièce, commune, selfie, photo)
 * se complète plus tard dans le profil (/identity),
 * et n'est exigé que pour recevoir ses commissions.
 */
class CompleteRegistration
{
    public function __construct(
        private readonly EnrolAppRelais $enrol,
        private readonly StartCandidature $startCandidature,
        private readonly SaveCandidatureStep $saveStep,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, string>  $identity  facultatif : last_name, first_names, gender, birth_date (AAAA-MM-JJ), déjà validés
     *
     * @throws AuthenticationRefused numéro non vérifié (ou vérification expirée), numéro inscrit entre-temps
     */
    public function handle(PhoneNumber $phone, string $password, array $identity = []): User
    {
        if (! VerifiedRegistrations::isVerified($phone)) {
            throw new AuthenticationRefused(__('auth.registration_not_verified'));
        }

        VerifyRegistrationCode::ensureAvailable($phone);

        $user = DB::transaction(function () use ($phone, $password, $identity): User {
            $user = (new User(['status' => UserStatus::Active]))->setPhoneNumber($phone);
            $user->password = $password;
            $user->save();

            // Identité saisie à l'inscription : enregistrée avant le profil Relais, dont le nom affiché en découle.
            if ($identity !== []) {
                $candidature = $this->saveStep->handle($this->startCandidature->handle($user), CandidatureStep::Identite, $identity);
                $name = trim(($candidature->first_names ?? '').' '.($candidature->last_name ?? ''));

                if ($name !== '') {
                    $user->forceFill(['name' => $name])->save();
                }
            }

            $this->enrol->handle($user);
            $this->audit->record('auth.account.registered', $user, actor: $user);

            return $user;
        });

        VerifiedRegistrations::forget($phone);

        return $user;
    }
}
