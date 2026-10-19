<?php

namespace App\Http\Controllers\Api\V1\Legal;

use App\Domain\Legal\Actions\AcceptLegalDocuments;
use App\Domain\Legal\Models\Consent;
use App\Domain\Legal\Models\LegalDocument;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Legal\StoreConsentRequest;
use App\Http\Resources\V1\LegalDocumentResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Charte, conditions d'utilisation et politique de confidentialité (MOB-04).
 */
class LegalController extends Controller
{
    /**
     * Textes en vigueur.
     *
     * @unauthenticated
     */
    public function index(): AnonymousResourceCollection
    {
        return LegalDocumentResource::collection(array_values(LegalDocument::currentSet()));
    }

    /**
     * Mes acceptations, et si les textes en vigueur sont tous acceptés.
     */
    public function consents(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'data' => Consent::query()->with('legalDocument')->where('user_id', $user->id)->latest('accepted_at')->get()
                ->map(fn (Consent $consent): array => [
                    'type' => $consent->legalDocument->type->value,
                    'version' => $consent->legalDocument->version,
                    'accepted_at' => $consent->accepted_at->toIso8601ZuluString(),
                ]),
            'meta' => ['up_to_date' => AcceptLegalDocuments::isUpToDate($user)],
        ]);
    }

    /**
     * Accepter les textes en vigueur.
     *
     * Envoyer les `uuid` de toutes les versions en vigueur (GET /legal). 422 si une version a changé entre-temps.
     */
    public function accept(StoreConsentRequest $request, AcceptLegalDocuments $accept): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $accept->handle($user, $request->documentUuids(), 'app', $request->ip(), $request->userAgent());
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['documents' => $exception->getMessage()]);
        }

        return response()->json(['meta' => ['up_to_date' => true]], 201);
    }
}
