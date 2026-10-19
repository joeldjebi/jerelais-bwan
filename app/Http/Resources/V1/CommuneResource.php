<?php

namespace App\Http\Resources\V1;

use App\Domain\Territoire\Models\Commune;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Commune
 */
class CommuneResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'code' => $this->code,
            'region' => $this->whenLoaded('region', fn () => $this->region === null ? null : [
                'uuid' => $this->region->uuid,
                'name' => $this->region->name,
            ]),
            'is_virtual' => $this->is_virtual,
        ];
    }
}
