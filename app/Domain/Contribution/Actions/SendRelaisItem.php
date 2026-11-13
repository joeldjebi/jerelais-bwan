<?php

namespace App\Domain\Contribution\Actions;

use App\Domain\Contribution\Exceptions\RelaisIncomplete;
use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Media\Actions\InitiateMediaUpload;
use App\Domain\Media\Exceptions\MediaRefused;
use App\Domain\Territoire\Actions\ResolveCommuneFromPoint;
use App\Domain\Territoire\Models\Commune;
use App\Models\User;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Support\Facades\DB;

/**
 * Envoi d'un relais en un seul appel depuis l'application (MOB-19) : contenu complet et liste des fichiers.
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
        private readonly ResolveCommuneFromPoint $resolveCommune,
    ) {}

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

            $missing = array_diff_key($this->submit->missing($relais), ['medias' => true]);

            if (! $this->hasCommune($relais)) {
                $missing['declared_commune'] = __('relais_items.errors.no_commune');
            }

            if ($missing !== []) {
                throw new RelaisIncomplete($missing);
            }

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

    private function hasCommune(RelaisItem $relais): bool
    {
        if ($relais->location instanceof Point && $this->resolveCommune->handle($relais->location) !== null) {
            return true;
        }

        return $relais->declared_commune_id !== null && Commune::query()->active()->whereKey($relais->declared_commune_id)->exists();
    }
}
