<?php

namespace App\Domain\Territoire\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug'])]
class LieuType extends Model
{
    use HasPublicUuid;

    /**
     * @return HasMany<Lieu, $this>
     */
    public function lieux(): HasMany
    {
        return $this->hasMany(Lieu::class);
    }
}
