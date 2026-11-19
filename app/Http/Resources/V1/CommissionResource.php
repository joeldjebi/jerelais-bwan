<?php

namespace App\Http\Resources\V1;

use App\Domain\Gains\Models\Commission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Commission vue par le Relais (MOB-26) : montant, raison, statut et, une fois payée, moyen et référence.
 *
 * @mixin Commission
 */
class CommissionResource extends JsonResource
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
            'amount' => $this->amount,
            'currency' => 'XOF',
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'relais' => $this->relaisItem === null ? null : ['uuid' => $this->relaisItem->uuid, 'title' => $this->relaisItem->title],
            'paid_at' => $this->paid_at?->toIso8601ZuluString(),
            'payment_method' => $this->payment_method?->value,
            'payment_method_label' => $this->payment_method?->label(),
            'payment_reference' => $this->payment_reference,
            'created_at' => $this->created_at?->toIso8601ZuluString(),
        ];
    }
}
