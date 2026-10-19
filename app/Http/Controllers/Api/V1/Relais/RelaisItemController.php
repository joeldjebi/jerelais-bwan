<?php

namespace App\Http\Controllers\Api\V1\Relais;

use App\Domain\Contribution\Actions\SaveRelaisItem;
use App\Domain\Contribution\Actions\SubmitRelaisItem;
use App\Domain\Contribution\Exceptions\RelaisIncomplete;
use App\Domain\Contribution\Exceptions\RelaisNotEditable;
use App\Domain\Contribution\Models\RelaisItem;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Relais\StoreRelaisItemRequest;
use App\Http\Requests\Api\V1\Relais\UpdateRelaisItemRequest;
use App\Http\Resources\V1\RelaisItemResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Relais envoyés par l'application (MOB-10 à MOB-20).
 */
class RelaisItemController extends Controller
{
    /**
     * Mes relais, du plus récent au plus ancien, avec leur statut.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        return RelaisItemResource::collection(
            RelaisItem::withoutCommuneRestriction()->with(['category', 'commune', 'rejectionReason'])
                ->where('user_id', $user->id)->latest('id')->paginate(20)
        );
    }

    /**
     * Créer un relais (brouillon).
     *
     * Idempotent : renvoyer le même `client_uuid` rend le relais existant (200) au lieu d'en créer un second (201).
     * Les médias s'ajoutent ensuite, puis le relais est envoyé avec `POST /relais/{uuid}/submit`.
     */
    public function store(StoreRelaisItemRequest $request, SaveRelaisItem $save): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $clientUuid = $request->string('client_uuid')->toString();

        $existing = RelaisItem::withoutCommuneRestriction()->where('client_uuid', $clientUuid)->first();

        if ($existing !== null && $existing->user_id !== $user->id) {
            throw new ConflictHttpException(__('relais_items.errors.client_uuid_taken'));
        }

        $relais = $save->create($user, $clientUuid, $request->relaisData());

        return $this->respond($relais, $relais->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Un de mes relais.
     */
    public function show(RelaisItem $relais): JsonResponse
    {
        Gate::authorize('view', $relais);

        return $this->respond($relais);
    }

    /**
     * Compléter un relais pas encore envoyé.
     */
    public function update(UpdateRelaisItemRequest $request, RelaisItem $relais, SaveRelaisItem $save): JsonResponse
    {
        Gate::authorize('update', $relais);

        try {
            $save->update($relais, $request->relaisData());
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
     */
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

    private function respond(RelaisItem $relais, int $status = 200): JsonResponse
    {
        $relais->load(['category', 'commune', 'rejectionReason']);

        return (new RelaisItemResource($relais))->response()->setStatusCode($status);
    }
}
