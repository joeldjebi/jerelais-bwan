<?php

namespace App\Domain\Messagerie\Models;

use App\Domain\Access\Concerns\BelongsToCommune;
use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Relais\Models\RelaisProfile;
use App\Models\Concerns\HasPublicUuid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Conversation entre un Relais et l'équipe de sa commune, générale ou rattachée à un relais (MOB-22).
 *
 * @property int $id
 * @property string $uuid
 * @property int $relais_profile_id
 * @property int $commune_id
 * @property int|null $relais_item_id
 * @property CarbonImmutable|null $last_message_at
 * @property CarbonImmutable|null $relais_read_at
 * @property CarbonImmutable|null $staff_read_at
 * @property CarbonImmutable|null $relais_notified_at
 * @property-read RelaisProfile $relaisProfile
 * @property-read RelaisItem|null $relaisItem
 * @property-read Collection<int, Message> $messages
 */
#[Fillable(['relais_profile_id', 'commune_id', 'relais_item_id'])]
class Conversation extends Model
{
    use BelongsToCommune, HasPublicUuid;

    /** Horodatage à la microseconde : l'ordre et la lecture des messages restent exacts. */
    protected $dateFormat = 'Y-m-d H:i:s.u';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_message_at' => 'immutable_datetime',
            'relais_read_at' => 'immutable_datetime',
            'staff_read_at' => 'immutable_datetime',
            'relais_notified_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<RelaisProfile, $this>
     */
    public function relaisProfile(): BelongsTo
    {
        return $this->belongsTo(RelaisProfile::class);
    }

    /**
     * @return BelongsTo<RelaisItem, $this>
     */
    public function relaisItem(): BelongsTo
    {
        return $this->belongsTo(RelaisItem::class);
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at')->orderBy('id');
    }

    public function unreadForStaff(): bool
    {
        return $this->last_message_at !== null && ($this->staff_read_at === null || $this->staff_read_at->lt($this->last_message_at))
            && $this->messages()->reorder()->latest('id')->value('from_staff') === false;
    }
}
