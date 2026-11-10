<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Identity\Enums\IdentityDocumentType;
use App\Domain\Identity\Exceptions\IdentityLocked;
use App\Domain\Identity\Models\IdentityVerification;
use App\Domain\Territoire\Models\Commune;
use App\Domain\Territoire\Models\Localite;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Enregistre la commune (ou la localité) et le type de pièce (MOB-01). Les données doivent avoir été validées.
 * Changer de type de pièce retire les faces devenues inutiles (ex. recto/verso d'une CNI remplacée par un passeport).
 */
class SaveIdentityDetails
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(IdentityVerification $verification, ?Commune $commune, ?Localite $localite, ?IdentityDocumentType $documentType): IdentityVerification
    {
        if (! $verification->status->isEditable()) {
            throw IdentityLocked::make();
        }

        DB::transaction(function () use ($verification, $commune, $localite, $documentType): void {
            $verification->forceFill([
                'commune_id' => $commune->id ?? $localite?->commune_id,
                'localite_id' => $localite?->id,
                'document_type' => $documentType,
            ])->save();

            if ($documentType !== null) {
                $kept = array_map(fn ($kind): string => $kind->value, $documentType->requiredFiles());

                foreach ($verification->files()->whereNotIn('kind', $kept)->get() as $file) {
                    Storage::disk($file->disk)->delete($file->path);
                    $file->delete();
                }
            }

            $this->audit->record('identity.details.saved', $verification, ['document_type' => $documentType?->value], actor: $verification->user);
        });

        return $verification->refresh();
    }
}
