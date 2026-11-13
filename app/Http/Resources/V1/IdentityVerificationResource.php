<?php

namespace App\Http\Resources\V1;

use App\Domain\Candidature\Models\Candidature;
use App\Domain\Identity\Enums\IdentityFileKind;
use App\Domain\Identity\Enums\IdentityStatus;
use App\Domain\Identity\Models\IdentityDocumentType;
use App\Domain\Identity\Models\IdentityVerification;
use App\Domain\Identity\Support\IdentityChecklist;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Vérification d'identité du Relais connecté : informations, fichiers envoyés, éléments manquants
 * et éligibilité aux gains.
 *
 * @mixin IdentityVerification
 */
class IdentityVerificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $identity = Candidature::withoutCommuneRestriction()->where('user_id', $this->user_id)->first();

        return [
            'uuid' => $this->uuid,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'is_editable' => $this->status->isEditable(),
            'gains_eligible' => $this->status === IdentityStatus::Verified,
            'last_name' => $identity?->last_name,
            'first_name' => $identity?->first_names,
            'gender' => $identity?->gender?->value,
            'birth_date' => $identity?->birth_date?->toDateString(),
            'commune' => $this->commune === null ? null : ['uuid' => $this->commune->uuid, 'name' => $this->commune->name],
            'localite' => $this->localite === null ? null : ['uuid' => $this->localite->uuid, 'name' => $this->localite->name],
            'document_type' => $this->document_type,
            'required_files' => array_map(
                fn (IdentityFileKind $kind): string => $kind->value,
                $this->documentType?->requiredFiles() ?? [IdentityFileKind::Selfie, IdentityFileKind::ProfilePhoto],
            ),
            'files' => IdentityFileResource::collection($this->files),
            'missing' => $this->status->isEditable() ? IdentityChecklist::missing($this->resource) : (object) [],
            'decision_comment' => $this->status === IdentityStatus::Rejected ? $this->decision_comment : null,
            'submitted_at' => $this->submitted_at?->toIso8601ZuluString(),
            'decided_at' => $this->decided_at?->toIso8601ZuluString(),
            'document_types' => IdentityDocumentType::query()->active()->get()
                ->map(fn (IdentityDocumentType $type): array => $type->toOption())->values()->all(),
        ];
    }
}
