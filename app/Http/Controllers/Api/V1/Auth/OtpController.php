<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Auth\Actions\RequestOtp;
use App\Domain\Auth\Actions\VerifyOtp;
use App\Domain\Auth\Exceptions\AuthenticationRefused;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\RequestOtpRequest;
use App\Http\Requests\Api\V1\Auth\VerifyOtpRequest;
use App\Http\Resources\V1\IssuedTokenResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * Connexion par code SMS (MOB-01).
 */
class OtpController extends Controller
{
    /**
     * Demander un code de vérification.
     *
     * La réponse est identique que le numéro soit déjà inscrit ou non.
     *
     * @unauthenticated
     */
    public function request(RequestOtpRequest $request, RequestOtp $requestOtp): JsonResponse
    {
        $requestOtp->handle($request->phoneNumber(), $request->ip());

        return response()->json([
            'message' => __('auth.otp_sent'),
            'meta' => ['expires_in_seconds' => (int) config('jerelais.otp.ttl_minutes') * 60],
        ], 202);
    }

    /**
     * Vérifier le code et obtenir un jeton pour cet appareil.
     *
     * Un numéro inconnu crée un compte « candidat ».
     *
     * @unauthenticated
     */
    public function verify(VerifyOtpRequest $request, VerifyOtp $verifyOtp): JsonResponse
    {
        try {
            $issued = $verifyOtp->handle($request->phoneNumber(), $request->string('code')->toString(), $request->deviceInfo());
        } catch (AuthenticationRefused $exception) {
            throw ValidationException::withMessages(['code' => $exception->getMessage()]);
        }

        return (new IssuedTokenResource($issued))->response()->setStatusCode(201);
    }
}
