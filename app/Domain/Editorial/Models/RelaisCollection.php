<?php

namespace App\Domain\Editorial\Models;

use App\Domain\Contribution\Models\RelaisItem;
use App\Models\Concerns\HasPublicUuid;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Collection éditoriale : relais regroupés par sujet ou par événement (ADM-15), ex. « Inondations juin ».
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string|null $description
 * @property int $created_by
 * @property-read Collection<int, RelaisItem> $relais
 * @property-read User $creator
 */
#[Table('collections')]
#[Fillable(['name', 'description'])]
class RelaisCollection extends Model
{
    use HasPublicUuid;

    /**
     * @return BelongsToMany<RelaisItem, $this>
     */
    public function relais(): BelongsToMany
    {
        return $this->belongsToMany(RelaisItem::class, 'collection_relais_item', 'collection_id', 'relais_item_id')
            ->withPivot(['added_by', 'created_at']);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
