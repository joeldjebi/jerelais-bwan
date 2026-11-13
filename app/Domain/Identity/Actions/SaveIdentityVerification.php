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
 * Vérification d'identité depuis l'application (MOB-01), champs et fichiers dans la même requête.
 *
 * - submit() : POST /identity, tout est fourni ; le dossier part aussitôt en vérification.
 * - update() : PUT /identity, seuls les éléments envoyés changent ; si le dossier devient complet, il part en vérification.
 *
 * Tout ou rien : chaque fichier passe l'antivirus avant que quoi que ce soit soit enregistré.
 * Modifiable tant que le dossier est à compléter (draft) ou refusé (rejected).
 *
 * @phpstan-type IdentityDetails array{commune?: Commune|null, localite?: Localite|null, document_type?: IdentityDocumentType|null, last_name?: string|null, first_names?: string|null, gender?: string|null, birth_date?: string|null}
 */
class SaveIdentityVerification
{
    public function __construct(
        private readonly SaveIdentityDetails $saveDetails,
        private readonly StoreIdentityFile $storeFile,
        private readonly SubmitIdentityVerification $submit,
        private readonly VirusScanner $scanner,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  IdentityDetails  $details
     * @param  array<string, UploadedFile>  $files  fichiers par type (recto, verso, page, selfie)
     *
     * @throws IdentityLocked dossier en vérification ou déjà vérifié
     * @throws ValidationException fichier refusé par l'antivirus
     * @throws VirusScanUnavailable antivirus indisponible
     * @throws IdentityIncomplete élément manquant
     */
    public function submit(IdentityVerification $verification, array $details, array $files): IdentityVerification
    {
        $this->save($verification, $details, $files);

        return $this->submit->handle($verification->refresh());
    }

    /**
     * @param  IdentityDetails  $details
     * @param  array<string, UploadedFile>  $files
     *
     * @throws IdentityLocked
     * @throws ValidationException
     * @throws VirusScanUnavailable
     */
    public function update(IdentityVerification $verification, array $details, array $files): IdentityVerification
    {
        $this->save($verification, $details, $files);
        $this->submit->submitIfComplete($verification->refresh());

        return $verification->refresh();
    }

    /**
     * @param  IdentityDetails  $details
     * @param  array<string, UploadedFile>  $files
     */
    private function save(IdentityVerification $verification, array $details, array $files): void
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

        if ($details !== []) {
            $this->saveDetails->handle($verification, $details, autoSubmit: false);
        }

        foreach ($files as $kind => $file) {
            $this->storeFile->handle($verification->refresh(), IdentityFileKind::from($kind), $file, autoSubmit: false);
        }
    }
}
