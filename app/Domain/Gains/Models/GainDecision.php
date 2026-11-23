<?php

namespace App\Domain\Gains\Models;

use App\Domain\Contribution\Models\RelaisItem;
use App\Models\Concerns\HasPublicUuid;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Décision de rémunération d'un relais, historisée (ADM-27) : accordée, refusée, montant corrigé ou annulée.
 *
 * @property int $id
 * @property string $uuid
 * @property int $relais_item_id
 * @property int|null $commission_id
 * @property int|null $decided_by
 * @property string $decision credited, declined, amended, cancelled
 * @property int|null $amount
 * @property int|null $previous_amount
 * @property string|null $reason
 * @property CarbonImmutable $created_at
 * @property-read User|null $decider
 * @property-read RelaisItem $relaisItem
 */
#[Fillable(['relais_item_id', 'commission_id', 'decided_by', 'decision', 'amount', 'previous_amount', 'reason'])]
class GainDecision extends Model
{
    use HasPublicUuid;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['amount' => 'integer', 'previous_amount' => 'integer', 'created_at' => 'immutable_datetime'];
    }

    public function label(): string
    {
        return __("gains.decisions.{$this->decision}");
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /**
     * @return BelongsTo<RelaisItem, $this>
     */
    public function relaisItem(): BelongsTo
    {
        return $this->belongsTo(RelaisItem::class);
    }
}
