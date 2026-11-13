<?php

namespace App\Domain\Media\Actions;

use App\Domain\Contribution\Actions\SubmitWhenUploaded;
use App\Domain\Media\Enums\MediaStatus;
use App\Domain\Media\Models\Media;
use App\Domain\Media\Storage\MultipartStorage;
use Throwable;

/**
 * Envois de médias jamais terminés : l'envoi multipart est annulé sur le stockage (plus de morceaux facturés)
 * et le média est marqué abandonné.
 */
class PurgeAbandonedUploads
{
    public const AFTER_HOURS = 48;

    public function __construct(
        private readonly MultipartStorage $storage,
        private readonly SubmitWhenUploaded $submitWhenUploaded,
    ) {}

    public function handle(): int
    {
        $purged = 0;

        Media::query()
            ->where('status', MediaStatus::Pending->value)
            ->where('created_at', '<', now()->subHours(self::AFTER_HOURS))
            ->each(function (Media $media) use (&$purged): void {
                try {
                    if ($media->upload_id !== null) {
                        $this->storage->abort($media->path, $media->upload_id);
                    }
                } catch (Throwable $exception) {
                    report($exception);
                }

                $media->forceFill(['status' => MediaStatus::Rejected, 'rejection' => 'abandoned', 'upload_id' => null, 'processed_at' => now()])->save();
                $purged++;

                // Un fichier abandonné ne doit pas bloquer l'envoi automatique d'un relais dont les autres fichiers sont arrivés.
                $this->submitWhenUploaded->handle($media->relaisItem);
            });

        return $purged;
    }
}
