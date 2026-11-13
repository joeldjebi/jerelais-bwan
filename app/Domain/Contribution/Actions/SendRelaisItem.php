<?php

namespace App\Domain\Contribution\Actions;

use App\Domain\Contribution\Exceptions\RelaisIncomplete;
use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Media\Actions\InitiateMediaUpload;
use App\Domain\Media\Actions\StoreDirectMedia;
use App\Domain\Media\Exceptions\MediaRefused;
use App\Domain\Media\Jobs\ProcessMedia;
use App\Domain\Territoire\Actions\ResolveCommuneFromPoint;
use App\Domain\Territoire\Models\Commune;
use App\Models\User;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Envoi d'un relais en un seul appel depuis l'application (MOB-19). Deux façons :
 * - handleWithFiles() : contenu et fichiers dans la même requête (jusqu'à 100 Mo par fichier) ;
 * - handle() : contenu et liste des fichiers, puis envoi en morceaux reprenable (vidéos lourdes).
 * Pas de brouillon : tout est contrôlé d'emblée (catégorie, titre, lieu, protections, au moins un fichier),
 * puis chaque fichier reçoit ses URL d'envoi. Le relais est soumis automatiquement à la réception du dernier
 * fichier (SubmitWhenUploaded). Idempotent : un même « client_uuid » renvoie le même relais.
 */
class SendRelaisItem
{
    public function __construct(
        private readonly SaveRelaisItem $save,
        private readonly SubmitRelaisItem $submit,
        private readonly InitiateMediaUpload $initiateMedia,
        private readonly StoreDirectMedia $storeMedia,
        private readonly ResolveCommuneFromPoint $resolveCommune,
    ) {}

    /**
     * Envoi en une seule requête : contenu et fichiers ensemble. Le relais est aussitôt soumis (« received »),
     * puis chaque fichier est contrôlé en arrière-plan. Tout ou rien : en cas de refus, rien n'est gardé.
     *
     * @param  array<string, mixed>  $data  contenu du relais validé
     * @param  list<UploadedFile>  $files
     *
     * @throws RelaisIncomplete contenu incomplet ou lieu introuvable
     * @throws MediaRefused un fichier est refusé (format, taille, nombre) : champ « files.N »
     */
    public function handleWithFiles(User $user, string $clientUuid, array $data, array $files): RelaisItem
    {
        $existing = RelaisItem::withoutCommuneRestriction()->where('client_uuid', $clientUuid)->first();

        if ($existing !== null) {
            return $existing;
        }

        $stored = [];

        try {
            $relais = DB::transaction(function () use ($user, $clientUuid, $data, $files, &$stored): RelaisItem {
                $relais = $this->save->create($user, $clientUuid, $data);
                $this->ensureComplete($relais);

                foreach ($files as $index => $file) {
                    try {
                        $stored[] = $this->storeMedia->handle($relais, $file);
                    } catch (MediaRefused $exception) {
                        throw new MediaRefused("files.{$index}", $exception->getMessage());
                    }
                }

                return $this->submit->handle($relais);
            });
        } catch (Throwable $exception) {
            foreach ($stored as $media) {
                Storage::disk($media->disk)->delete($media->path);
            }

            throw $exception;
        }

        foreach ($stored as $media) {
            ProcessMedia::dispatch($media->id);
        }

        return $relais;
    }

    /**
     * @param  array<string, mixed>  $data  contenu du relais validé
     * @param  list<array{client_uuid: string, kind: string, mime: string, size: int, sha256: string, filename?: string|null, is_imported?: bool, device_captured_at?: string|null}>  $medias
     *
     * @throws RelaisIncomplete contenu incomplet ou lieu introuvable
     * @throws MediaRefused un fichier est refusé avant l'envoi (taille, format, nombre) : champ « medias.N.… »
     */
    public function handle(User $user, string $clientUuid, array $data, array $medias): RelaisItem
    {
        $existing = RelaisItem::withoutCommuneRestriction()->where('client_uuid', $clientUuid)->first();

        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($user, $clientUuid, $data, $medias): RelaisItem {
            $relais = $this->save->create($user, $clientUuid, $data);
            $relais->forceFill(['submit_on_upload' => true])->save();

            $this->ensureComplete($relais);

            foreach ($medias as $index => $media) {
                try {
                    $this->initiateMedia->handle($relais, $media);
                } catch (MediaRefused $exception) {
                    throw new MediaRefused("medias.{$index}.{$exception->field}", $exception->getMessage());
                }
            }

            return $relais;
        });
    }

    /**
     * Contenu complet (hors fichiers) et commune déterminable.
     *
     * @throws RelaisIncomplete
     */
    private function ensureComplete(RelaisItem $relais): void
    {
        $missing = array_diff_key($this->submit->missing($relais), ['medias' => true]);

        if (! $this->hasCommune($relais)) {
            $missing['declared_commune'] = __('relais_items.errors.no_commune');
        }

        if ($missing !== []) {
            throw new RelaisIncomplete($missing);
        }
    }

    private function hasCommune(RelaisItem $relais): bool
    {
        if ($relais->location instanceof Point && $this->resolveCommune->handle($relais->location) !== null) {
            return true;
        }

        return $relais->declared_commune_id !== null && Commune::query()->active()->whereKey($relais->declared_commune_id)->exists();
    }
}
