<?php

namespace App\Http\Controllers\Api\V1\Relais;

use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Media\Actions\CompleteMediaUpload;
use App\Domain\Media\Actions\InitiateMediaUpload;
use App\Domain\Media\Exceptions\MediaRefused;
use App\Domain\Media\Models\Media;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Relais\StoreMediaRequest;
use App\Http\Resources\V1\MediaUploadResource;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Envoi des médias d'un relais, fractionné et reprenable (MOB-16).
 *
 * 1. `POST /relais/{uuid}/medias` annonce le fichier et renvoie les URL signées de ses morceaux ;
 * 2. l'application dépose chaque morceau (`PUT` sur l'URL) et note l'en-tête `ETag` de la réponse ;
 * 3. en cas de coupure, `GET /medias/{uuid}` indique les morceaux reçus et renvoie des URL pour les autres ;
 * 4. `POST /medias/{uuid}/complete` clôt l'envoi ; le contrôle (antivirus, empreintes) se fait ensuite.
 */
#[Group('Médias', 'Envoi des photos, vidéos et sons d’un relais, fractionné et reprenable, directement vers le stockage (MOB-11, MOB-16).', weight: 8)]
class MediaController extends Controller
{
    /**
     * Annoncer un média.
     *
     * Idempotent sur `client_uuid`. Taille, nombre et formats sont contrôlés ici puis de nouveau sur le fichier reçu.
     *
     * `kind` : `photo`, `video` ou `audio` ; `size` en octets ; `sha256` : empreinte hexadécimale du fichier entier.
     * `is_imported` : `true` si le fichier vient de la galerie (le coordonnateur en est informé) ; `device_captured_at` : date de prise de vue.
     * La réponse donne `upload.part_size` et une URL signée par morceau (`upload.missing_parts`), valable 30 minutes.
     * Chaque morceau s'envoie par `PUT` brut sur son URL, sans en-tête `Authorization` ; garder l'en-tête `ETag` de la réponse.
     */
    #[Response(201, 'Média annoncé : URL signées des morceaux à déposer.', type: 'MediaUploadResource')]
    #[Response(200, 'Média déjà annoncé avec ce `client_uuid` : état actuel et URL des morceaux manquants.', type: 'MediaUploadResource')]
    public function store(StoreMediaRequest $request, RelaisItem $relais, InitiateMediaUpload $initiate): JsonResponse
    {
        try {
            /** @var array{client_uuid: string, kind: string, mime: string, size: int, sha256: string, filename?: string|null, is_imported?: bool, device_captured_at?: string|null} $data */
            $data = $request->validated();
            $media = $initiate->handle($relais, $data);
        } catch (MediaRefused $exception) {
            throw ValidationException::withMessages([$exception->field => $exception->getMessage()]);
        }

        return (new MediaUploadResource($media))->response()->setStatusCode($media->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * État d'un média et reprise de l'envoi.
     *
     * Pendant l'envoi (`pending`) : morceaux déjà reçus (`upload.uploaded_parts`) et nouvelles URL signées pour les manquants.
     * Après la clôture : `uploaded`, puis `processing`, puis `ready` ou `rejected` (raison affichable dans `rejection`).
     * À interroger toutes les quelques secondes après la clôture, jusqu'à `ready`.
     */
    public function show(Request $request, Media $media): MediaUploadResource
    {
        $this->authorizeOwner($request, $media);

        return new MediaUploadResource($media);
    }

    /**
     * Clore l'envoi.
     *
     * Envoyer la liste complète des morceaux : `parts: [{number, etag}]`.
     *
     * Tous les morceaux doivent avoir été déposés et la taille totale reçue doit être exactement celle annoncée :
     * sinon 422 (`parts` ou `size`) et, en cas de taille différente, le média est rejeté. Le contrôle du fichier
     * (type réel, antivirus, empreintes, métadonnées, vignette) se fait ensuite, en tâche de fond. Idempotent.
     */
    #[Response(403, 'Relais suspendu ou radié : envoi refusé.', type: 'array{message: string}')]
    public function complete(Request $request, Media $media, CompleteMediaUpload $complete): MediaUploadResource
    {
        $this->authorizeOwner($request, $media);

        /** @var array{parts: list<array{number: int, etag: string}>} $data */
        $data = $request->validate([
            'parts' => ['required', 'array', 'min:1', 'max:10000'],
            'parts.*.number' => ['required', 'integer', 'min:1', 'distinct'],
            'parts.*.etag' => ['required', 'string', 'max:100'],
        ]);

        try {
            $complete->handle($media, array_map(fn (array $part): array => ['number' => (int) $part['number'], 'etag' => (string) $part['etag']], $data['parts']));
        } catch (MediaRefused $exception) {
            throw ValidationException::withMessages([$exception->field => $exception->getMessage()]);
        }

        return new MediaUploadResource($media->refresh());
    }

    private function authorizeOwner(Request $request, Media $media): void
    {
        $owner = RelaisItem::withoutCommuneRestriction()->whereKey($media->relais_item_id)->value('user_id');

        abort_unless($owner !== null && (int) $owner === $request->user()?->getAuthIdentifier(), 404);
    }
}
