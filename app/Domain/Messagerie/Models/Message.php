<?php

namespace App\Domain\Messagerie\Models;

use App\Models\Concerns\HasPublicUuid;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $uuid
 * @property int $conversation_id
 * @property int $sender_id
 * @property bool $from_staff
 * @property string $body
 * @property string|null $broadcast_uuid
 * @property CarbonImmutable $created_at
 * @property-read User $sender
 * @property-read Conversation $conversation
 */
#[Fillable(['sender_id', 'from_staff', 'body', 'broadcast_uuid', 'created_at'])]
class Message extends Model
{
    use HasPublicUuid;

    /** Horodatage à la microseconde : l'ordre et la lecture des messages restent exacts. */
    protected $dateFormat = 'Y-m-d H:i:s.u';

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['from_staff' => 'boolean', 'created_at' => 'immutable_datetime'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
