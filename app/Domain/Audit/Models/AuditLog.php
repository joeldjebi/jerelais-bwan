<?php

namespace App\Domain\Audit\Models;

use App\Models\Concerns\HasPublicUuid;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Ligne du journal d'audit. En ajout seul : la base refuse toute modification (trigger),
 * le modèle aussi.
 *
 * @property int $id
 * @property string $uuid
 * @property CarbonImmutable $occurred_at
 * @property int|null $actor_id
 * @property string $action
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property int|null $commune_id
 * @property array<string, mixed>|null $payload
 * @property string|null $prev_hash
 * @property string $hash
 */
#[Fillable(['occurred_at', 'actor_id', 'action', 'subject_type', 'subject_id', 'commune_id', 'ip_address', 'user_agent', 'payload', 'prev_hash', 'hash'])]
class AuditLog extends Model
{
    use HasPublicUuid;

    public const UPDATED_AT = null;

    public const CREATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'immutable_datetime',
            'payload' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Le journal d’audit est en ajout seul.'));
        static::deleting(fn () => throw new LogicException('Le journal d’audit est en ajout seul.'));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * Empreinte de la ligne, chaînée à la précédente.
     */
    public static function computeHash(?string $previousHash, self $entry): string
    {
        $payload = $entry->payload ?? [];
        ksort($payload);

        return hash('sha256', implode('|', [
            $previousHash ?? '',
            $entry->uuid,
            $entry->occurred_at->utc()->format('Y-m-d H:i:s'),
            (string) $entry->actor_id,
            $entry->action,
            (string) $entry->subject_type,
            (string) $entry->subject_id,
            (string) $entry->commune_id,
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]));
    }
}
