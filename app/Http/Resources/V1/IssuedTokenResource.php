<?php

namespace App\Http\Resources\V1;

use App\Domain\Auth\Data\IssuedToken;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Réponse de connexion : jeton à conserver dans le stockage sécurisé du téléphone.
 *
 * @property IssuedToken $resource
 */
class IssuedTokenResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'token' => $this->resource->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => now()->addMinutes((int) config('sanctum.expiration'))->toIso8601ZuluString(),
            'is_new_user' => $this->resource->isNewUser,
            'device' => new DeviceResource($this->resource->device),
            'user' => new UserResource($this->resource->user),
        ];
    }
}
