<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Auth\Actions\RequestOtp;
use App\Domain\Auth\Actions\VerifyOtp;
use App\Domain\Auth\Exceptions\AuthenticationRefused;
use App\Domain\Auth\Exceptions\SmsNotSent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\RequestOtpRequest;
use App\Http\Requests\Api\V1\Auth\VerifyOtpRequest;
use App\Http\Resources\V1\IssuedTokenResource;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/**
 * Connexion par code SMS (MOB-01).
 */
#[Group('Authentification', 'Inscription par numéro et code SMS, connexion par numéro et mot de passe, mot de passe oublié, jeton par appareil, déconnexion.', weight: 1)]
class OtpController extends Controller
{
    /**
     * Mot de passe oublié : demander un code de connexion.
     *
     * Pour un compte existant (mot de passe oublié, nouvel appareil). Envoie par SMS un code à 4 chiffres, valable 5 minutes ;
     * chaque nouvelle demande annule le code précédent. Tant qu'aucun fournisseur de SMS n'est branché, le code est `1234`.
     * L'inscription passe par `POST /auth/register`. La réponse est identique que le numéro soit inscrit ou non.
     * Numéro ivoirien à 10 chiffres (`0700000001`) ou `+2250700000001` ; seuls les numéros de Côte d’Ivoire reçoivent un code (422 sinon).
     *
     * @unauthenticated
     */
    #[ApiResponse(503, 'SMS non envoyé (fournisseur indisponible, ou plafond horaire de la plateforme atteint) : afficher `message` et réessayer après `Retry-After`.', type: 'array{message: string}')]
    public function request(RequestOtpRequest $request, RequestOtp $requestOtp): JsonResponse
    {
        try {
            $requestOtp->handle($request->phoneNumber(), $request->ip());
        } catch (SmsNotSent $exception) {
            throw new ServiceUnavailableHttpException(60, $exception->userMessage());
        }

        return response()->json([
            'message' => __('auth.otp_sent'),
            'meta' => ['expires_in_seconds' => (int) config('jerelais.otp.ttl_minutes') * 60],
        ], 202);
    }

    /**
     * Mot de passe oublié : valider le code et obtenir un jeton.
     *
     * Renvoie le jeton Sanctum de cet appareil, valable 60 jours (`expires_at`), à envoyer ensuite dans `Authorization: Bearer <jeton>`.
     * Pendant 15 minutes, ce jeton peut redéfinir le mot de passe avec `PUT /me/password` **sans** l'ancien (`current_password`).
     * Un numéro sans compte est refusé (422 sur `code`) : l'application propose l'inscription.
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
