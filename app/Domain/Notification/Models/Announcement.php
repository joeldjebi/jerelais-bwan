<?php

namespace App\Domain\Notification\Models;

use App\Domain\Notification\Enums\AnnouncementAudience;
use App\Domain\Notification\Enums\AnnouncementScreen;
use App\Domain\Notification\Enums\AnnouncementStatus;
use App\Models\Concerns\HasPublicUuid;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Annonce de l'administration aux Relais (MOB-21) : notification push, SMS de secours facultatif, boîte de réception
 * de l'application.
 *
 * @property int $id
 * @property string $uuid
 * @property string $title
 * @property string $body
 * @property AnnouncementScreen $screen
 * @property AnnouncementAudience $audience
 * @property list<string>|null $audience_ids
 * @property bool $with_sms
 * @property AnnouncementStatus $status
 * @property CarbonImmutable|null $scheduled_at
 * @property CarbonImmutable|null $sent_at
 * @property int $recipients_count
 * @property int $push_count
 * @property int $sms_count
 * @property int $unreached_count
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property-read User|null $author
 */
#[Fillable(['title', 'body', 'screen', 'audience', 'audience_ids', 'with_sms', 'status', 'scheduled_at', 'created_by'])]
class Announcement extends Model
{
    use HasPublicUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'screen' => AnnouncementScreen::class,
            'audience' => AnnouncementAudience::class,
            'audience_ids' => 'array',
            'with_sms' => 'boolean',
            'status' => AnnouncementStatus::class,
            'scheduled_at' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<AnnouncementRecipient, $this>
     */
    public function recipients(): HasMany
    {
        return $this->hasMany(AnnouncementRecipient::class);
    }
}
