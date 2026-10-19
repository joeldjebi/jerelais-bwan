<?php

namespace App\Http\Resources\V1;

use App\Domain\Auth\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Device
 */
class DeviceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'platform' => $this->platform->value,
            'app_version' => $this->app_version,
            'push_enabled' => $this->fcm_token !== null,
            'last_seen_at' => $this->last_seen_at?->toIso8601ZuluString(),
        ];
    }
}
