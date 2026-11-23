<?php

namespace App\Domain\Gains\Models;

use App\Domain\Gains\Enums\PaymentMethod;
use App\Models\Concerns\HasPublicUuid;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Numéro Mobile Money où le Relais reçoit ses retraits (MOB-26), deux au plus par Relais. Numéro chiffré.
 *
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property PaymentMethod $method
 * @property string $phone
 * @property string $holder_name
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 */
#[Fillable(['user_id', 'method', 'phone', 'holder_name'])]
class PayoutAccount extends Model
{
    use HasPublicUuid;

    /**
     * Nombre de numéros Mobile Money qu'un Relais peut enregistrer.
     */
    public const int MAX_PER_USER = 2;

    /**
     * Moyens proposés au Relais (Mobile Money uniquement).
     *
     * @return list<PaymentMethod>
     */
    public static function methods(): array
    {
        return [PaymentMethod::Wave, PaymentMethod::OrangeMoney, PaymentMethod::MtnMomo, PaymentMethod::MoovMoney];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'phone' => 'encrypted',
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
}
