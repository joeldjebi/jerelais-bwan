<?php

namespace App\Domain\Media\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Version dérivée d'un média (vignette sans EXIF, aperçu allégé).
 *
 * @property int $id
 * @property int $media_id
 * @property string $kind
 * @property string $disk
 * @property string $path
 * @property string $mime
 * @property int $size
 * @property int|null $width
 * @property int|null $height
 */
#[Fillable(['kind', 'disk', 'path', 'mime', 'size', 'width', 'height'])]
#[Hidden(['disk', 'path'])]
class MediaVariant extends Model
{
    /**
     * @return BelongsTo<Media, $this>
     */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }
}
