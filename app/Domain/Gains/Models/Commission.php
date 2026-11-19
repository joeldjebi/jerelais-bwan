<?php

namespace App\Domain\Gains\Models;

use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Gains\Enums\CommissionKind;
use App\Domain\Gains\Enums\CommissionStatus;
use App\Domain\Gains\Enums\PaymentMethod;
use App\Models\Concerns\HasPublicUuid;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\CommissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Commission d'un Relais (MOB-26, ADM-27) : montant dû pour un relais validé ou diffusé, puis payé par Mobile Money.
 *
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property int|null $relais_item_id
 * @property CommissionKind $kind
 * @property int $amount
 * @property CommissionStatus $status
 * @property int|null $payout_id
 * @property CarbonImmutable|null $paid_at
 * @property int|null $paid_by
 * @property PaymentMethod|null $payment_method
 * @property string|null $payment_reference
 * @property string|null $payment_phone
 * @property CarbonImmutable|null $cancelled_at
 * @property string|null $cancel_reason
 * @property CarbonImmutable|null $created_at
 * @property-read User $user
 * @property-read RelaisItem|null $relaisItem
 * @property-read User|null $payer
 */
#[Fillable(['user_id', 'relais_item_id', 'kind', 'amount', 'status'])]
#[Hidden(['payment_phone'])]
class Commission extends Model
{
    /** @use HasFactory<CommissionFactory> */
    use HasFactory, HasPublicUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => CommissionKind::class,
            'status' => CommissionStatus::class,
            'amount' => 'integer',
            'payment_method' => PaymentMethod::class,
            'payment_phone' => 'encrypted',
            'paid_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
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
     * @return BelongsTo<RelaisItem, $this>
     */
    public function relaisItem(): BelongsTo
    {
        return $this->belongsTo(RelaisItem::class);
    }

    /**
     * @return BelongsTo<Payout, $this>
     */
    public function payout(): BelongsTo
    {
        return $this->belongsTo(Payout::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    /**
     * @param  Builder<Commission>  $query
     */
    public function scopeDue(Builder $query): void
    {
        $query->where('status', CommissionStatus::Due->value);
    }

    protected static function newFactory(): CommissionFactory
    {
        return CommissionFactory::new();
    }
}
