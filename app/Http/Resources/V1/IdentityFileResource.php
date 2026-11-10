<?php

namespace App\Http\Resources\V1;

use App\Domain\Identity\Models\IdentityFile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Fichier envoyé pour la vérification d'identité (jamais son contenu ni son chemin).
 *
 * @mixin IdentityFile
 */
class IdentityFileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'kind' => $this->kind->value,
            'label' => $this->kind->label(),
            'mime' => $this->mime,
            'size' => $this->size,
            'uploaded_at' => $this->created_at?->toIso8601ZuluString(),
        ];
    }
}
