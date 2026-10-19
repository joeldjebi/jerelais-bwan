<?php

namespace App\Domain\Validation\Models;

use App\Domain\Validation\Enums\RejectionTarget;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Motif de rejet paramétrable (COO-15 pour les relais, COO-02 pour les candidatures).
 *
 * @property int $id
 * @property string $uuid
 * @property RejectionTarget $applies_to
 * @property string $label
 * @property string $slug
 * @property int $position
 * @property bool $is_active
 */
#[Fillable(['applies_to', 'label', 'slug', 'position', 'is_active'])]
class RejectionReason extends Model
{
    use HasPublicUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'applies_to' => RejectionTarget::class,
            'position' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param  Builder<RejectionReason>  $query
     */
    #[Scope]
    protected function activeFor(Builder $query, RejectionTarget $target): void
    {
        $query->where('applies_to', $target->value)->where('is_active', true)->orderBy('position');
    }
}
