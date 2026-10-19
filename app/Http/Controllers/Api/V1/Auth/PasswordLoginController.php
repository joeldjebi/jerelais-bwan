<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Auth\Actions\LoginWithPassword;
use App\Domain\Auth\Exceptions\AuthenticationRefused;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\PasswordLoginRequest;
use App\Http\Resources\V1\IssuedTokenResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class PasswordLoginController extends Controller
{
    /**
     * Se connecter avec son numéro et son mot de passe.
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
