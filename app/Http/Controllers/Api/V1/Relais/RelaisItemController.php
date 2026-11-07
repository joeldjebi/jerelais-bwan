<?php

namespace App\Http\Controllers\Api\V1\Relais;

use App\Domain\Contribution\Actions\RespondToComplement;
use App\Domain\Contribution\Actions\SaveRelaisItem;
use App\Domain\Contribution\Actions\SubmitRelaisItem;
use App\Domain\Contribution\Exceptions\RelaisIncomplete;
use App\Domain\Contribution\Exceptions\RelaisNotEditable;
use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Media\Exceptions\MediaRefused;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Relais\StoreRelaisItemRequest;
use App\Http\Requests\Api\V1\Relais\UpdateRelaisItemRequest;
use App\Http\Resources\V1\RelaisItemResource;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Relais envoyés par l'application (MOB-10 à MOB-20).
 */
#[Group('Relais', 'Création, envoi et suivi des relais (MOB-10 à MOB-20).', weight: 7)]
class RelaisItemController extends Controller
{
    /**
     * Mes relais.
     *
     * Du plus récent au plus ancien, 20 par page (`?page=2`), avec le statut public : `uploading`, `received`,
     * `complement_requested`, `validated`, `rejected`, `selected`, `broadcast`, `archived`, le motif de rejet,
     * la question du coordonnateur et l'état de chaque média.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        return RelaisItemResource::collection(
            RelaisItem::withoutCommuneRestriction()->with(['category', 'commune', 'rejectionReason', 'medias'])
                ->where('user_id', $user->id)->latest('id')->paginate(20)
        );
    }

    /**
     * Créer un relais (brouillon).
     *
     * Idempotent : renvoyer le même `client_uuid` rend le relais existant (200) au lieu d'en créer un second (201).
     * Les médias s'ajoutent ensuite, puis le relais est envoyé avec `POST /relais/{uuid}/submit`.
     * Tous les champs sauf `client_uuid` peuvent être complétés plus tard (`PUT /relais/{uuid}`).
     * Pour répondre à un appel à relais, envoyer son `uuid` dans `appel` (422 si l'appel est clos ou hors de ma zone).
     * 409 si le `client_uuid` appartient déjà à un autre compte. 403 pour un Relais suspendu ou un candidat.
     */
    #[Response(201, 'Relais créé (brouillon, statut `uploading`).', type: 'RelaisItemResource')]
    #[Response(200, 'Relais déjà créé avec ce `client_uuid` : renvoyé tel quel (nouvel essai sans doublon).', type: 'RelaisItemResource')]
    public function store(StoreRelaisItemRequest $request, SaveRelaisItem $save): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $clientUuid = $request->string('client_uuid')->toString();

        $existing = RelaisItem::withoutCommuneRestriction()->where('client_uuid', $clientUuid)->first();

        if ($existing !== null && $existing->user_id !== $user->id) {
            throw new ConflictHttpException(__('relais_items.errors.client_uuid_taken'));
        }

        try {
            $relais = $save->create($user, $clientUuid, $request->relaisData());
        } catch (MediaRefused $exception) {
            throw ValidationException::withMessages([$exception->field => $exception->getMessage()]);
        }

        return $this->respond($relais, $relais->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Un de mes relais.
     *
     * Détail complet : statut, catégorie, lieu, protections, médias, motif de rejet ou complément demandé.
     *
     * @response RelaisItemResource
     */
    public function show(RelaisItem $relais): JsonResponse
    {
        Gate::authorize('view', $relais);

        return $this->respond($relais);
    }

    /**
     * Compléter un relais pas encore envoyé.
     *
     * Seuls les champs envoyés sont modifiés. Possible uniquement au statut `uploading` : 409 une fois le relais envoyé.
     *
     * @response RelaisItemResource
     */
    public function update(UpdateRelaisItemRequest $request, RelaisItem $relais, SaveRelaisItem $save): JsonResponse
    {
        Gate::authorize('update', $relais);

        try {
            $save->update($relais, $request->relaisData());
        } catch (MediaRefused $exception) {
            throw ValidationException::withMessages([$exception->field => $exception->getMessage()]);
        } catch (RelaisNotEditable $exception) {
            throw new ConflictHttpException($exception->getMessage());
        }

        return $this->respond($relais);
    }

    /**
     * Envoyer le relais.
     *
     * Contrôle les champs obligatoires et les questions de protection (422 sinon), route le relais
     * vers la commune du lieu de capture et le place dans la file du coordonnateur. Idempotent.
     *
     * Il faut : au moins un média entièrement envoyé (clôturé, non rejeté), la catégorie, le titre, une position GPS
     * ou une commune déclarée, et les réponses `has_minors`, `people_consent`, `credit_mode` (+ `credit_name` pour un pseudonyme).
     * 403 pour un Relais suspendu ou radié.
     *
     * @response RelaisItemResource
     */
    #[Response(422, 'Relais incomplet : `errors` liste les champs manquants (`category`, `title`, `declared_commune`, `has_minors`, `people_consent`, `credit_mode`, `credit_name`, `medias`).', type: 'array{message: string, errors: array<string, list<string>>}')]
    #[Response(403, 'Relais suspendu ou radié : envoi refusé.', type: 'array{message: string}')]
    public function submit(Request $request, RelaisItem $relais, SubmitRelaisItem $submit): JsonResponse
    {
        abort_unless($relais->user_id === $request->user()?->getAuthIdentifier(), 404);

        try {
            $submit->handle($relais);
        } catch (RelaisIncomplete $exception) {
            throw ValidationException::withMessages($exception->missing);
        } catch (RelaisNotEditable $exception) {
            throw new ConflictHttpException($exception->getMessage());
        }

        return $this->respond($relais);
    }

    /**
     * Répondre à une demande de complément.
     *
     * Message facultatif si un média a été ajouté entre-temps (`POST /relais/{uuid}/medias`). Le relais revient dans la file.
     * Possible uniquement au statut `complement_requested` (409 sinon).
     *
     * @response RelaisItemResource
     */
    #[Response(403, 'Relais suspendu ou radié : réponse refusée.', type: 'array{message: string}')]
    public function complement(Request $request, RelaisItem $relais, RespondToComplement $respond): JsonResponse
    {
        abort_unless($relais->user_id === $request->user()?->getAuthIdentifier(), 404);
        $request->validate(['message' => ['nullable', 'string', 'max:2000']]);

        try {
            $respond->handle($relais, $request->string('message')->toString() ?: null);
        } catch (RelaisIncomplete $exception) {
            throw ValidationException::withMessages($exception->missing);
        } catch (RelaisNotEditable $exception) {
            throw new ConflictHttpException($exception->getMessage());
        }

        return $this->respond($relais);
    }

    private function respond(RelaisItem $relais, int $status = 200): JsonResponse
    {
        $relais->load(['category', 'commune', 'rejectionReason', 'medias']);

        return (new RelaisItemResource($relais))->response()->setStatusCode($status);
    }
}
