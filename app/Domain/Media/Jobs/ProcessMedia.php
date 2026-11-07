<?php

namespace App\Domain\Media\Jobs;

use App\Domain\Audit\AuditLogger;
use App\Domain\Media\Contracts\VirusScanner;
use App\Domain\Media\Enums\MediaStatus;
use App\Domain\Media\Models\Media;
use App\Domain\Media\Storage\MultipartStorage;
use App\Domain\Media\Support\MediaInspector;
use App\Domain\Media\Support\PerceptualHash;
use App\Domain\Media\Support\TemporaryPath;
use App\Domain\Settings\Settings;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * Contrôle d'un média reçu, avant toute mise à disposition (§10) :
 * type réel, antivirus, empreinte SHA-256 (comparée à celle annoncée), métadonnées d'origine,
 * durée maximale, vignette sans EXIF, empreinte perceptuelle. Un fichier non conforme est retiré.
 */
class ProcessMedia implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 600;

    public function __construct(public readonly int $mediaId)
    {
        $this->onQueue('medias');
    }

    public function handle(MultipartStorage $storage, VirusScanner $scanner, MediaInspector $inspector, Settings $settings, AuditLogger $audit): void
    {
        $media = Media::query()->with('relaisItem')->find($this->mediaId);

        if ($media === null || ! in_array($media->status, [MediaStatus::Uploaded, MediaStatus::Processing], true)) {
            return;
        }

        $media->forceFill(['status' => MediaStatus::Processing])->save();
        $local = TemporaryPath::make('bin');
        $thumbPath = TemporaryPath::make('jpg');

        try {
            $storage->download($media->path, $local);

            $mime = $inspector->mimeOf($local);
            $size = (int) filesize($local);

            if (! $inspector->matchesKind($media->kind, $mime)) {
                $this->reject($media, 'type', $audit);

                return;
            }

            if ($size > $settings->integer('media.max_file_size_mb') * 1024 * 1024) {
                $this->reject($media, 'too_large', $audit);

                return;
            }

            $threat = $scanner->scan($local);

            if ($threat !== null) {
                $this->reject($media, 'infected', $audit, ['threat' => $threat]);

                return;
            }

            $sha256 = (string) hash_file('sha256', $local);

            if ($media->declared_sha256 !== null && ! hash_equals($media->declared_sha256, $sha256)) {
                $this->reject($media, 'checksum', $audit);

                return;
            }

            $meta = $inspector->metadata($media->kind, $local, $mime);
            $maxDuration = $media->kind->maxDurationSetting();

            if ($maxDuration !== null && $meta['duration_s'] !== null && $meta['duration_s'] > $settings->integer($maxDuration) + 1) {
                $this->reject($media, 'too_long', $audit);

                return;
            }

            $thumb = $inspector->thumbnail($media->kind, $local, $mime, $meta['duration_s'], $thumbPath);

            if ($thumb !== null) {
                $variantPath = dirname($media->path).'/vignettes/'.$media->uuid.'.jpg';
                Storage::disk($media->disk)->put($variantPath, (string) file_get_contents($thumbPath));
                $media->variants()->updateOrCreate(['kind' => 'thumbnail'], [
                    'disk' => $media->disk,
                    'path' => $variantPath,
                    'mime' => 'image/jpeg',
                    'size' => (int) filesize($thumbPath),
                    'width' => imagesx($thumb),
                    'height' => imagesy($thumb),
                ]);
            }

            $media->forceFill([
                'status' => MediaStatus::Ready,
                'mime' => $mime,
                'size' => $size,
                'sha256' => $sha256,
                'phash' => $thumb === null ? null : PerceptualHash::fromImage($thumb),
                'captured_at' => $meta['captured_at'],
                'duration_s' => $meta['duration_s'],
                'width' => $meta['width'],
                'height' => $meta['height'],
                'metadata' => $meta['metadata'] === [] ? null : $meta['metadata'],
                'processed_at' => now(),
            ])->save();

            $audit->record('media.received', $media->relaisItem, [
                'media' => $media->uuid, 'kind' => $media->kind->value, 'sha256' => $sha256,
            ], communeId: $media->relaisItem->commune_id);
        } finally {
            @unlink($local);
            @unlink($thumbPath);
        }
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function reject(Media $media, string $reason, AuditLogger $audit, array $extra = []): void
    {
        Storage::disk($media->disk)->delete($media->path);
        $media->forceFill(['status' => MediaStatus::Rejected, 'rejection' => $reason, 'processed_at' => now()])->save();

        $audit->record('media.rejected', $media->relaisItem, ['media' => $media->uuid, 'reason' => $reason, ...$extra], communeId: $media->relaisItem->commune_id);
    }
}
