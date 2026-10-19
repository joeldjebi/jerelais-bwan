<?php

namespace App\Http\Controllers\Api\V1\Candidature;

use App\Domain\Access\Enums\Permission;
use App\Domain\Candidature\Actions\SaveCandidatureStep;
use App\Domain\Candidature\Actions\StartCandidature;
use App\Domain\Candidature\Actions\SubmitCandidature;
use App\Domain\Candidature\Enums\CandidatureStep;
use App\Domain\Candidature\Enums\Gender;
use App\Domain\Candidature\Enums\Occupation;
use App\Domain\Candidature\Exceptions\CandidatureIncomplete;
use App\Domain\Candidature\Exceptions\CandidatureLocked;
use App\Domain\Candidature\Models\Candidature;
use App\Domain\Candidature\Support\StepRules;
use App\Domain\Contribution\Models\Category;
use App\Domain\Territoire\Models\Commune;
use App\Domain\Territoire\Models\LieuType;
use App\Domain\Territoire\Models\Localite;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Candidature\UpdateCandidatureStepRequest;
use App\Http\Resources\V1\CandidatureResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Dossier de candidature en étapes (MOB-02, MOB-06).
 */
class CandidatureController extends Controller
{
    /**
     * Mon dossier de candidature.
     *
     * Le dossier est ouvert au premier appel. Un Relais déjà recruté retrouve son dossier accepté.
     */
    public function show(Request $request, StartCandidature $start): JsonResponse
    {
        return $this->respond($this->candidatureOf($request, $start));
    }

    /**
     * Enregistrer une étape (sauvegarde automatique).
     *
     * Tous les champs sont facultatifs ; seuls ceux envoyés sont modifiés.
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
     * Renvoie 422 avec la liste des éléments manquants si le dossier est incomplet.
     */
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
     * Listes du formulaire : genres, professions, langues, centres d'intérêt, lieux types.
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
     * Localités (quartiers, villages) d'une commune ouverte.
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
        $candidature->load(['commune', 'localite', 'lieuType', 'rejectionReason']);

        return (new CandidatureResource($candidature))->response()->setStatusCode(200);
    }
}
