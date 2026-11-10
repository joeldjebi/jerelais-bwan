<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Access\Enums\Role;
use App\Domain\Audit\AuditLogger;
use App\Domain\Auth\Data\PhoneNumber;
use App\Domain\Auth\Enums\UserStatus;
use App\Domain\Auth\Exceptions\AuthenticationRefused;
use App\Domain\Auth\Support\VerifiedRegistrations;
use App\Domain\Candidature\Actions\SaveCandidatureStep;
use App\Domain\Candidature\Actions\StartCandidature;
use App\Domain\Candidature\Enums\CandidatureStep;
use App\Domain\Identity\Actions\StoreIdentityFile;
use App\Domain\Identity\Enums\IdentityDocumentType;
use App\Domain\Identity\Models\IdentityVerification;
use App\Domain\Media\Exceptions\InfectedFile;
use App\Domain\Media\Exceptions\VirusScanUnavailable;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Inscription depuis l'application, dernière étape (MOB-01) : identité, pièce d'identité et code PIN.
 * Le numéro doit avoir été vérifié par code SMS dans les 30 dernières minutes (VerifyRegistrationCode).
 * Crée le compte « candidat » au statut « en attente » (aucune connexion avant validation par l'équipe),
 * pré-remplit la candidature et ouvre la vérification d'identité avec le type de pièce et ses faces.
 * La commune, le selfie et la photo de profil se complètent plus tard (GET/PUT /identity).
 */
class CompleteRegistration
{
    public function __construct(
        private readonly StartCandidature $startCandidature,
        private readonly SaveCandidatureStep $saveStep,
        private readonly StoreIdentityFile $storeFile,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{last_name: string, first_names: string, gender: string, birth_date: string}  $identity
     * @param  array<string, UploadedFile>  $documentFiles  faces de la pièce, par type de fichier (recto, verso ou page)
     *
     * @throws AuthenticationRefused numéro non vérifié (ou vérification expirée), numéro inscrit entre-temps
     * @throws InfectedFile une face de la pièce est refusée par l'antivirus
     * @throws VirusScanUnavailable antivirus injoignable
     */
    public function handle(PhoneNumber $phone, array $identity, string $pin, IdentityDocumentType $documentType, array $documentFiles): User
    {
        if (! VerifiedRegistrations::isVerified($phone)) {
            throw new AuthenticationRefused(__('auth.registration_not_verified'));
        }

        VerifyRegistrationCode::ensureAvailable($phone);

        $verification = null;

        try {
            $user = DB::transaction(function () use ($phone, $identity, $pin, $documentType, $documentFiles, &$verification): User {
                $user = (new User([
                    'name' => trim($identity['first_names'].' '.$identity['last_name']),
                    'status' => UserStatus::Pending,
                ]))->setPhoneNumber($phone);
                $user->password = $pin;
                $user->save();
                $user->assignRole(Role::Candidat->value);

                $this->saveStep->handle($this->startCandidature->handle($user), CandidatureStep::Identite, $identity);

                $verification = new IdentityVerification(['document_type' => $documentType]);
                $verification->user()->associate($user);
                $verification->save();

                foreach ($documentType->sides() as $side) {
                    $this->storeFile->handle($verification, $side, $documentFiles[$side->value]);
                }

                $this->audit->record('auth.account.registered', $user, [
                    'status' => UserStatus::Pending->value,
                    'document_type' => $documentType->value,
                ], actor: $user);

                return $user;
            });
        } catch (\Throwable $exception) {
            // Transaction annulée : retirer les fichiers déjà copiés sur le disque privé.
            if ($verification instanceof IdentityVerification) {
                Storage::disk(StoreIdentityFile::DISK)->deleteDirectory('identite/'.$verification->uuid);
            }

            throw $exception;
        }

        VerifiedRegistrations::forget($phone);

        return $user;
    }
}
