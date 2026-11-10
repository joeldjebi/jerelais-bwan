<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Access\Enums\Role;
use App\Domain\Audit\AuditLogger;
use App\Domain\Auth\Enums\UserStatus;
use App\Domain\Auth\Exceptions\AuthenticationRefused;
use App\Domain\Auth\Support\RegistrationTokens;
use App\Domain\Candidature\Actions\SaveCandidatureStep;
use App\Domain\Candidature\Actions\StartCandidature;
use App\Domain\Candidature\Enums\CandidatureStep;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Inscription depuis l'application, étape 2 (MOB-01) : identité et code PIN.
 * Crée le compte « candidat » au statut « en attente » : aucune connexion possible tant que l'équipe
 * ne l'a pas validé dans la console (ReviewRegistration). L'identité pré-remplit la candidature.
 */
class CompleteRegistration
{
    public function __construct(
        private readonly StartCandidature $startCandidature,
        private readonly SaveCandidatureStep $saveStep,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{last_name: string, first_names: string, gender: string, birth_date: string}  $identity
     *
     * @throws AuthenticationRefused jeton d'inscription inconnu ou expiré, numéro inscrit entre-temps
     */
    public function handle(string $registrationToken, array $identity, string $pin): User
    {
        $phone = RegistrationTokens::phoneFor($registrationToken)
            ?? throw new AuthenticationRefused(__('auth.registration_token_invalid'));

        VerifyRegistrationCode::ensureAvailable($phone);

        $user = DB::transaction(function () use ($phone, $identity, $pin): User {
            $user = (new User([
                'name' => trim($identity['first_names'].' '.$identity['last_name']),
                'status' => UserStatus::Pending,
            ]))->setPhoneNumber($phone);
            $user->password = $pin;
            $user->save();
            $user->assignRole(Role::Candidat->value);

            $this->saveStep->handle($this->startCandidature->handle($user), CandidatureStep::Identite, $identity);
            $this->audit->record('auth.account.registered', $user, ['status' => UserStatus::Pending->value], actor: $user);

            return $user;
        });

        RegistrationTokens::revoke($registrationToken);

        return $user;
    }
}
