<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Auth\Actions\RequestOtp;
use App\Domain\Auth\Actions\VerifyOtp;
use App\Domain\Auth\Exceptions\AuthenticationRefused;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\RequestOtpRequest;
use App\Http\Requests\Api\V1\Auth\VerifyOtpRequest;
use App\Http\Resources\V1\IssuedTokenResource;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * Connexion par code SMS (MOB-01).
 */
#[Group('Authentification', 'Connexion par code SMS ou par mot de passe, jeton par appareil, déconnexion.', weight: 1)]
class OtpController extends Controller
{
    /**
     * Demander un code de vérification.
     *
     * Envoie par SMS un code à 6 chiffres, valable 5 minutes. Chaque nouvelle demande annule le code précédent.
     * La réponse est identique que le numéro soit déjà inscrit ou non : elle ne révèle pas l'existence d'un compte.
     * Numéro ivoirien à 10 chiffres (`0700000001`) ou format international (`+2250700000001`).
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
     * Renvoie le jeton Sanctum de cet appareil, valable 60 jours (`expires_at`), à envoyer ensuite dans `Authorization: Bearer <jeton>`.
     * Un numéro inconnu crée un compte « candidat » (`is_new_user: true`) : l'application ouvre alors le parcours de candidature.
     *
     * Erreurs 422 sur `code` : code faux ou expiré ; après 5 essais le code est annulé et il faut en redemander un.
     * Un compte suspendu ou un compte de l'équipe (console) est refusé.
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
