<?php

namespace App\Http\Resources\V1;

use App\Domain\Contribution\Models\RelaisItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Relais vu par son auteur (MOB-20) : statut public, jamais la note interne ni le verrou.
 *
 * @mixin RelaisItem
 */
class RelaisItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $public = $this->state->publicName();

        return [
            'uuid' => $this->uuid,
            'client_uuid' => $this->client_uuid,
            'status' => $public,
            'status_label' => __("relais_items.states.{$public}"),
            'category' => $this->category === null ? null : ['uuid' => $this->category->uuid, 'name' => $this->category->name],
            'urgency' => $this->urgency->value,
            'title' => $this->title,
            'description' => $this->description,
            'location' => $this->location === null ? null : [
                'lat' => $this->location->getLatitude(),
                'lng' => $this->location->getLongitude(),
                'accuracy_m' => $this->location_accuracy_m,
            ],
            'commune' => $this->commune === null ? null : ['uuid' => $this->commune->uuid, 'name' => $this->commune->name],
            'declared_place' => $this->declared_place,
            'device_captured_at' => $this->device_captured_at?->toIso8601ZuluString(),
            'protections' => [
                'has_minors' => $this->has_minors,
                'people_consent' => $this->people_consent?->value,
                'credit_mode' => $this->credit_mode?->value,
                'credit_name' => $this->credit_name,
            ],
            'rejection' => $public === 'rejected' ? ['reason' => $this->rejectionReason?->label, 'comment' => $this->decision_comment] : null,
            'complement_request' => $public === 'complement_requested' ? $this->decision_comment : null,
            'submitted_at' => $this->submitted_at?->toIso8601ZuluString(),
            'created_at' => $this->created_at?->toIso8601ZuluString(),
            'updated_at' => $this->updated_at?->toIso8601ZuluString(),
        ];
    }
}
