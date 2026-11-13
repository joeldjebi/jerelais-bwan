<?php

namespace App\Http\Controllers\Api\V1\Identity;

use App\Domain\Access\Enums\Role;
use App\Domain\Identity\Actions\DeleteIdentityFile;
use App\Domain\Identity\Actions\SaveIdentityDetails;
use App\Domain\Identity\Actions\StoreIdentityFile;
use App\Domain\Identity\Actions\SubmitIdentityVerification;
use App\Domain\Identity\Enums\IdentityFileKind;
use App\Domain\Identity\Exceptions\IdentityIncomplete;
use App\Domain\Identity\Exceptions\IdentityLocked;
use App\Domain\Identity\Models\IdentityVerification;
use App\Domain\Media\Exceptions\InfectedFile;
use App\Domain\Media\Exceptions\VirusScanUnavailable;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Identity\StoreIdentityFileRequest;
use App\Http\Requests\Api\V1\Identity\UpdateIdentityRequest;
use App\Http\Resources\V1\IdentityFileResource;
use App\Http\Resources\V1\IdentityVerificationResource;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/**
 * Vérification d'identité du Relais (MOB-01) : condition pour recevoir ses gains.
 */
#[Group('Vérification d’identité', 'Profil pour recevoir ses commissions : identité, commune, pièce d’identité, selfie et photo de profil, vérifiés par l’administration. N’empêche ni la connexion ni l’envoi de relais.', weight: 2)]
class IdentityController extends Controller
{
    /**
     * Ma vérification d'identité.
     *
     * Statut (`draft` à compléter, `submitted` en cours de vérification, `verified` vérifiée, `rejected` à corriger avec
     * `decision_comment`), commune et localité, type de pièce, fichiers envoyés, éléments manquants (`missing`) et
     * éligibilité aux gains (`gains_eligible`). `document_types` liste les pièces acceptées et leurs faces.
     *
     * @response IdentityVerificationResource
     */
    #[ApiResponse(403, 'Réservé aux Relais (compte validé).', type: 'array{message: string}')]
    public function show(Request $request): JsonResponse
    {
        return $this->resource($this->verificationOf($request));
    }

    /**
     * Compléter mon profil (identité, commune, type de pièce).
     *
     * **Mise à jour partielle** : seuls les champs envoyés sont modifiés, l'application peut compléter le profil en plusieurs fois.
     * `last_name`, `first_names`, `gender` (`femme` ou `homme`), `birth_date` (AAAA-MM-JJ) ;
     * `commune` (uuid de `GET /communes`) et/ou `localite` (uuid de `GET /communes/{commune}/localites`) : une localité suffit,
     * sa commune est déduite ; `document_type` : une `value` de `GET /auth/register/options` (`cni`, `passeport`, `permis`…, liste gérée par l'administration).
     * Changer de type de pièce retire les faces devenues inutiles. Si la mise à jour complète le dossier, il passe tout seul
     * au statut `submitted`. 409 si la vérification est envoyée ou déjà vérifiée.
     *
     * @response IdentityVerificationResource
     */
    public function update(UpdateIdentityRequest $request, SaveIdentityDetails $save): JsonResponse
    {
        try {
            $verification = $save->handle($this->verificationOf($request), $request->details());
        } catch (IdentityLocked $exception) {
            throw new ConflictHttpException($exception->getMessage());
        }

        return $this->resource($verification);
    }

    /**
     * Envoyer un fichier.
     *
     * Envoi multipart : `kind` et `file`. `kind` : `recto` et `verso` (CNI, permis, carte consulaire), `page` (passeport,
     * attestation d'identité), `selfie` (la personne tenant sa pièce) et `profile_photo`. Choisir d'abord le type de pièce.
     * Formats : JPEG, PNG, HEIC, et PDF pour les faces de la pièce ; 10 Mo maximum. Un nouveau fichier remplace le précédent.
     * Quand ce fichier complète le dossier, celui-ci passe tout seul au statut `submitted` (envoyé à l'administration).
     */
    #[ApiResponse(503, 'Antivirus momentanément indisponible : réessayer plus tard (`Retry-After`).', type: 'array{message: string}')]
    public function storeFile(StoreIdentityFileRequest $request, StoreIdentityFile $store): JsonResponse
    {
        try {
            $file = $store->handle($this->verificationOf($request), $request->kind(), $request->file('file'));
        } catch (IdentityLocked $exception) {
            throw new ConflictHttpException($exception->getMessage());
        } catch (InfectedFile $exception) {
            throw ValidationException::withMessages(['file' => $exception->getMessage()]);
        } catch (VirusScanUnavailable) {
            throw new ServiceUnavailableHttpException(60, __('media.scan_unavailable'));
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['kind' => $exception->getMessage()]);
        }

        return (new IdentityFileResource($file))->response()->setStatusCode(201);
    }

    /**
     * Retirer un fichier.
     *
     * `kind` : `recto`, `verso`, `page`, `selfie` ou `profile_photo`. 409 si la vérification n'est plus modifiable.
     */
    public function destroyFile(Request $request, IdentityFileKind $kind, DeleteIdentityFile $delete): Response
    {
        try {
            $delete->handle($this->verificationOf($request), $kind);
        } catch (IdentityLocked $exception) {
            throw new ConflictHttpException($exception->getMessage());
        }

        return response()->noContent();
    }

    /**
     * Envoyer ma vérification d'identité (facultatif).
     *
     * Le dossier part déjà tout seul dès qu'il est complet (dernier fichier ou dernier champ reçu) : cet appel est facultatif.
     * Exige le nom, les prénoms, le sexe, la date de naissance, la commune, le type de pièce, ses faces, le selfie et la photo
     * de profil ; sinon 422 avec la liste des éléments
     * manquants. Passe au statut `submitted` : l'administration vérifie, puis le statut devient `verified` (éligible aux gains)
     * ou `rejected` (corriger selon `decision_comment` et renvoyer). Idempotent une fois envoyée.
     *
     * @response IdentityVerificationResource
     */
    #[ApiResponse(422, 'Vérification incomplète : `errors` liste chaque élément manquant.', type: 'array{message: string, errors: array<string, list<string>>}')]
    public function submit(Request $request, SubmitIdentityVerification $submit): JsonResponse
    {
        try {
            $verification = $submit->handle($this->verificationOf($request));
        } catch (IdentityIncomplete $exception) {
            throw ValidationException::withMessages(array_map(fn (string $message): array => [$message], $exception->missing));
        } catch (IdentityLocked $exception) {
            throw new ConflictHttpException($exception->getMessage());
        }

        return $this->resource($verification);
    }

    private function verificationOf(Request $request): IdentityVerification
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user->hasRole(Role::Relais->value), 403, __('identity.errors.not_relais'));

        $verification = IdentityVerification::query()->where('user_id', $user->id)->first();

        if ($verification === null) {
            $verification = new IdentityVerification;
            $verification->user()->associate($user);
            $verification->save();
        }

        return $verification;
    }

    /**
     * Toujours 200 : le dossier est créé à la première consultation, ce n'est pas une création demandée par l'appel.
     */
    private function resource(IdentityVerification $verification): JsonResponse
    {
        return (new IdentityVerificationResource($verification->load(['commune', 'localite', 'files'])))->response()->setStatusCode(200);
    }
}
