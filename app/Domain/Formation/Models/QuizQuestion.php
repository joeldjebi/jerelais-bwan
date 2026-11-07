<?php

namespace App\Domain\Formation\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Question du quiz : une seule bonne réponse, explication affichée après la correction.
 *
 * @property int $id
 * @property string $uuid
 * @property string $prompt
 * @property string $explanation
 * @property int $position
 * @property bool $is_active
 * @property-read Collection<int, QuizChoice> $choices
 */
#[Fillable(['prompt', 'explanation', 'position', 'is_active'])]
class QuizQuestion extends Model
{
    use HasPublicUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<QuizChoice, $this>
     */
    public function choices(): HasMany
    {
        return $this->hasMany(QuizChoice::class)->orderBy('position');
    }

    /**
     * @param  Builder<QuizQuestion>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('position');
    }
}
