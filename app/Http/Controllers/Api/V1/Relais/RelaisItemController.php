<?php

namespace App\Http\Controllers\Api\V1\Relais;

use App\Domain\Contribution\Actions\RespondToComplement;
use App\Domain\Contribution\Actions\SendRelaisItem;
use App\Domain\Contribution\Actions\UpdateRelaisItem;
use App\Domain\Contribution\Exceptions\RelaisIncomplete;
use App\Domain\Contribution\Exceptions\RelaisNotEditable;
use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Media\Exceptions\MediaRefused;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Relais\StoreRelaisItemRequest;
use App\Http\Requests\Api\V1\Relais\UpdateRelaisItemRequest;
use App\Http\Resources\V1\MediaUploadResource;
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
#[Group('Relais', 'Envoi d’un relais en un seul appel (contenu et fichiers), suivi et complément (MOB-10 à MOB-20).', weight: 7)]
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
     * Envoyer un relais (une seule requête).
     *
     * **Requête `multipart/form-data` avec tout le contenu et les fichiers.** Le relais est aussitôt envoyé au
     * coordonnateur (`status: "received"`) ; chaque fichier est ensuite contrôlé en arrière-plan (antivirus, durée) :
     * suivre `medias.N.status` (`processing` puis `ready`, ou `rejected`).
     *
     * Champs : `category`, `title` (obligatoires), `description`, `urgency`, le lieu — `lat` + `lng` (GPS, `accuracy_m`
     * facultatif) ou `declared_commune` —, `declared_place`, les protections `has_minors` (`true`/`false`), `people_consent`,
     * `credit_mode` (`credit_name` si pseudonyme), `appel` pour répondre à un appel, et **`files[]`** : 1 à 10 fichiers
     * (photos JPEG/PNG/HEIC, vidéos MP4/MOV, sons M4A/MP3/AAC), **100 Mo maximum chacun**. `client_uuid` (facultatif) :
     * renvoyer le même rend le même relais, sans doublon.
     *
     * Tout ou rien : 422 avec un message par champ, rien n'est créé. Vidéo de plus de 100 Mo ou réseau instable :
     * utiliser l'envoi en morceaux (JSON avec `medias`, voir le guide).
     */
    #[Response(201, 'Relais envoyé (`status: "received"`) ; ses fichiers sont contrôlés en arrière-plan.', type: 'RelaisItemResource')]
    #[Response(200, 'Relais déjà envoyé avec ce `client_uuid` : renvoyé sans doublon.', type: 'RelaisItemResource')]
    #[Response(422, 'Contenu incomplet ou fichier refusé : `errors` liste chaque champ (`category`, `title`, `declared_commune`, `protections.*`, `files.N`…).', type: 'array{message: string, errors: array<string, list<string>>}')]
    public function store(StoreRelaisItemRequest $request, SendRelaisItem $send): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $clientUuid = $request->clientUuid();

        $existing = RelaisItem::withoutCommuneRestriction()->where('client_uuid', $clientUuid)->first();

        if ($existing !== null && $existing->user_id !== $user->id) {
            throw new ConflictHttpException(__('relais_items.errors.client_uuid_taken'));
        }

        try {
            $relais = $request->hasDirectFiles()
                ? $send->handleWithFiles($user, $clientUuid, $request->relaisData(), $request->attachedFiles())
                : $send->handle($user, $clientUuid, $request->relaisData(), $request->medias());
        } catch (RelaisIncomplete $exception) {
            throw ValidationException::withMessages($exception->missing);
        } catch (MediaRefused $exception) {
            throw ValidationException::withMessages([$exception->field => $exception->getMessage()]);
        }

        $relais->load(['category', 'commune', 'rejectionReason', 'medias']);
        $resource = new RelaisItemResource($relais);

        if (! $request->hasDirectFiles()) {
            $resource->additional(['uploads' => MediaUploadResource::collection($relais->medias)]);
        }

        return $resource->response()->setStatusCode($relais->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Modifier un relais envoyé.
     *
     * **Tout est facultatif** : seuls les éléments envoyés et non vides changent (même format que l'envoi, multipart ou JSON).
     * Ajouter des fichiers : `files[]` (100 Mo maximum chacun). Retirer des fichiers : `remove_medias[]` (uuid de
     * `medias.N.uuid`) ; il doit en rester au moins un.
     *
     * Possible tant que le coordonnateur ne l'a pas pris en charge (`received`) et quand un complément est demandé
     * (`complement_requested`) : la modification sert alors de réponse (`message` facultatif) et le relais revient dans la
     * file. 409 pendant l'examen et après la décision.
     *
     * @response RelaisItemResource
     */
    #[Response(409, 'Relais en cours d’examen ou déjà décidé : il ne peut plus être modifié.', type: 'array{message: string}')]
    public function update(UpdateRelaisItemRequest $request, RelaisItem $relais, UpdateRelaisItem $update): JsonResponse
    {
        abort_unless($relais->user_id === $request->user()?->getAuthIdentifier(), 404);

        try {
            $update->handle($relais, $request->changes(), $request->attachedFiles(), $request->removedMedias(), $request->string('message')->toString() ?: null);
        } catch (RelaisIncomplete $exception) {
            throw ValidationException::withMessages($exception->missing);
        } catch (MediaRefused $exception) {
            throw ValidationException::withMessages([$exception->field => $exception->getMessage()]);
        } catch (RelaisNotEditable $exception) {
            throw new ConflictHttpException($exception->getMessage());
        }

        return $this->respond($relais);
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
