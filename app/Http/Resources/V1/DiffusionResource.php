<?php

namespace App\Http\Resources\V1;

use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Editorial\Support\CreditLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Relais diffusé et publié sur la vitrine (MOB-25).
 *
 * @mixin RelaisItem
 */
class DiffusionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'title' => $this->showcase_title,
            'text' => $this->showcase_text,
            'commune' => $this->commune?->name,
            'category' => $this->category?->name,
            'credit' => CreditLine::for($this->resource),
            'programme' => $this->broadcast_programme,
            'broadcast_url' => $this->broadcast_url,
            'broadcast_at' => $this->broadcast_at?->toIso8601ZuluString(),
            'image_url' => $this->showcase_with_image ? route('showcase.image', $this->resource) : null,
        ];
    }
}
