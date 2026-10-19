<?php

namespace App\Domain\Candidature\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Candidature\Enums\DocumentType;
use App\Domain\Candidature\Exceptions\CandidatureLocked;
use App\Domain\Candidature\Models\Candidature;
use Illuminate\Support\Facades\Storage;

/**
 * Retire une pièce du dossier tant qu'il n'est pas déposé.
 */
class DeleteCandidatureDocument
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(Candidature $candidature, DocumentType $type): void
    {
        if (! $candidature->status->isEditable()) {
            throw CandidatureLocked::make();
        }

        $document = $candidature->documents()->where('type', $type->value)->first();

        if ($document === null) {
            return;
        }

        Storage::disk($document->disk)->delete($document->path);
        $document->delete();

        $this->audit->record('candidature.document.deleted', $candidature, ['type' => $type->value], communeId: $candidature->commune_id);
    }
}
