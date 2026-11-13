<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Identity\Enums\IdentityFileKind;
use App\Domain\Identity\Exceptions\IdentityIncomplete;
use App\Domain\Identity\Exceptions\IdentityLocked;
use App\Domain\Identity\Models\IdentityDocumentType;
use App\Domain\Identity\Models\IdentityVerification;
use App\Domain\Media\Contracts\VirusScanner;
use App\Domain\Media\Exceptions\VirusScanUnavailable;
use App\Domain\Territoire\Models\Commune;
use App\Domain\Territoire\Models\Localite;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Vérification d'identité en un seul envoi (MOB-01) : identité, commune, type de pièce et fichiers.
 * Tout ou rien : chaque fichier passe l'antivirus avant que quoi que ce soit soit enregistré ; le dossier part
 * ensuite directement en vérification (« submitted »). Un dossier refusé peut être renvoyé de la même façon.
 */
class SubmitIdentityAtOnce
{
    public function __construct(
        private readonly SaveIdentityDetails $saveDetails,
        private readonly StoreIdentityFile $storeFile,
        private readonly SubmitIdentityVerification $submit,
        private readonly VirusScanner $scanner,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{commune?: Commune|null, localite?: Localite|null, document_type?: IdentityDocumentType|null, last_name?: string|null, first_names?: string|null, gender?: string|null, birth_date?: string|null}  $details
     * @param  array<string, UploadedFile>  $files  fichiers par type (recto, verso, page, selfie, profile_photo)
     *
     * @throws IdentityLocked vérification déjà envoyée ou vérifiée
     * @throws ValidationException fichier refusé par l'antivirus
     * @throws VirusScanUnavailable antivirus indisponible
     * @throws IdentityIncomplete élément manquant (ne devrait pas arriver après validation de la requête)
     */
    public function handle(IdentityVerification $verification, array $details, array $files): IdentityVerification
    {
        if (! $verification->status->isEditable()) {
            throw IdentityLocked::make();
        }

        foreach ($files as $kind => $file) {
            $threat = $this->scanner->scan((string) $file->getRealPath());

            if ($threat !== null) {
                $this->audit->record('identity.file.infected', $verification, ['kind' => $kind, 'threat' => $threat], actor: $verification->user);

                throw ValidationException::withMessages([$kind => __('identity.errors.infected', ['kind' => IdentityFileKind::from($kind)->label()])]);
            }
        }

        $this->saveDetails->handle($verification, $details, autoSubmit: false);

        foreach ($files as $kind => $file) {
            $this->storeFile->handle($verification->refresh(), IdentityFileKind::from($kind), $file, autoSubmit: false);
        }

        return $this->submit->handle($verification->refresh());
    }
}
