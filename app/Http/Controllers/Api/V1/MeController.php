<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Audit\AuditLogger;
use App\Domain\Auth\Models\Device;
use App\Domain\Privacy\Actions\ExportPersonalData;
use App\Domain\Privacy\Actions\PseudonymizeUser;
use App\Domain\Relais\Models\RelaisProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateCurrentDeviceRequest;
use App\Http\Requests\Api\V1\UpdatePasswordRequest;
use App\Http\Resources\V1\BadgeResource;
use App\Http\Resources\V1\DeviceResource;
use App\Http\Resources\V1\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
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
     * Mon badge de Relais (MOB-07).
     *
     * 404 tant que la candidature n'est pas acceptée.
     */
    public function badge(Request $request): BadgeResource
    {
        /** @var User $user */
        $user = $request->user();

        $profile = RelaisProfile::withoutCommuneRestriction()->with('commune')->where('user_id', $user->id)->first();
        abort_if($profile === null, 404);

        return new BadgeResource($profile);
    }

    /**
     * Mes données personnelles (droit d'accès, §9.4), au format JSON.
     */
    public function export(Request $request, ExportPersonalData $export, AuditLogger $audit): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $audit->record('privacy.exported', $user, ['channel' => 'app'], actor: $user);

        return response()->json(['data' => $export->handle($user)]);
    }

    /**
     * Supprimer mon compte (§9.4).
     *
     * Envoyer `confirm` = « SUPPRIMER ». Le compte est pseudonymisé : identité, pièces, positions et messages sont effacés.
     */
    public function destroy(Request $request, PseudonymizeUser $pseudonymize): Response
    {
        $request->validate(['confirm' => ['required', 'in:SUPPRIMER']]);

        /** @var User $user */
        $user = $request->user();
        abort_if($user->isStaff(), 403);
        $pseudonymize->handle($user, $user, __('Demande du titulaire du compte'));

        return response()->noContent();
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
