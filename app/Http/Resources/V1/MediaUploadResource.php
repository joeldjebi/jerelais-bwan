<?php

namespace App\Http\Resources\V1;

use App\Domain\Media\Enums\MediaStatus;
use App\Domain\Media\Models\Media;
use App\Domain\Media\Storage\MultipartStorage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * État d'un média et, tant que l'envoi n'est pas terminé, les URL signées des morceaux manquants (reprise, MOB-16).
 *
 * @mixin Media
 */
class MediaUploadResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $upload = null;

        if ($this->status === MediaStatus::Pending && $this->upload_id !== null) {
            $storage = app(MultipartStorage::class);
            $received = $storage->listParts($this->path, $this->upload_id);
            $done = array_column($received, 'number');
            $minutes = (int) config('jerelais.media.part_url_minutes');

            $upload = [
                'part_size' => $this->part_size,
                'part_count' => $this->partCount(),
                'uploaded_parts' => $received,
                'missing_parts' => collect(range(1, $this->partCount()))
                    ->reject(fn (int $number): bool => in_array($number, $done, true))
                    ->map(fn (int $number): array => ['number' => $number, 'url' => $storage->presignPart($this->path, (string) $this->upload_id, $number, $minutes)])
                    ->values(),
                'expires_in_minutes' => $minutes,
            ];
        }

        return [
            'uuid' => $this->uuid,
            'client_uuid' => $this->client_uuid,
            'kind' => $this->kind->value,
            'status' => $this->status->value,
            'rejection' => $this->rejection === null ? null : __("media.rejections.{$this->rejection}"),
            'size' => $this->size,
            'is_imported' => $this->is_imported,
            'duration_s' => $this->duration_s === null ? null : (float) $this->duration_s,
            'upload' => $upload,
        ];
    }
}
