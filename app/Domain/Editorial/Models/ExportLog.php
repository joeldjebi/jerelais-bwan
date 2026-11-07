<?php

namespace App\Domain\Editorial\Models;

use App\Models\Concerns\HasPublicUuid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Trace de chaque téléchargement de médias d'origine (ADM-11, ADM-12).
 *
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property int $relais_item_id
 * @property string $kind
 * @property int|null $media_id
 * @property string|null $derogation_reason
 * @property CarbonImmutable $created_at
 */
#[Fillable(['user_id', 'relais_item_id', 'kind', 'media_id', 'derogation_reason', 'created_at'])]
class ExportLog extends Model
{
    use HasPublicUuid;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['created_at' => 'immutable_datetime'];
    }
}
