<?php

namespace App\Domain\Media\Actions;

use App\Domain\Media\Enums\MediaStatus;
use App\Domain\Media\Exceptions\MediaRefused;
use App\Domain\Media\Jobs\ProcessMedia;
use App\Domain\Media\Models\Media;
use App\Domain\Media\Storage\MultipartStorage;
use Throwable;

/**
 * Clôt l'envoi d'un média une fois tous ses morceaux déposés, puis lance son contrôle en arrière-plan.
 * Idempotent : un média déjà reçu n'est pas clos deux fois.
 */
class CompleteMediaUpload
{
    public function __construct(private readonly MultipartStorage $storage) {}

    /**
     * @param  list<array{number: int, etag: string}>  $parts
     */
    public function handle(Media $media, array $parts): Media
    {
        if ($media->status !== MediaStatus::Pending) {
            return $media;
        }

        $numbers = array_map(fn (array $part): int => $part['number'], $parts);
        sort($numbers);

        if ($numbers !== range(1, $media->partCount())) {
            throw new MediaRefused('parts', __('media.parts_incomplete', ['count' => $media->partCount()]));
        }

        try {
            $this->storage->complete($media->path, (string) $media->upload_id, $parts);
        } catch (Throwable $exception) {
            report($exception);

            throw new MediaRefused('parts', __('media.parts_invalid'));
        }

        $media->forceFill(['status' => MediaStatus::Uploaded, 'upload_id' => null, 'uploaded_at' => now()])->save();

        ProcessMedia::dispatch($media->id);

        return $media;
    }
}
