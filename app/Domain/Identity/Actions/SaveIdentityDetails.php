<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Candidature\Actions\SaveCandidatureStep;
use App\Domain\Candidature\Actions\StartCandidature;
use App\Domain\Candidature\Enums\CandidatureStep;
use App\Domain\Candidature\Exceptions\CandidatureLocked;
use App\Domain\Identity\Exceptions\IdentityLocked;
use App\Domain\Identity\Models\IdentityDocumentType;
use App\Domain\Identity\Models\IdentityVerification;
use App\Domain\Relais\Actions\CreateRelaisProfile;
use App\Domain\Relais\Models\RelaisProfile;
use App\Domain\Territoire\Models\Commune;
use App\Domain\Territoire\Models\Localite;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Profil du Relais pour ses commissions (MOB-01), en mise à jour partielle : seules les clés fournies changent.
 * Identité (nom, prénoms, sexe, date de naissance — sur le dossier du compte), commune ou localité, type de pièce.
 * Changer de type de pièce retire les faces devenues inutiles. Les données doivent avoir été validées.
 */
class SaveIdentityDetails
{
    public const array IDENTITY_FIELDS = ['last_name', 'first_names', 'gender', 'birth_date'];

    public function __construct(
        private readonly StartCandidature $startCandidature,
        private readonly SaveCandidatureStep $saveStep,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{commune?: Commune|null, localite?: Localite|null, document_type?: IdentityDocumentType|null, last_name?: string|null, first_names?: string|null, gender?: string|null, birth_date?: string|null}  $data
     */
    public function handle(IdentityVerification $verification, array $data): IdentityVerification
    {
        if (! $verification->status->isEditable()) {
            throw IdentityLocked::make();
        }

        DB::transaction(function () use ($verification, $data): void {
            if (array_key_exists('commune', $data) || array_key_exists('localite', $data)) {
                $localite = $data['localite'] ?? null;
                $commune = $data['commune'] ?? null;

                $verification->commune_id = $commune->id ?? $localite?->commune_id;
                $verification->localite_id = $localite?->id;
            }

            if (array_key_exists('document_type', $data)) {
                $verification->document_type = $data['document_type']?->slug;
                $verification->setRelation('documentType', $data['document_type']);
                $this->removeUnneededSides($verification);
            }

            $verification->save();

            $identity = array_intersect_key($data, array_flip(self::IDENTITY_FIELDS));

            if ($identity !== []) {
                $this->saveIdentity($verification, $identity);
            }

            $this->audit->record('identity.details.saved', $verification, ['fields' => array_keys($data)], actor: $verification->user);
        });

        return $verification->refresh();
    }

    /**
     * @param  array<string, string|null>  $identity
     */
    private function saveIdentity(IdentityVerification $verification, array $identity): void
    {
        $user = $verification->user;

        try {
            $candidature = $this->saveStep->handle($this->startCandidature->handle($user), CandidatureStep::Identite, $identity);
        } catch (CandidatureLocked) {
            // Relais recruté par candidature : son identité a déjà été vérifiée et n'est plus modifiable.
            throw IdentityLocked::make();
        }

        $name = trim(($candidature->first_names ?? '').' '.($candidature->last_name ?? ''));

        if ($name !== '') {
            $user->forceFill(['name' => $name])->save();
            RelaisProfile::withoutCommuneRestriction()->where('user_id', $user->id)
                ->update(['display_name' => CreateRelaisProfile::displayNameFor($candidature)]);
        }
    }

    private function removeUnneededSides(IdentityVerification $verification): void
    {
        $type = $verification->documentType;

        if ($type === null) {
            return;
        }

        $kept = array_map(fn ($kind): string => $kind->value, $type->requiredFiles());

        foreach ($verification->files()->whereNotIn('kind', $kept)->get() as $file) {
            Storage::disk($file->disk)->delete($file->path);
            $file->delete();
        }
    }
}
