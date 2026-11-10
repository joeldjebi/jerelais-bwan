<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Auth\Actions\CompleteRegistration;
use App\Domain\Auth\Actions\RequestOtp;
use App\Domain\Auth\Actions\VerifyRegistrationCode;
use App\Domain\Auth\Exceptions\AuthenticationRefused;
use App\Domain\Auth\Support\VerifiedRegistrations;
use App\Domain\Identity\Enums\IdentityDocumentType;
use App\Domain\Identity\Enums\IdentityFileKind;
use App\Domain\Media\Exceptions\InfectedFile;
use App\Domain\Media\Exceptions\VirusScanUnavailable;
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
 * Inscription depuis l'application (MOB-01) : numéro, code SMS à 4 chiffres, puis identité, pièce d'identité et mot de passe.
 * Le compte créé reste « en attente » jusqu'à sa validation par l'équipe dans la console.
 */
#[Group('Authentification', weight: 1)]
class RegistrationController extends Controller
{
    /**
     * Inscription : pièces d'identité acceptées.
     *
     * Liste à afficher sur l'écran d'inscription : `value` (à envoyer dans `document_type`), `label` et `sides`
     * (fichiers à joindre : `recto` et `verso`, ou `page`). Formats acceptés et taille maximale dans `meta`.
     *
     * @unauthenticated
     */
    public function options(): JsonResponse
    {
        return response()->json([
            'data' => array_map(fn (IdentityDocumentType $type): array => [
                /** Valeur à envoyer dans `document_type`. */
                'value' => $type->value,
                /** Libellé prêt à afficher. */
                'label' => $type->label(),
                /** Fichiers à joindre : `recto` et `verso`, ou `page`. */
                'sides' => array_map(fn (IdentityFileKind $kind): string => $kind->value, $type->sides()),
            ], IdentityDocumentType::cases()),
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
     * Inscription 3/3 : identité, pièce d'identité et mot de passe.
     *
     * Envoi **multipart** : `phone` (le numéro vérifié à l'étape 2, dans les 30 minutes), `last_name`, `first_names`,
     * `gender` (`femme` ou `homme`), `birth_date` (AAAA-MM-JJ), `document_type`, les faces de la pièce et le mot de passe.
     *
     * Faces selon `document_type` : `cni`, `permis`, `carte_consulaire` → fichiers `recto` **et** `verso` ;
     * `passeport`, `attestation_identite` → fichier `page`. Image (JPEG, PNG, HEIC) ou PDF, 10 Mo maximum chacun.
     * Mot de passe : `password` (au moins 8 caractères) et `password_confirmation`.
     *
     * Crée le compte au statut **`pending`** (en attente) : **aucun jeton n'est renvoyé**. L'utilisateur ne peut pas se connecter
     * tant que l'équipe JE RELAIS n'a pas validé son inscription (elle voit la pièce envoyée). Une fois validée, il se connecte
     * avec son numéro et son mot de passe. La commune, le selfie et la photo de profil se complètent ensuite (`/identity`).
     *
     * Erreurs 422 : champ invalide ; `phone` non vérifié ou vérification expirée (recommencer à l'étape 1) ; fichier refusé
     * par l'antivirus (`recto`, `verso` ou `page`).
     *
     * @unauthenticated
     */
    #[ApiResponse(503, 'Antivirus momentanément indisponible : réessayer plus tard (`Retry-After`).', type: 'array{message: string}')]
    public function complete(CompleteRegistrationRequest $request, CompleteRegistration $complete): JsonResponse
    {
        $documentType = $request->documentType();

        try {
            $user = $complete->handle(
                $request->phoneNumber(),
                $request->identity(),
                $request->string('password')->toString(),
                $documentType ?? throw ValidationException::withMessages(['document_type' => __('identity.errors.document_type')]),
                $request->documentFiles(),
            );
        } catch (AuthenticationRefused $exception) {
            throw ValidationException::withMessages(['phone' => $exception->getMessage()]);
        } catch (InfectedFile $exception) {
            throw ValidationException::withMessages([$documentType->sides()[0]->value => $exception->getMessage()]);
        } catch (VirusScanUnavailable) {
            throw new ServiceUnavailableHttpException(60, __('media.scan_unavailable'));
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
