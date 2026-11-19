<?php

namespace App\Http\Resources\V1;

use App\Domain\Identity\Models\IdentityFile;
use App\Domain\Media\Support\SignedFileUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Fichier envoyé pour la vérification d'identité (jamais son chemin) : `url`, lien signé de quelques minutes pour
 * que le Relais revoie sa pièce dans l'application (image ou PDF selon `mime`). À redemander après `url_expires_at`.
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
            'url' => SignedFileUrl::for($this->disk, $this->path, self::minutes()),
            'url_expires_at' => SignedFileUrl::expiresAt(self::minutes()),
            'uploaded_at' => $this->created_at?->toIso8601ZuluString(),
        ];
    }

    private static function minutes(): int
    {
        return (int) config('jerelais.candidature.identity_url_minutes');
    }
}
