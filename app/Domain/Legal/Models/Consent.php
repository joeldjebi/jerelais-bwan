<?php

namespace App\Domain\Legal\Models;

use App\Models\Concerns\HasPublicUuid;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Acceptation horodatée d'une version précise d'un texte (MOB-04).
 *
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property int $legal_document_id
 * @property CarbonImmutable $accepted_at
 * @property string $channel
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property-read LegalDocument $legalDocument
 */
#[Fillable(['legal_document_id', 'accepted_at', 'channel', 'ip_address', 'user_agent'])]
class Consent extends Model
{
    use HasPublicUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'accepted_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<LegalDocument, $this>
     */
    public function legalDocument(): BelongsTo
    {
        return $this->belongsTo(LegalDocument::class);
    }
}
