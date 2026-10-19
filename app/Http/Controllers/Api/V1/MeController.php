<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Audit\AuditLogger;
use App\Domain\Auth\Models\Device;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateCurrentDeviceRequest;
use App\Http\Requests\Api\V1\UpdatePasswordRequest;
use App\Http\Resources\V1\DeviceResource;
use App\Http\Resources\V1\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MeController extends Controller
{
    /**
     * Mon profil.
     */
    public function show(Request $request): UserResource
    {
        /** @var User $user */
        $user = $request->user();

        return new UserResource($user->load('communes'));
    }

    /**
     * Définir ou changer mon mot de passe.
     */
    public function updatePassword(UpdatePasswordRequest $request, AuditLogger $audit): Response
    {
        /** @var User $user */
        $user = $request->user();

        $user->forceFill(['password' => $request->string('password')->toString()])->save();
        $audit->record('auth.password.changed', $user, actor: $user);

        return response()->noContent();
    }

    /**
     * Mettre à jour l'appareil courant (jeton de notifications push, version de l'application).
     */
    public function updateDevice(UpdateCurrentDeviceRequest $request): DeviceResource
    {
        /** @var User $user */
        $user = $request->user();
        $token = $user->currentAccessToken();

        $device = Device::query()->where('personal_access_token_id', $token->getKey())->firstOrFail();
        $device->fill([
            ...$request->safe()->only(['fcm_token', 'app_version']),
            'last_seen_at' => now(),
        ])->save();

        return new DeviceResource($device);
    }
}
