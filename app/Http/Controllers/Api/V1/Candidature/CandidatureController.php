<?php

namespace App\Http\Controllers\Api\V1\Candidature;

use App\Domain\Access\Enums\Permission;
use App\Domain\Candidature\Actions\DeleteCandidatureDocument;
use App\Domain\Candidature\Actions\SaveCandidatureStep;
use App\Domain\Candidature\Actions\StartCandidature;
use App\Domain\Candidature\Actions\StoreCandidatureDocument;
use App\Domain\Candidature\Actions\SubmitCandidature;
use App\Domain\Candidature\Enums\CandidatureStep;
use App\Domain\Candidature\Enums\DocumentType;
use App\Domain\Candidature\Enums\Gender;
use App\Domain\Candidature\Enums\Occupation;
use App\Domain\Candidature\Exceptions\CandidatureIncomplete;
use App\Domain\Candidature\Exceptions\CandidatureLocked;
use App\Domain\Candidature\Models\Candidature;
use App\Domain\Candidature\Support\StepRules;
use App\Domain\Contribution\Models\Category;
use App\Domain\Media\Exceptions\InfectedFile;
use App\Domain\Media\Exceptions\VirusScanUnavailable;
use App\Domain\Territoire\Models\Commune;
use App\Domain\Territoire\Models\LieuType;
use App\Domain\Territoire\Models\Localite;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Candidature\StoreCandidatureDocumentRequest;
use App\Http\Requests\Api\V1\Candidature\UpdateCandidatureStepRequest;
use App\Http\Resources\V1\CandidatureDocumentResource;
use App\Http\Resources\V1\CandidatureResource;
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
 * Dossier de candidature en étapes (MOB-02, MOB-06).
 */
#[Group('Candidature', 'Dossier de candidature en trois étapes, pièces justificatives, dépôt et suivi (MOB-02 à MOB-06).', weight: 4)]
class CandidatureController extends Controller
{
    /**
     * Mon dossier de candidature.
     *
     * Le dossier est ouvert au premier appel. Un Relais déjà recruté retrouve son dossier accepté.
     * Contient les réponses des trois étapes, les pièces déposées, la liste de ce qui manque (`checklist`), le statut
     * et, le cas échéant, le motif de refus ou le complément demandé.
     *
     * @response CandidatureResource
     */
    #[ApiResponse(403, 'Compte sans dossier de candidature (ni candidat ni Relais recruté).', type: 'array{message: string}')]
    public function show(Request $request, StartCandidature $start): JsonResponse
    {
        return $this->respond($this->candidatureOf($request, $start));
    }

    /**
     * Enregistrer une étape (sauvegarde automatique).
     *
     * Tous les champs sont facultatifs ; seuls ceux envoyés sont modifiés. À appeler à chaque modification
     * (sauvegarde automatique). Étapes : `identite` (nom, prénoms, naissance, genre, e-mail), `territoire` (commune,
     * localité, type de lieu fréquenté), `profil` (profession, langues, centres d'intérêt, motivation).
     * 409 si le dossier est déposé et n'attend pas de complément.
     *
     * @response CandidatureResource
     */
    public function updateStep(UpdateCandidatureStepRequest $request, CandidatureStep $step, StartCandidature $start, SaveCandidatureStep $save): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $candidature = $save->handle($start->handle($user), $step, $request->validated());
        } catch (CandidatureLocked $exception) {
            throw new ConflictHttpException($exception->getMessage());
        }

        return $this->respond($candidature);
    }

    /**
     * Déposer le dossier complet.
     *
     * Vérifie les trois étapes, les pièces, l'acceptation des textes en vigueur et la réussite du quiz.
     * Renvoie 422 avec la liste des éléments manquants si le dossier est incomplet. Après le dépôt, le dossier n'est plus
     * modifiable, sauf si le coordonnateur demande un complément.
     *
     * @response CandidatureResource
     */
    #[ApiResponse(422, 'Dossier incomplet : `errors` liste chaque élément manquant (étapes, pièces, textes juridiques, quiz).', type: 'array{message: string, errors: array<string, list<string>>}')]
    #[ApiResponse(403, 'Compte sans dossier de candidature (ni candidat ni Relais recruté).', type: 'array{message: string}')]
    public function submit(Request $request, StartCandidature $start, SubmitCandidature $submit): JsonResponse
    {
        $candidature = $this->candidatureOf($request, $start);

        try {
            $submit->handle($candidature);
        } catch (CandidatureLocked $exception) {
            throw new ConflictHttpException($exception->getMessage());
        } catch (CandidatureIncomplete $exception) {
            throw ValidationException::withMessages($exception->missing);
        }

        return $this->respond($candidature);
    }

    /**
     * Envoyer une pièce (recto, verso, selfie, photo de profil, autorisation parentale).
     *
     * Envoi multipart : `type` et `file`. Une nouvelle pièce remplace la précédente du même type.
     * Formats : JPEG, PNG, HEIC (et PDF pour l'autorisation parentale), 10 Mo maximum.
     */
    public function storeDocument(StoreCandidatureDocumentRequest $request, StartCandidature $start, StoreCandidatureDocument $store): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $document = $store->handle($start->handle($user), $request->documentType(), $request->file('file'));
        } catch (CandidatureLocked $exception) {
            throw new ConflictHttpException($exception->getMessage());
        } catch (InfectedFile $exception) {
            throw ValidationException::withMessages(['file' => $exception->getMessage()]);
        } catch (VirusScanUnavailable) {
            throw new ServiceUnavailableHttpException(60, __('media.scan_unavailable'));
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages(['type' => __('candidature.document_not_expected')]);
        }

        return (new CandidatureDocumentResource($document))->response()->setStatusCode(201);
    }

    /**
     * Retirer une pièce.
     *
     * `type` : `profile_photo`, `id_front`, `id_back`, `selfie` ou `parental_authorization`. 409 si le dossier n'est plus modifiable.
     */
    public function destroyDocument(Request $request, DocumentType $type, StartCandidature $start, DeleteCandidatureDocument $delete): Response
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $delete->handle($start->handle($user), $type);
        } catch (CandidatureLocked $exception) {
            throw new ConflictHttpException($exception->getMessage());
        }

        return response()->noContent();
    }

    /**
     * Listes du formulaire de candidature.
     *
     * Étapes, genres, professions, langues parlées, centres d'intérêt (les catégories de relais) et types de lieux.
     * Envoyer les `value` (ou `uuid` pour les types de lieux) dans `PUT /candidature/steps/{step}`.
     *
     * @response array{data: array{steps: list<array{key: string, label: string}>, genders: list<array{value: string, label: string}>, occupations: list<array{value: string, label: string}>, languages: list<array{value: string, label: string}>, interests: list<array{value: string, label: string}>, lieu_types: list<array{uuid: string, name: string}>}}
     *
     * @unauthenticated
     */
    public function options(): JsonResponse
    {
        $labels = fn (array $cases): array => array_map(fn (Gender|Occupation $case): array => ['value' => $case->value, 'label' => $case->label()], $cases);

        return response()->json(['data' => [
            'steps' => array_map(fn (CandidatureStep $step): array => ['key' => $step->value, 'label' => $step->label()], CandidatureStep::cases()),
            'genders' => $labels(Gender::cases()),
            'occupations' => $labels(Occupation::cases()),
            'languages' => collect(StepRules::languages())->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])->values(),
            'interests' => Category::query()->active()->get(['slug', 'name'])->map(fn (Category $category): array => ['value' => $category->slug, 'label' => $category->name]),
            'lieu_types' => LieuType::query()->orderBy('name')->get(['uuid', 'name'])->map(fn (LieuType $type): array => ['uuid' => $type->uuid, 'name' => $type->name]),
        ]]);
    }

    /**
     * Localités d'une commune ouverte.
     *
     * Quartiers, villages et sous-préfectures de la commune, triés par nom. 404 si la commune est fermée aux Relais.
     *
     * @response array{data: list<array{uuid: string, name: string, type: 'quartier'|'village'|'sous_prefecture'}>}
     *
     * @unauthenticated
     */
    public function localites(Commune $commune): JsonResponse
    {
        abort_unless($commune->is_active, 404);

        return response()->json(['data' => $commune->localites()->orderBy('name')->get(['uuid', 'name', 'type'])
            ->map(fn (Localite $localite): array => ['uuid' => $localite->uuid, 'name' => $localite->name, 'type' => $localite->type->value]),
        ]);
    }

    private function candidatureOf(Request $request, StartCandidature $start): Candidature
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasPermissionTo(Permission::CandidatureApply->value)) {
            return $start->handle($user);
        }

        $candidature = Candidature::withoutCommuneRestriction()->where('user_id', $user->id)->first();
        abort_if($candidature === null, 403);

        return $candidature;
    }

    /**
     * Toujours 200 : l'ouverture du dossier au premier accès n'est pas une création demandée par le client.
     */
    private function respond(Candidature $candidature): JsonResponse
    {
        $candidature->load(['commune', 'localite', 'lieuType', 'rejectionReason', 'documents']);

        return (new CandidatureResource($candidature))->response()->setStatusCode(200);
    }
}
