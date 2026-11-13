<?php

namespace App\Http\Controllers\Api\V1\Identity;

use App\Domain\Access\Enums\Role;
use App\Domain\Identity\Actions\SaveIdentityVerification;
use App\Domain\Identity\Exceptions\IdentityIncomplete;
use App\Domain\Identity\Exceptions\IdentityLocked;
use App\Domain\Identity\Models\IdentityVerification;
use App\Domain\Media\Exceptions\VirusScanUnavailable;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Identity\SubmitIdentityRequest;
use App\Http\Requests\Api\V1\Identity\UpdateIdentityRequest;
use App\Http\Resources\V1\IdentityVerificationResource;
use App\Models\User;
use Closure;
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
     * Envoyer ma vérification d'identité (une seule requête).
     *
     * Requête `multipart/form-data` avec tous les champs et toutes les photos. Le dossier part aussitôt en vérification
     * (`status: "submitted"`) ; l'administration le valide (`verified`) ou le refuse (`rejected`, motif dans `decision_comment`).
     *
     * **Choisir d'abord `document_type`** (liste `GET /auth/register/options`) : ses `sides` disent quelles photos de la pièce
     * envoyer — `recto` et `verso` (CNI, permis, carte consulaire) ou `page` (passeport, attestation). Plus le `selfie`
     * (la personne tenant sa pièce, visage visible ; sert aussi de photo du badge). Images JPEG, PNG ou HEIC (PDF accepté pour
     * la pièce), 10 Mo maximum chacune.
     *
     * Tout ou rien : en cas d'erreur (422), rien n'est enregistré et `errors` indique chaque champ ou photo à corriger.
     *
     * @response IdentityVerificationResource
     */
    #[ApiResponse(409, 'Vérification déjà envoyée (en cours) ou déjà vérifiée.', type: 'array{message: string}')]
    #[ApiResponse(503, 'Antivirus momentanément indisponible : réessayer plus tard (`Retry-After`).', type: 'array{message: string}')]
    public function store(SubmitIdentityRequest $request, SaveIdentityVerification $save): JsonResponse
    {
        return $this->resource($this->run(fn () => $save->submit($this->verificationOf($request), $request->details(), $request->identityFiles())));
    }

    /**
     * Modifier ma vérification d'identité.
     *
     * **Tout est facultatif** : seuls les éléments envoyés et non vides changent (champs et/ou photos) ; un champ vide ne
     * l'efface pas, les autres sont conservés. Une requête sans aucun élément répond 422.
     * Même format que `POST /identity` (`multipart/form-data` ; JSON accepté s'il n'y a pas de photo).
     * Changer `document_type` retire les photos de la pièce devenues inutiles ; les photos envoyées doivent correspondre
     * au type de pièce (celui envoyé, sinon celui déjà enregistré). Possible tant que le dossier est `draft` ou `rejected`.
     * Si la modification complète le dossier, il part aussitôt en vérification (`submitted`).
     *
     * @response IdentityVerificationResource
     */
    #[ApiResponse(409, 'Vérification déjà envoyée (en cours) ou déjà vérifiée.', type: 'array{message: string}')]
    #[ApiResponse(503, 'Antivirus momentanément indisponible : réessayer plus tard (`Retry-After`).', type: 'array{message: string}')]
    public function update(UpdateIdentityRequest $request, SaveIdentityVerification $save): JsonResponse
    {
        return $this->resource($this->run(fn () => $save->update($this->verificationOf($request), $request->details(), $request->identityFiles())));
    }

    /**
     * Traduit les refus métier en réponses HTTP (409, 422, 503).
     *
     * @param  Closure(): IdentityVerification  $action
     */
    private function run(Closure $action): IdentityVerification
    {
        try {
            return $action();
        } catch (IdentityLocked $exception) {
            throw new ConflictHttpException($exception->getMessage());
        } catch (VirusScanUnavailable) {
            throw new ServiceUnavailableHttpException(60, __('media.scan_unavailable'));
        } catch (IdentityIncomplete $exception) {
            throw ValidationException::withMessages(array_map(fn (string $message): array => [$message], $exception->missing));
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['document_type' => $exception->getMessage()]);
        }
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
