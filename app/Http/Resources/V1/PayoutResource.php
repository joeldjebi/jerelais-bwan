<?php

namespace App\Http\Resources\V1;

use App\Domain\Gains\Models\Payout;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Retrait du portefeuille (MOB-26) : montant, statut (en attente, payé, refusé), opérateur, numéro crédité,
 * référence de la transaction une fois payé, motif en cas de refus.
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
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'method' => $this->method->value,
            'method_label' => $this->method->label(),
            'phone' => $this->phone,
            'holder_name' => $this->holder_name,
            'provider' => $this->provider,
            'simulated' => $this->isSimulated(),
            'reference' => $this->reference,
            'rejection_reason' => $this->rejection_reason,
            'requested_at' => ($this->requested_at ?? $this->paid_at)?->toIso8601ZuluString(),
            'paid_at' => $this->paid_at?->toIso8601ZuluString(),
        ];
    }
}
