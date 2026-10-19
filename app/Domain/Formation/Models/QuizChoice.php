<?php

namespace App\Domain\Formation\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Réponse proposée. « is_correct » n'est jamais envoyé avant la correction.
 *
 * @property int $id
 * @property string $uuid
 * @property int $quiz_question_id
 * @property string $label
 * @property bool $is_correct
 * @property int $position
 */
#[Fillable(['label', 'is_correct', 'position'])]
#[Hidden(['is_correct'])]
class QuizChoice extends Model
{
    use HasPublicUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<QuizQuestion, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(QuizQuestion::class, 'quiz_question_id');
    }
}
