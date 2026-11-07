<?php

namespace App\Domain\Contribution\Models;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Historique des statuts d'un relais (qui, quand, pourquoi).
 *
 * @property int $id
 * @property int $relais_item_id
 * @property string|null $from
 * @property string $to
 * @property int|null $actor_id
 * @property string|null $reason
 * @property CarbonImmutable $created_at
 * @property-read User|null $actor
 */
#[Table('relais_status_history')]
#[Fillable(['from', 'to', 'actor_id', 'reason', 'created_at'])]
class RelaisStatusChange extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['created_at' => 'immutable_datetime'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
