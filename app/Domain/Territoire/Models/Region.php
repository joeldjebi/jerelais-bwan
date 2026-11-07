<?php

namespace App\Domain\Territoire\Models;

use App\Models\Concerns\HasPublicUuid;
use Database\Factories\RegionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'district', 'code'])]
#[UseFactory(RegionFactory::class)]
class Region extends Model
{
    /** @use HasFactory<RegionFactory> */
    use HasFactory, HasPublicUuid;

    /**
     * @return HasMany<Commune, $this>
     */
    public function communes(): HasMany
    {
        return $this->hasMany(Commune::class);
    }
}
