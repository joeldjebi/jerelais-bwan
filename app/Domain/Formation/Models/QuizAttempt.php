<?php

namespace App\Domain\Formation\Models;

use App\Models\Concerns\HasPublicUuid;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tentative de quiz notée. Le meilleur score est présenté au coordonnateur (COO-01).
 *
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property int $score
 * @property int $correct_count
 * @property int $question_count
 * @property int $passing_score
 * @property bool $passed
 * @property array<string, array{choice: string|null, correct: bool}> $answers
 * @property CarbonImmutable|null $created_at
 */
#[Fillable(['score', 'correct_count', 'question_count', 'passing_score', 'passed', 'answers'])]
class QuizAttempt extends Model
{
    use HasPublicUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'correct_count' => 'integer',
            'question_count' => 'integer',
            'passing_score' => 'integer',
            'passed' => 'boolean',
            'answers' => 'array',
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
