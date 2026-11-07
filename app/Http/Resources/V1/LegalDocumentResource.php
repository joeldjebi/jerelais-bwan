<?php

namespace App\Http\Resources\V1;

use App\Domain\Legal\Models\LegalDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Texte juridique en vigueur : Markdown brut et rendu HTML (HTML saisi retiré).
 *
 * @mixin LegalDocument
 */
class LegalDocumentResource extends JsonResource
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
            'version' => $this->version,
            'title' => $this->title,
            'body_markdown' => $this->body,
            'body_html' => $this->html()->toHtml(),
            'published_at' => $this->published_at->toIso8601ZuluString(),
        ];
    }
}
