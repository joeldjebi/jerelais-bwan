<?php

namespace App\Domain\Gains\Models;

use App\Domain\Gains\Enums\PaymentMethod;
use App\Domain\Gains\Enums\PayoutStatus;
use App\Models\Concerns\HasPublicUuid;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Retrait du portefeuille d'un Relais (MOB-26, ADM-27) : demandé par le Relais, puis payé par Mobile Money par
 * l'administration (avec la référence de la transaction) ou refusé avec un motif.
 *
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property int $amount
 * @property PayoutStatus $status
 * @property PaymentMethod $method
 * @property string|null $provider simulation, manual, ou le prestataire de paiement
 * @property string|null $reference
 * @property string|null $phone
 * @property string|null $holder_name
 * @property CarbonImmutable|null $requested_at
 * @property CarbonImmutable|null $paid_at
 * @property string|null $rejection_reason
 * @property CarbonImmutable|null $rejected_at
 * @property int|null $paid_by
 * @property-read User $user
 * @property-read User|null $payer
 */
#[Fillable(['user_id', 'amount', 'status', 'method', 'provider', 'reference', 'phone', 'holder_name', 'requested_at', 'paid_at', 'paid_by'])]
#[Hidden(['phone'])]
class Payout extends Model
{
    use HasPublicUuid;

    public function isSimulated(): bool
    {
        return $this->provider === 'simulation';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'status' => PayoutStatus::class,
            'requested_at' => 'immutable_datetime',
            'rejected_at' => 'immutable_datetime',
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
