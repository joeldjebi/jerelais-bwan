<?php

namespace App\Http\Resources\V1;

use App\Domain\Candidature\Enums\CandidatureStatus;
use App\Domain\Candidature\Enums\DocumentType;
use App\Domain\Candidature\Models\Candidature;
use App\Domain\Candidature\Support\CandidatureChecklist;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Dossier du candidat et suivi de son état (MOB-02, MOB-06).
 *
 * @mixin Candidature
 */
class CandidatureResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $checklist = app(CandidatureChecklist::class);

        return [
            'uuid' => $this->uuid,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'is_editable' => $this->status->isEditable(),
            'checklist' => collect($checklist->items($this->resource))
                ->map(fn (array $item, string $key): array => ['key' => $key, ...$item])
                ->values(),
            'missing' => $this->status->isEditable() ? $checklist->missing($this->resource) : (object) [],
            'identite' => [
                'last_name' => $this->last_name,
                'first_names' => $this->first_names,
                'birth_date' => $this->birth_date?->toDateString(),
                'gender' => $this->gender?->value,
                'email' => $this->email,
                'is_minor' => $this->is_minor,
            ],
            'territoire' => [
                'commune' => $this->commune === null ? null : ['uuid' => $this->commune->uuid, 'name' => $this->commune->name],
                'localite' => $this->localite === null ? null : ['uuid' => $this->localite->uuid, 'name' => $this->localite->name],
                'lieu_type' => $this->lieuType === null ? null : ['uuid' => $this->lieuType->uuid, 'name' => $this->lieuType->name],
            ],
            'profil' => [
                'occupation' => $this->occupation?->value,
                'languages' => $this->languages ?? [],
                'interests' => $this->interests ?? [],
                'motivation' => $this->motivation,
            ],
            'documents' => collect(DocumentType::requiredFor($this->resource))->map(function (DocumentType $type): array {
                $document = $this->documents->firstWhere('type', $type);

                return [
                    'type' => $type->value,
                    'label' => $type->label(),
                    'hint' => $type->hint(),
                    'accepted_mimes' => $type->mimeTypes(),
                    'document' => $document === null ? null : new CandidatureDocumentResource($document),
                ];
            })->values(),
            'decision' => $this->status->isFinal() || $this->status === CandidatureStatus::ComplementRequested ? [
                'decided_at' => $this->decided_at?->toIso8601ZuluString(),
                'reason' => $this->rejectionReason?->label,
                'comment' => $this->decision_comment,
            ] : null,
            'submitted_at' => $this->submitted_at?->toIso8601ZuluString(),
            'updated_at' => $this->updated_at?->toIso8601ZuluString(),
        ];
    }
}
