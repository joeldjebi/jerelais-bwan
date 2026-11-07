<?php

namespace App\Http\Resources\V1;

use App\Domain\Relais\Models\RelaisProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

/**
 * Badge numérique du Relais (MOB-07). L'application génère le QR code à partir de « qr_payload ».
 *
 * @mixin RelaisProfile
 */
class BadgeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'number' => $this->number,
            'display_name' => $this->display_name,
            'commune' => $this->commune === null ? null : ['uuid' => $this->commune->uuid, 'name' => $this->commune->name],
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'photo_url' => URL::temporarySignedRoute('relais.badge.photo', now()->addMinutes(15), $this->resource),
            'qr_payload' => $this->verificationUrl(),
            'activated_at' => $this->activated_at->toIso8601ZuluString(),
        ];
    }
}
