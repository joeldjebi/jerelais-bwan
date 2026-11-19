<?php

namespace App\Domain\Notification\Models;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Destinataire d'une annonce : canal utilisé (push, SMS ou aucun) et lecture dans l'application.
 *
 * @property int $id
 * @property int $announcement_id
 * @property int $user_id
 * @property string|null $channel
 * @property CarbonImmutable|null $read_at
 * @property CarbonImmutable|null $created_at
 * @property-read Announcement $announcement
 */
#[Fillable(['announcement_id', 'user_id', 'channel', 'read_at'])]
class AnnouncementRecipient extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'read_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Announcement, $this>
     */
    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
