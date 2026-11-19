<?php

namespace App\Http\Resources\V1;

use App\Domain\Gains\Models\PayoutAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Coordonnées Mobile Money du Relais, telles qu'il les a enregistrées (MOB-26).
 *
 * @mixin PayoutAccount
 */
class PayoutAccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'method' => $this->method->value,
            'method_label' => $this->method->label(),
            'phone' => $this->phone,
            'holder_name' => $this->holder_name,
            'updated_at' => $this->updated_at?->toIso8601ZuluString(),
        ];
    }
}
