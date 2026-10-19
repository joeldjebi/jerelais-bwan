<?php

namespace App\Domain\Candidature\Models;

use App\Domain\Candidature\Enums\DocumentType;
use App\Models\Concerns\HasPublicUuid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pièce d'un dossier de candidature, sur le disque privé « identity ».
 * Le chemin n'est jamais exposé : consultation par URL signée de courte durée, auditée.
 *
 * @property int $id
 * @property string $uuid
 * @property int $candidature_id
 * @property DocumentType $type
 * @property string $disk
 * @property string $path
 * @property string $mime
 * @property int $size
 * @property string $sha256
 * @property CarbonImmutable $scanned_at
 * @property CarbonImmutable|null $created_at
 * @property-read Candidature $candidature
 */
#[Fillable(['type', 'disk', 'path', 'mime', 'size', 'sha256', 'scanned_at'])]
#[Hidden(['disk', 'path'])]
class CandidatureDocument extends Model
{
    use HasPublicUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DocumentType::class,
            'size' => 'integer',
            'scanned_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Candidature, $this>
     */
    public function candidature(): BelongsTo
    {
        return $this->belongsTo(Candidature::class);
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime, 'image/');
    }
}
