<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Auth\Actions\RegisterWithPhone;
use App\Domain\Auth\Actions\RequestOtp;
use App\Domain\Auth\Exceptions\AuthenticationRefused;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\RequestOtpRequest;
use App\Http\Requests\Api\V1\Auth\VerifyOtpRequest;
use App\Http\Resources\V1\IssuedTokenResource;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * Inscription depuis l'application (MOB-01) : numéro, code SMS à 4 chiffres, puis code PIN obligatoire.
 */
#[Group('Authentification', weight: 1)]
class RegistrationController extends Controller
{
    /**
     * Inscription, étape 1 : demander un code.
     *
     * Envoie par SMS un code à 4 chiffres, valable 5 minutes, au numéro à inscrire.
     * Tant qu'aucun fournisseur de SMS n'est branché, aucun SMS ne part : le code est `1234`.
     * Un numéro qui a déjà un compte est refusé (422 sur `phone`) : l'application propose alors la connexion par code PIN.
     *
     * @unauthenticated
     */
    public function request(RequestOtpRequest $request, RequestOtp $requestOtp): JsonResponse
    {
        $phone = $request->phoneNumber();

        if (User::findByPhone($phone) !== null) {
            throw ValidationException::withMessages(['phone' => __('auth.phone_already_registered')]);
        }

        $requestOtp->handle($phone, $request->ip());

        return response()->json([
            'message' => __('auth.registration_otp_sent'),
            'meta' => ['expires_in_seconds' => (int) config('jerelais.otp.ttl_minutes') * 60],
        ], 202);
    }

    /**
     * Inscription, étape 2 : valider le code et créer le compte.
     *
     * Crée le compte « candidat » et renvoie le jeton de cet appareil, avec `pin_required: true`.
     * Étape suivante obligatoire : définir le code PIN avec `PUT /me/password`. Tant qu'il ne l'est pas,
     * les autres routes protégées répondent 403 avec `code: "pin_required"`.
     *
     * Erreurs 422 : `code` faux ou expiré (5 essais par code) ; `phone` déjà inscrit.
     *
     * @unauthenticated
     */
    public function verify(VerifyOtpRequest $request, RegisterWithPhone $register): JsonResponse
    {
        $phone = $request->phoneNumber();

        try {
            RegisterWithPhone::ensureAvailable($phone);
        } catch (AuthenticationRefused $exception) {
            throw ValidationException::withMessages(['phone' => $exception->getMessage()]);
        }

        try {
            $issued = $register->handle($phone, $request->string('code')->toString(), $request->deviceInfo());
        } catch (AuthenticationRefused $exception) {
            throw ValidationException::withMessages(['code' => $exception->getMessage()]);
        }

        return (new IssuedTokenResource($issued))->response()->setStatusCode(201);
    }
}
