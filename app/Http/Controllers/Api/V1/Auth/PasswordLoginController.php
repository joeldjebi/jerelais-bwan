<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Auth\Actions\LoginWithPassword;
use App\Domain\Auth\Exceptions\AuthenticationRefused;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\PasswordLoginRequest;
use App\Http\Resources\V1\IssuedTokenResource;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

#[Group('Authentification', weight: 1)]
class PasswordLoginController extends Controller
{
    /**
     * Se connecter avec son numéro et son mot de passe.
     *
     * Possible une fois le mot de passe défini avec `PUT /me/password`. Renvoie un nouveau jeton pour cet appareil, comme `POST /auth/verify`.
     * Identifiants incorrects, compte suspendu ou compte de l'équipe : 422 sur `phone` (message volontairement générique).
     *
     * @unauthenticated
     */
    public function __invoke(PasswordLoginRequest $request, LoginWithPassword $login): JsonResponse
    {
        try {
            $issued = $login->handle($request->phoneNumber(), $request->string('password')->toString(), $request->deviceInfo());
        } catch (AuthenticationRefused $exception) {
            throw ValidationException::withMessages(['phone' => $exception->getMessage()]);
        }

        return (new IssuedTokenResource($issued))->response()->setStatusCode(201);
    }
}
