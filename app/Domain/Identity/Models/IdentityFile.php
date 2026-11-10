<?php

namespace App\Domain\Identity\Models;

use App\Domain\Identity\Enums\IdentityFileKind;
use App\Models\Concerns\HasPublicUuid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fichier d'une vérification d'identité, sur le disque privé « identity ».
 * Le chemin n'est jamais exposé : consultation par URL signée de courte durée, auditée.
 *
 * @property int $id
 * @property string $uuid
 * @property int $identity_verification_id
 * @property IdentityFileKind $kind
 * @property string $disk
 * @property string $path
 * @property string $mime
 * @property int $size
 * @property string $sha256
 * @property CarbonImmutable $scanned_at
 * @property CarbonImmutable|null $created_at
 * @property-read IdentityVerification $verification
 */
#[Fillable(['kind', 'disk', 'path', 'mime', 'size', 'sha256', 'scanned_at'])]
#[Hidden(['disk', 'path'])]
class IdentityFile extends Model
{
    use HasPublicUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => IdentityFileKind::class,
            'size' => 'integer',
            'scanned_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<IdentityVerification, $this>
     */
    public function verification(): BelongsTo
    {
        return $this->belongsTo(IdentityVerification::class, 'identity_verification_id');
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime, 'image/');
    }
}
