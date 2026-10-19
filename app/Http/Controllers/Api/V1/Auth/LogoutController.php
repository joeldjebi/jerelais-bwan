<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Auth\Actions\RevokeDevice;
use App\Domain\Auth\Models\Device;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class LogoutController extends Controller
{
    /**
     * Se déconnecter : le jeton de cet appareil est détruit.
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
