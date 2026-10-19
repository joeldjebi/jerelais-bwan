<?php

namespace App\Domain\Media\Models;

use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Media\Enums\MediaKind;
use App\Domain\Media\Enums\MediaStatus;
use App\Models\Concerns\HasPublicUuid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Fichier d'un relais, stocké en privé sur le disque « medias » (Wasabi). L'original n'est jamais modifié.
 *
 * @property int $id
 * @property string $uuid
 * @property int $relais_item_id
 * @property string $client_uuid
 * @property MediaKind $kind
 * @property MediaStatus $status
 * @property string|null $original_name
 * @property string $declared_mime
 * @property string|null $mime
 * @property int $size
 * @property string|null $declared_sha256
 * @property string|null $sha256
 * @property string|null $phash
 * @property string $disk
 * @property string $path
 * @property string|null $upload_id
 * @property int $part_size
 * @property bool $is_imported
 * @property CarbonImmutable|null $device_captured_at
 * @property CarbonImmutable|null $captured_at
 * @property string|null $duration_s
 * @property int|null $width
 * @property int|null $height
 * @property array<string, mixed>|null $metadata
 * @property string|null $rejection
 * @property CarbonImmutable|null $uploaded_at
 * @property CarbonImmutable|null $processed_at
 * @property CarbonImmutable|null $purged_at
 * @property-read RelaisItem $relaisItem
 * @property-read Collection<int, MediaVariant> $variants
 */
#[Table('medias')]
#[Fillable([
    'client_uuid', 'kind', 'status', 'original_name', 'declared_mime', 'size', 'declared_sha256',
    'disk', 'path', 'part_size', 'is_imported', 'device_captured_at',
])]
#[Hidden(['disk', 'path', 'upload_id'])]
class Media extends Model
{
    use HasPublicUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => MediaKind::class,
            'status' => MediaStatus::class,
            'size' => 'integer',
            'part_size' => 'integer',
            'is_imported' => 'boolean',
            'device_captured_at' => 'immutable_datetime',
            'captured_at' => 'immutable_datetime',
            'width' => 'integer',
            'height' => 'integer',
            'metadata' => 'array',
            'uploaded_at' => 'immutable_datetime',
            'processed_at' => 'immutable_datetime',
            'purged_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<RelaisItem, $this>
     */
    public function relaisItem(): BelongsTo
    {
        return $this->belongsTo(RelaisItem::class);
    }

    /**
     * @return HasMany<MediaVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(MediaVariant::class);
    }

    public function partCount(): int
    {
        return max(1, (int) ceil($this->size / $this->part_size));
    }
}
