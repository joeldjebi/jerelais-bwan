<?php

namespace App\Domain\Editorial\Actions;

use App\Domain\Access\Enums\Permission;
use App\Domain\Audit\AuditLogger;
use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Editorial\Exceptions\EditorialRefused;
use App\Domain\Editorial\Models\ExportLog;
use App\Domain\Editorial\Support\CreditLine;
use App\Domain\Media\Enums\MediaStatus;
use App\Domain\Media\Models\Media;
use App\Domain\Media\Storage\MultipartStorage;
use App\Domain\Media\Support\TemporaryPath;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Téléchargement des médias en qualité d'origine (ADM-11), individuellement ou par lot, avec fiche d'accompagnement PDF.
 * Bloqué pour un relais non validé (ADM-12), sauf dérogation d'un administrateur avec motif, tracée dans l'audit.
 */
class ExportRelaisMedias
{
    public function __construct(
        private readonly MultipartStorage $storage,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Vérifie le droit d'exporter ce relais ; retourne le motif de dérogation retenu (null si aucune n'est nécessaire).
     */
    public function authorize(RelaisItem $relais, User $user, ?string $derogationReason = null): ?string
    {
        if ($relais->isExploitable()) {
            return null;
        }

        if (! $user->hasPermissionTo(Permission::RelaisExportUnvalidated->value)) {
            throw new EditorialRefused(__('editorial.errors.export_blocked'));
        }

        $reason = trim((string) $derogationReason);

        if ($reason === '') {
            throw new EditorialRefused(__('editorial.errors.derogation_reason_required'));
        }

        return $reason;
    }

    /**
     * ZIP d'un ou plusieurs relais : un dossier par relais avec ses fichiers d'origine et sa fiche.
     * Les relais non exploitables sont écartés d'un lot (sauf dérogation, accordée relais par relais).
     *
     * @param  iterable<RelaisItem>  $items
     * @return string chemin du fichier ZIP temporaire
     */
    public function zip(iterable $items, User $user, ?string $derogationReason = null): string
    {
        $path = TemporaryPath::make('zip');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $downloads = [];
        $count = 0;

        foreach ($items as $relais) {
            $derogation = $this->authorize($relais, $user, $derogationReason);
            $relais->loadMissing(['category', 'commune', 'relaisProfile.candidature', 'medias', 'statusHistory.actor']);
            $folder = sprintf('%02d-%s', ++$count, Str::slug($relais->title ?: 'relais', '-'));

            $zip->addFromString("{$folder}/fiche.pdf", $this->fiche($relais));

            foreach ($relais->medias->where('status', MediaStatus::Ready)->values() as $index => $media) {
                $local = TemporaryPath::make($media->kind->extensionFor((string) $media->mime));
                $this->storage->download($media->path, $local);
                $downloads[] = $local;
                $zip->addFile($local, sprintf('%s/%02d-%s.%s', $folder, $index + 1, $media->kind->value, $media->kind->extensionFor((string) $media->mime)));
            }

            $this->log($relais, $user, 'zip', null, $derogation);
        }

        $zip->close();

        foreach ($downloads as $file) {
            @unlink($file);
        }

        return $path;
    }

    /**
     * Un seul fichier d'origine.
     */
    public function original(Media $media, User $user, ?string $derogationReason = null): void
    {
        $relais = RelaisItem::withoutCommuneRestriction()->findOrFail($media->relais_item_id);
        $this->log($relais, $user, 'original', $media, $this->authorize($relais, $user, $derogationReason));
    }

    /**
     * Fiche d'accompagnement : titre, description, lieu, date et heure, Relais, crédit, consignes de floutage.
     */
    public function fiche(RelaisItem $relais): string
    {
        return Pdf::loadView('pdf.relais-fiche', [
            'relais' => $relais,
            'credit' => CreditLine::for($relais),
            'validator' => $relais->statusHistory->firstWhere('to', 'validated')?->actor,
        ])->setPaper('a4')->output();
    }

    private function log(RelaisItem $relais, User $user, string $kind, ?Media $media, ?string $derogation): void
    {
        ExportLog::query()->create([
            'user_id' => $user->id,
            'relais_item_id' => $relais->id,
            'kind' => $kind,
            'media_id' => $media?->id,
            'derogation_reason' => $derogation,
            'created_at' => now(),
        ]);

        $this->audit->record($derogation === null ? 'relais.exported' : 'relais.export_derogation', $relais, array_filter([
            'kind' => $kind,
            'media' => $media?->uuid,
            'reason' => $derogation,
        ]), actor: $user, communeId: $relais->commune_id);
    }
}
