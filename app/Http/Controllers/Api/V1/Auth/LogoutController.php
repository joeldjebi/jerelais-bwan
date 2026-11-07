<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Auth\Actions\RevokeDevice;
use App\Domain\Auth\Models\Device;
use App\Http\Controllers\Controller;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

#[Group('Authentification', weight: 1)]
class LogoutController extends Controller
{
    /**
     * Se déconnecter.
     *
     * Détruit le jeton utilisé pour cette requête et désactive les notifications de cet appareil.
     * Les autres appareils du compte restent connectés.
     */
    public function __invoke(Request $request, RevokeDevice $revoke): Response
    {
        /** @var User $user */
        $user = $request->user();
        $token = $user->currentAccessToken();

        $device = Device::query()->where('personal_access_token_id', $token->getKey())->first();

        if ($device !== null) {
            $revoke->handle($device, $user);
        } else {
            $token->delete();
        }

        return response()->noContent();
    }
}
