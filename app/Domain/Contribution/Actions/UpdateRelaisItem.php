<?php

namespace App\Domain\Contribution\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Contribution\Exceptions\RelaisIncomplete;
use App\Domain\Contribution\Exceptions\RelaisNotEditable;
use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Contribution\States\ComplementRequested;
use App\Domain\Contribution\States\Received;
use App\Domain\Contribution\States\Uploading;
use App\Domain\Contribution\Support\RelaisSender;
use App\Domain\Media\Actions\StoreDirectMedia;
use App\Domain\Media\Enums\MediaStatus;
use App\Domain\Media\Exceptions\MediaRefused;
use App\Domain\Media\Jobs\ProcessMedia;
use App\Domain\Media\Models\Media;
use App\Domain\Territoire\Actions\ResolveCommuneFromPoint;
use App\Domain\Territoire\Models\Commune;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Modification d'un relais par son Relais (MOB-19, COO-14) : texte, lieu, protections, fichiers ajoutés ou retirés.
 *
 * Possible avant la prise en charge par le coordonnateur (« received », ou encore en cours d'envoi) et quand un
 * complément est demandé : la modification sert alors de réponse et le relais revient dans la file.
 * Pendant l'examen et après la décision : refusé. Tout ou rien.
 */
class UpdateRelaisItem
{
    public function __construct(
        private readonly SaveRelaisItem $save,
        private readonly SubmitRelaisItem $submit,
        private readonly StoreDirectMedia $storeMedia,
        private readonly RespondToComplement $respondToComplement,
        private readonly ResolveCommuneFromPoint $resolveCommune,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data  champs modifiés (les absents sont conservés)
     * @param  list<UploadedFile>  $files  fichiers ajoutés
     * @param  list<string>  $removeMedias  uuid des fichiers à retirer
     *
     * @throws RelaisNotEditable relais en cours d'examen ou déjà décidé
     * @throws RelaisIncomplete contenu devenu incomplet, plus aucun fichier, ou lieu introuvable
     * @throws MediaRefused fichier refusé (« files.N ») ou fichier à retirer inconnu (« remove_medias »)
     */
    public function handle(RelaisItem $relais, array $data, array $files, array $removeMedias, ?string $message = null): RelaisItem
    {
        RelaisSender::ensureCanSend($relais->user);

        if (! $relais->state instanceof Received && ! $relais->state instanceof ComplementRequested && ! $relais->state instanceof Uploading) {
            throw RelaisNotEditable::make();
        }

        $stored = [];

        try {
            DB::transaction(function () use ($relais, $data, $files, $removeMedias, &$stored): void {
                $this->save->amend($relais, $data);

                foreach ($removeMedias as $uuid) {
                    $media = $relais->medias()->where('uuid', $uuid)->first();

                    if (! $media instanceof Media) {
                        throw new MediaRefused('remove_medias', __('media.not_in_relais'));
                    }

                    $this->storeMedia->remove($media);
                }

                foreach ($files as $index => $file) {
                    try {
                        $stored[] = $this->storeMedia->handle($relais, $file);
                    } catch (MediaRefused $exception) {
                        throw new MediaRefused("files.{$index}", $exception->getMessage());
                    }
                }

                $this->ensureStillComplete($relais);

                $this->audit->record('relais.updated', $relais, [
                    'fields' => array_keys($data),
                    'added' => count($files),
                    'removed' => count($removeMedias),
                ], actor: $relais->user, communeId: $relais->commune_id);
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

        // Réponse à une demande de complément : le relais revient dans la file du coordonnateur.
        if ($relais->state instanceof ComplementRequested) {
            $this->respondToComplement->handle($relais, $message ?: __('relais_items.complement_by_update'));
        }

        return $relais->refresh();
    }

    /**
     * Un relais déjà envoyé doit rester complet (au moins un fichier) et rattaché à une commune.
     */
    private function ensureStillComplete(RelaisItem $relais): void
    {
        if ($relais->state instanceof Uploading) {
            return;
        }

        $missing = $this->submit->missing($relais);
        unset($missing['medias']);

        if (! $relais->medias()->where('status', '!=', MediaStatus::Rejected->value)->exists()) {
            $missing['files'] = __('media.none');
        }

        $gps = $relais->location === null ? null : $this->resolveCommune->handle($relais->location);
        $commune = $gps ?? ($relais->declared_commune_id === null ? null : Commune::query()->active()->find($relais->declared_commune_id));

        if ($commune === null) {
            $missing['declared_commune'] = __('relais_items.errors.no_commune');
        }

        if ($missing !== [] || $commune === null) {
            throw new RelaisIncomplete($missing);
        }

        // Lieu modifié avant la prise en charge : le relais suit sa nouvelle commune.
        if ($relais->commune_id !== $commune->id) {
            $relais->forceFill(['commune_id' => $commune->id, 'routed_by' => $gps !== null ? 'gps' : 'declared'])->save();
        }
    }
}
