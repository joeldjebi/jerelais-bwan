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
     * Uniquement `phone` et `password` (le mot de passe choisi à l'inscription). Renvoie un nouveau jeton pour cet appareil.
     * Identifiants incorrects, compte suspendu ou compte de l'équipe : 422 sur `phone` (message volontairement générique).
     * Inscription pas encore validée par l'équipe : 422 sur `phone` avec le message « inscription en attente de validation ».
     * Après 5 mots de passe erronés sur un même numéro, quelle que soit l'adresse IP, le numéro est bloqué 15 minutes (429) :
     * proposer « Mot de passe oublié » (`POST /auth/otp` puis `POST /auth/verify`).
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
