<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Auth\Actions\CompleteRegistration;
use App\Domain\Auth\Actions\RequestOtp;
use App\Domain\Auth\Actions\VerifyRegistrationCode;
use App\Domain\Auth\Exceptions\AuthenticationRefused;
use App\Domain\Auth\Exceptions\SmsNotSent;
use App\Domain\Auth\Support\VerifiedRegistrations;
use App\Domain\Identity\Enums\IdentityFileKind;
use App\Domain\Identity\Models\IdentityDocumentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\CompleteRegistrationRequest;
use App\Http\Requests\Api\V1\Auth\RequestOtpRequest;
use App\Http\Requests\Api\V1\Auth\VerifyRegistrationCodeRequest;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/**
 * Inscription depuis l'application (MOB-01) : numéro, code SMS à 4 chiffres, puis mot de passe. Le compte est actif tout de suite.
 */
#[Group('Authentification', weight: 1)]
class RegistrationController extends Controller
{
    /**
     * Pièces d'identité acceptées.
     *
     * Liste à afficher sur l'écran du profil (vérification d'identité, avant les commissions) : `value` (à envoyer dans `document_type`), `label` et `sides`
     * (fichiers à joindre : `recto` et `verso`, ou `page`). Formats acceptés et taille maximale dans `meta`.
     *
     * @unauthenticated
     */
    public function options(): JsonResponse
    {
        return response()->json([
            'data' => IdentityDocumentType::query()->active()->get()->map(fn (IdentityDocumentType $type): array => [
                /** Valeur à envoyer dans `document_type`. */
                'value' => $type->slug,
                /** Libellé prêt à afficher. */
                'label' => $type->name,
                /** Fichiers à joindre : `recto` et `verso`, ou `page`. */
                'sides' => array_map(fn (IdentityFileKind $kind): string => $kind->value, $type->sides()),
            ])->values()->all(),
            'meta' => [
                /** Types de fichiers acceptés pour les faces de la pièce. */
                'accepted_mime_types' => IdentityFileKind::Recto->mimeTypes(),
                /** Taille maximale d'un fichier, en kilo-octets. */
                'max_file_kb' => (int) config('jerelais.candidature.document_max_kb'),
            ],
        ]);
    }

    /**
     * Inscription 1/3 : demander un code.
     *
     * Envoie par SMS un code à 4 chiffres, valable 5 minutes, au numéro à inscrire.
     * Tant qu'aucun fournisseur de SMS n'est branché, aucun SMS ne part : le code est `1234`.
     * Un numéro qui a déjà un compte est refusé (422 sur `phone`) : l'application propose alors la connexion.
     *
     * @unauthenticated
     */
    #[ApiResponse(503, 'SMS non envoyé (fournisseur indisponible, ou plafond horaire de la plateforme atteint) : afficher `message` et réessayer après `Retry-After`.', type: 'array{message: string}')]
    public function request(RequestOtpRequest $request, RequestOtp $requestOtp): JsonResponse
    {
        $phone = $request->phoneNumber();

        try {
            VerifyRegistrationCode::ensureAvailable($phone);
        } catch (AuthenticationRefused $exception) {
            throw ValidationException::withMessages(['phone' => $exception->getMessage()]);
        }

        try {
            $requestOtp->handle($phone, $request->ip());
        } catch (SmsNotSent $exception) {
            throw new ServiceUnavailableHttpException(60, $exception->userMessage());
        }

        return response()->json([
            'message' => __('auth.registration_otp_sent'),
            'meta' => ['expires_in_seconds' => (int) config('jerelais.otp.ttl_minutes') * 60],
        ], 202);
    }

    /**
     * Inscription 2/3 : confirmer le code.
     *
     * Vérifie le code. Le numéro est alors retenu comme vérifié pendant 30 minutes : le temps d'envoyer l'étape suivante
     * (`POST /auth/register/complete`) avec ce même numéro. Aucun compte n'est encore créé, aucun jeton n'est renvoyé.
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
            $verify->handle($phone, $request->string('code')->toString());
        } catch (AuthenticationRefused $exception) {
            throw ValidationException::withMessages(['code' => $exception->getMessage()]);
        }

        return response()->json([
            'message' => __('auth.registration_code_verified'),
            'meta' => ['expires_in_seconds' => VerifiedRegistrations::TTL_MINUTES * 60],
        ]);
    }

    /**
     * Inscription 3/3 : mot de passe (et identité facultative).
     *
     * `phone` (le numéro vérifié à l'étape 2, dans les 30 minutes), `password` (au moins 8 caractères) et
     * `password_confirmation`. Facultatifs : `last_name`, `first_names`, `gender` (`femme` ou `homme`) et
     * `birth_date` au format **JJ/MM/AAAA** (`26/04/2000`, `2000-04-26` est aussi accepté).
     *
     * Crée le compte, **actif** : la personne est Relais (numéro et badge, au nom saisi) et peut tout de suite se connecter
     * avec `POST /auth/login` et envoyer des relais. **Aucun jeton n'est renvoyé** : l'application ouvre l'écran de connexion.
     * L'identité saisie ici pré-remplit le profil ; pièce d'identité, commune, selfie et photo se complètent plus tard
     * dans le profil (`/identity`), exigés seulement pour recevoir ses commissions.
     *
     * Erreurs 422 : `phone` non vérifié ou vérification expirée (recommencer à l'étape 1), ou déjà inscrit ; `password` ;
     * `birth_date` (format ou âge minimum) ; `gender`.
     *
     * @unauthenticated
     */
    public function complete(CompleteRegistrationRequest $request, CompleteRegistration $complete): JsonResponse
    {
        try {
            $user = $complete->handle($request->phoneNumber(), $request->string('password')->toString(), $request->identity());
        } catch (AuthenticationRefused $exception) {
            throw ValidationException::withMessages(['phone' => $exception->getMessage()]);
        }

        return response()->json([
            'message' => __('auth.registration_completed'),
            'data' => [
                /** Identifiant du compte créé. */
                'uuid' => $user->uuid,
                /** Statut du compte : `active`, connexion possible tout de suite. */
                'status' => $user->status->value,
            ],
        ], 201);
    }
}
