<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Auth\Actions\CompleteRegistration;
use App\Domain\Auth\Actions\RequestOtp;
use App\Domain\Auth\Actions\VerifyRegistrationCode;
use App\Domain\Auth\Exceptions\AuthenticationRefused;
use App\Domain\Auth\Support\RegistrationTokens;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\CompleteRegistrationRequest;
use App\Http\Requests\Api\V1\Auth\RequestOtpRequest;
use App\Http\Requests\Api\V1\Auth\VerifyRegistrationCodeRequest;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * Inscription depuis l'application (MOB-01) : numéro, code SMS à 4 chiffres, puis identité et code PIN.
 * Le compte créé reste « en attente » jusqu'à sa validation par l'équipe dans la console.
 */
#[Group('Authentification', weight: 1)]
class RegistrationController extends Controller
{
    /**
     * Inscription 1/3 : demander un code.
     *
     * Envoie par SMS un code à 4 chiffres, valable 5 minutes, au numéro à inscrire.
     * Tant qu'aucun fournisseur de SMS n'est branché, aucun SMS ne part : le code est `1234`.
     * Un numéro qui a déjà un compte est refusé (422 sur `phone`) : l'application propose alors la connexion.
     *
     * @unauthenticated
     */
    public function request(RequestOtpRequest $request, RequestOtp $requestOtp): JsonResponse
    {
        $phone = $request->phoneNumber();

        try {
            VerifyRegistrationCode::ensureAvailable($phone);
        } catch (AuthenticationRefused $exception) {
            throw ValidationException::withMessages(['phone' => $exception->getMessage()]);
        }

        $requestOtp->handle($phone, $request->ip());

        return response()->json([
            'message' => __('auth.registration_otp_sent'),
            'meta' => ['expires_in_seconds' => (int) config('jerelais.otp.ttl_minutes') * 60],
        ], 202);
    }

    /**
     * Inscription 2/3 : confirmer le code.
     *
     * Vérifie le code et renvoie un `registration_token`, à usage unique et valable 30 minutes, à joindre à l'étape suivante.
     * Aucun compte n'est encore créé.
     *
     * Erreurs 422 : `code` faux ou expiré (5 essais par code) ; `phone` déjà inscrit.
     *
     * @unauthenticated
     */
    public function verify(VerifyRegistrationCodeRequest $request, VerifyRegistrationCode $verify): JsonResponse
    {
        $phone = $request->phoneNumber();

        try {
            VerifyRegistrationCode::ensureAvailable($phone);
        } catch (AuthenticationRefused $exception) {
            throw ValidationException::withMessages(['phone' => $exception->getMessage()]);
        }

        try {
            $token = $verify->handle($phone, $request->string('code')->toString());
        } catch (AuthenticationRefused $exception) {
            throw ValidationException::withMessages(['code' => $exception->getMessage()]);
        }

        return response()->json([
            'message' => __('auth.registration_code_verified'),
            'data' => [
                /** Jeton à joindre à `POST /auth/register/complete`. */
                'registration_token' => $token,
                /** Durée de validité du jeton, en secondes. */
                'expires_in_seconds' => RegistrationTokens::TTL_MINUTES * 60,
            ],
        ]);
    }

    /**
     * Inscription 3/3 : identité et code PIN.
     *
     * Nom, prénoms, sexe, date de naissance et code PIN de 4 chiffres (saisi deux fois).
     * Crée le compte au statut **`pending`** (en attente) : **aucun jeton n'est renvoyé**. L'utilisateur ne peut pas se connecter
     * tant que l'équipe JE RELAIS n'a pas validé son inscription ; en attendant, `POST /auth/login` répond 422 avec un message
     * « inscription en attente de validation ». Une fois validée, il se connecte avec son numéro et son code PIN.
     *
     * Erreur 422 sur `registration_token` : jeton inconnu, déjà utilisé ou expiré (recommencer à l'étape 1).
     *
     * @unauthenticated
     */
    public function complete(CompleteRegistrationRequest $request, CompleteRegistration $complete): JsonResponse
    {
        try {
            $user = $complete->handle(
                $request->string('registration_token')->toString(),
                $request->identity(),
                $request->string('password')->toString(),
            );
        } catch (AuthenticationRefused $exception) {
            throw ValidationException::withMessages(['registration_token' => $exception->getMessage()]);
        }

        return response()->json([
            'message' => __('auth.registration_completed'),
            'data' => [
                /** Identifiant du compte créé. */
                'uuid' => $user->uuid,
                /** Statut du compte : `pending` jusqu'à la validation par l'équipe. */
                'status' => $user->status->value,
            ],
        ], 201);
    }
}
