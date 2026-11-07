<?php

namespace App\Domain\Media\Actions;

use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Contribution\States\ComplementRequested;
use App\Domain\Contribution\States\Uploading;
use App\Domain\Contribution\Support\RelaisSender;
use App\Domain\Media\Enums\MediaKind;
use App\Domain\Media\Enums\MediaStatus;
use App\Domain\Media\Exceptions\MediaRefused;
use App\Domain\Media\Models\Media;
use App\Domain\Media\Storage\MultipartStorage;
use App\Domain\Settings\Settings;
use Illuminate\Support\Str;

/**
 * Ouvre l'envoi d'un média (MOB-11, MOB-16) : contrôle du nombre, de la taille et du format annoncés,
 * nom de fichier aléatoire, envoi multipart ouvert sur le stockage. Idempotent sur le « client_uuid » du média.
 */
class InitiateMediaUpload
{
    public const DISK = 'medias';

    public function __construct(
        private readonly MultipartStorage $storage,
        private readonly Settings $settings,
    ) {}

    /**
     * @param  array{client_uuid: string, kind: string, mime: string, size: int, sha256?: string|null, filename?: string|null, is_imported?: bool, device_captured_at?: string|null}  $data
     */
    public function handle(RelaisItem $relais, array $data): Media
    {
        RelaisSender::ensureCanSend($relais->user);

        $existing = $relais->medias()->where('client_uuid', $data['client_uuid'])->first();

        if ($existing !== null) {
            return $existing;
        }

        if (! $relais->state instanceof Uploading && ! $relais->state instanceof ComplementRequested) {
            throw new MediaRefused('relais', __('relais_items.errors.not_editable'));
        }

        $kind = MediaKind::from($data['kind']);

        if (! in_array($data['mime'], $kind->mimeTypes(), true)) {
            throw new MediaRefused('mime', __('media.unsupported_type'));
        }

        $maxBytes = $this->settings->integer('media.max_file_size_mb') * 1024 * 1024;

        if ($data['size'] > $maxBytes) {
            throw new MediaRefused('size', __('media.too_large', ['max' => $this->settings->integer('media.max_file_size_mb')]));
        }

        $max = $this->settings->integer('media.max_medias_per_relais');

        if ($relais->medias()->where('status', '!=', MediaStatus::Rejected->value)->count() >= $max) {
            throw new MediaRefused('relais', __('media.too_many', ['max' => $max]));
        }

        $uuid = (string) Str::uuid7();
        $path = 'relais/'.$relais->uuid.'/'.$uuid.'.'.$kind->extensionFor($data['mime']);

        $media = new Media([
            'client_uuid' => $data['client_uuid'],
            'kind' => $kind,
            'status' => MediaStatus::Pending,
            'original_name' => isset($data['filename']) ? Str::limit(basename($data['filename']), 190, '') : null,
            'declared_mime' => $data['mime'],
            'size' => $data['size'],
            'declared_sha256' => isset($data['sha256']) ? strtolower($data['sha256']) : null,
            'disk' => self::DISK,
            'path' => $path,
            'part_size' => (int) config('jerelais.media.part_size_mb') * 1024 * 1024,
            'is_imported' => (bool) ($data['is_imported'] ?? false),
            'device_captured_at' => $data['device_captured_at'] ?? null,
        ]);
        $media->uuid = $uuid;
        $media->upload_id = $this->storage->initiate($path, $data['mime']);
        $relais->medias()->save($media);

        return $media;
    }
}
