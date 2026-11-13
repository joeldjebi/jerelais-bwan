<?php

namespace App\Domain\Media\Actions;

use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Contribution\Support\RelaisSender;
use App\Domain\Media\Enums\MediaKind;
use App\Domain\Media\Enums\MediaStatus;
use App\Domain\Media\Exceptions\MediaRefused;
use App\Domain\Media\Models\Media;
use App\Domain\Settings\Settings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Fichier d'un relais reçu directement dans la requête (POST et PUT /relais, MOB-11) : nature déduite du contenu,
 * taille et nombre contrôlés, dépôt sur le stockage privé sous un nom aléatoire. Le contrôle complet (antivirus,
 * durée, aperçus) se fait ensuite en arrière-plan (ProcessMedia), comme pour un envoi en morceaux.
 */
class StoreDirectMedia
{
    public function __construct(private readonly Settings $settings) {}

    /**
     * Taille maximale d'un fichier envoyé directement, en mégaoctets.
     */
    public function maxMegabytes(): int
    {
        return min((int) config('jerelais.media.direct_upload_max_mb'), $this->settings->integer('media.max_file_size_mb'));
    }

    /**
     * @throws MediaRefused format, taille ou nombre de fichiers refusé (champ « file »)
     */
    public function handle(RelaisItem $relais, UploadedFile $file): Media
    {
        RelaisSender::ensureCanSend($relais->user);

        $mime = (string) $file->getMimeType();
        $kind = MediaKind::fromMime($mime);

        if ($kind === null) {
            throw new MediaRefused('file', __('media.unsupported_type'));
        }

        if ((int) $file->getSize() > $this->maxMegabytes() * 1024 * 1024) {
            throw new MediaRefused('file', __('media.too_large_direct', ['max' => $this->maxMegabytes()]));
        }

        $max = $this->settings->integer('media.max_medias_per_relais');

        if ($relais->medias()->where('status', '!=', MediaStatus::Rejected->value)->count() >= $max) {
            throw new MediaRefused('file', __('media.too_many', ['max' => $max]));
        }

        $uuid = (string) Str::uuid7();
        $path = 'relais/'.$relais->uuid.'/'.$uuid.'.'.$kind->extensionFor($mime);
        Storage::disk(InitiateMediaUpload::DISK)->putFileAs(dirname($path), $file, basename($path));

        $media = new Media([
            'client_uuid' => (string) Str::uuid7(),
            'kind' => $kind,
            'status' => MediaStatus::Uploaded,
            'original_name' => Str::limit(basename($file->getClientOriginalName()), 190, '') ?: null,
            'declared_mime' => $mime,
            'size' => (int) $file->getSize(),
            'declared_sha256' => (string) hash_file('sha256', (string) $file->getRealPath()),
            'disk' => InitiateMediaUpload::DISK,
            'path' => $path,
            'part_size' => (int) config('jerelais.media.part_size_mb') * 1024 * 1024,
        ]);
        $media->uuid = $uuid;
        $media->forceFill(['uploaded_at' => now()]);
        $relais->medias()->save($media);

        return $media;
    }

    /**
     * Retire un fichier du relais (modification par le Relais) : enregistrement, fichier stocké et aperçus.
     */
    public function remove(Media $media): void
    {
        foreach ($media->variants as $variant) {
            Storage::disk($variant->disk)->delete($variant->path);
        }

        Storage::disk($media->disk)->delete($media->path);
        $media->delete();
    }
}
