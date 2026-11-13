<?php

namespace App\Http\Controllers\Api\V1\Relais;

use App\Domain\Contribution\Actions\RespondToComplement;
use App\Domain\Contribution\Actions\SendRelaisItem;
use App\Domain\Contribution\Exceptions\RelaisIncomplete;
use App\Domain\Contribution\Exceptions\RelaisNotEditable;
use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Media\Exceptions\MediaRefused;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Relais\StoreRelaisItemRequest;
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
     * Envoyer un relais (en un seul appel).
     *
     * **Pas de brouillon.** Un seul appel avec tout le contenu et la liste des fichiers :
     * `client_uuid`, `category`, `title`, `location` (GPS) ou `declared_commune`, `protections`
     * (`has_minors`, `people_consent`, `credit_mode`, `credit_name` pour un pseudonyme) et `medias` (1 à 10 fichiers :
     * `client_uuid`, `kind`, `mime`, `size` en octets, `sha256`). Tout est contrôlé d'emblée : 422 sinon, et rien n'est créé.
     *
     * La réponse donne, dans `uploads`, les URL d'envoi de chaque fichier (`upload.part_size`, `upload.missing_parts`).
     * L'application envoie les morceaux, puis appelle `POST /medias/{uuid}/complete` pour chaque fichier.
     * **Le relais est soumis automatiquement** dès que son dernier fichier est reçu : statut `uploading` → `received`.
     *
     * Idempotent : renvoyer le même `client_uuid` rend le même relais (200) avec des URL d'envoi à jour pour les fichiers
     * pas encore reçus (reprise après une coupure). Pour répondre à un appel à relais, envoyer son `uuid` dans `appel`.
     * 409 si le `client_uuid` appartient à un autre compte. 403 pour un Relais suspendu.
     */
    #[Response(201, 'Relais créé : envoyer les fichiers avec les URL de `uploads` ; il est soumis à la réception du dernier.', type: 'RelaisItemResource')]
    #[Response(200, 'Relais déjà créé avec ce `client_uuid` : renvoyé avec des URL d’envoi à jour (reprise sans doublon).', type: 'RelaisItemResource')]
    #[Response(422, 'Contenu incomplet ou fichier refusé : `errors` liste chaque champ (`category`, `title`, `declared_commune`, `protections.*`, `medias.N.size`…).', type: 'array{message: string, errors: array<string, list<string>>}')]
    public function store(StoreRelaisItemRequest $request, SendRelaisItem $send): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $clientUuid = $request->string('client_uuid')->toString();

        $existing = RelaisItem::withoutCommuneRestriction()->where('client_uuid', $clientUuid)->first();

        if ($existing !== null && $existing->user_id !== $user->id) {
            throw new ConflictHttpException(__('relais_items.errors.client_uuid_taken'));
        }

        try {
            $relais = $send->handle($user, $clientUuid, $request->relaisData(), $request->medias());
        } catch (RelaisIncomplete $exception) {
            throw ValidationException::withMessages($exception->missing);
        } catch (MediaRefused $exception) {
            throw ValidationException::withMessages([$exception->field => $exception->getMessage()]);
        }

        $relais->load(['category', 'commune', 'rejectionReason', 'medias']);

        return (new RelaisItemResource($relais))
            ->additional(['uploads' => MediaUploadResource::collection($relais->medias)])
            ->response()
            ->setStatusCode($relais->wasRecentlyCreated ? 201 : 200);
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
