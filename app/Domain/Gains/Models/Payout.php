<?php

namespace App\Domain\Gains\Models;

use App\Domain\Gains\Enums\PaymentMethod;
use App\Models\Concerns\HasPublicUuid;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Paiement reçu par un Relais (ADM-27) : un versement Mobile Money qui solde une ou plusieurs commissions.
 *
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property int $amount
 * @property PaymentMethod $method
 * @property string $reference
 * @property string|null $phone
 * @property CarbonImmutable $paid_at
 * @property int|null $paid_by
 * @property-read User $user
 * @property-read User|null $payer
 */
#[Fillable(['user_id', 'amount', 'method', 'reference', 'phone', 'paid_at', 'paid_by'])]
#[Hidden(['phone'])]
class Payout extends Model
{
    use HasPublicUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'method' => PaymentMethod::class,
            'phone' => 'encrypted',
            'paid_at' => 'immutable_datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    /**
     * @return HasMany<Commission, $this>
     */
    public function commissions(): HasMany
    {
        return $this->hasMany(Commission::class);
    }
}
