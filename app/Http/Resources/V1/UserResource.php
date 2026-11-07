<?php

namespace App\Http\Resources\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Profil renvoyé à l'application : jamais le numéro en clair, ni empreinte, ni secret.
 *
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'phone_masked' => $this->phoneNumber()?->masked(),
            'email' => $this->email,
            'status' => $this->status->value,
            'roles' => $this->getRoleNames()->values(),
            'has_password' => $this->password !== null,
            'communes' => CommuneResource::collection($this->whenLoaded('communes')),
            'created_at' => $this->created_at?->toIso8601ZuluString(),
        ];
    }
}
