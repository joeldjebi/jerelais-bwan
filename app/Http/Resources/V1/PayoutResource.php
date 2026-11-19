<?php

namespace App\Http\Resources\V1;

use App\Domain\Gains\Models\Payout;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Paiement reçu par le Relais (MOB-26) : montant, moyen, référence et commissions soldées.
 *
 * @mixin Payout
 */
class PayoutResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'amount' => $this->amount,
            'currency' => 'XOF',
            'method' => $this->method->value,
            'method_label' => $this->method->label(),
            'reference' => $this->reference,
            'paid_at' => $this->paid_at->toIso8601ZuluString(),
            'commissions_count' => (int) ($this->commissions_count ?? 0),
            'commissions' => CommissionResource::collection($this->whenLoaded('commissions')),
        ];
    }
}
