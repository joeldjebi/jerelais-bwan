<?php

namespace App\Http\Resources\V1;

use App\Domain\Candidature\Models\Candidature;
use App\Domain\Relais\Models\RelaisProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Profil renvoyé à l'application (connexion, GET /me) : identité séparée (prénom, nom), profil Relais,
 * vérification d'identité. Jamais le numéro en clair, ni empreinte, ni secret.
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
        $identity = Candidature::withoutCommuneRestriction()->where('user_id', $this->id)->first();
        $relais = RelaisProfile::withoutCommuneRestriction()->with(['commune', 'localite'])->where('user_id', $this->id)->first();

        return [
            'uuid' => $this->uuid,
            'first_name' => $identity?->first_names,
            'last_name' => $identity?->last_name,
            'gender' => $identity?->gender?->value,
            'birth_date' => $identity?->birth_date?->toDateString(),
            'phone_masked' => $this->phoneNumber()?->masked(),
            'email' => $this->email,
            'status' => $this->status->value,
            'roles' => $this->getRoleNames()->values(),
            'has_password' => $this->password !== null,
            'relais' => $relais === null ? null : [
                'number' => $relais->number,
                'display_name' => $relais->display_name,
                'status' => $relais->status->value,
                'commune' => $relais->commune === null ? null : ['uuid' => $relais->commune->uuid, 'name' => $relais->commune->name],
                'localite' => $relais->localite === null ? null : ['uuid' => $relais->localite->uuid, 'name' => $relais->localite->name],
                'activated_at' => $relais->activated_at->toIso8601ZuluString(),
            ],
            'identity_status' => $this->identityVerification?->status->value,
            'gains_eligible' => $this->identityVerification?->isVerified() ?? false,
            'communes' => CommuneResource::collection($this->whenLoaded('communes')),
            'created_at' => $this->created_at?->toIso8601ZuluString(),
        ];
    }
}
