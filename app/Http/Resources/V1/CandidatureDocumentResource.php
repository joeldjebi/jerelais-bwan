<?php

namespace App\Http\Resources\V1;

use App\Domain\Candidature\Models\CandidatureDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Pièce reçue : jamais le chemin ni d'URL de fichier dans l'API du candidat.
 *
 * @mixin CandidatureDocument
 */
class CandidatureDocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'type' => $this->type->value,
            'label' => $this->type->label(),
            'mime' => $this->mime,
            'size' => $this->size,
            'uploaded_at' => $this->created_at?->toIso8601ZuluString(),
        ];
    }
}
