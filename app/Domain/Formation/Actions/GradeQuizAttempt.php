<?php

namespace App\Domain\Formation\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Formation\Models\QuizAttempt;
use App\Domain\Formation\Models\QuizQuestion;
use App\Domain\Settings\Settings;
use App\Models\User;
use InvalidArgumentException;

/**
 * Corrige une tentative de quiz (MOB-05) : toutes les questions publiées doivent recevoir une réponse.
 * Réussite si le pourcentage de bonnes réponses atteint le score minimal paramétré.
 */
class GradeQuizAttempt
{
    public function __construct(
        private readonly Settings $settings,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, string>  $answers  uuid de la question => uuid de la réponse choisie
     */
    public function handle(User $user, array $answers): QuizAttempt
    {
        $questions = QuizQuestion::query()->published()->with('choices')->get();

        if ($questions->isEmpty()) {
            throw new InvalidArgumentException(__('formation.no_quiz'));
        }

        $graded = [];
        $correct = 0;

        foreach ($questions as $question) {
            $choiceUuid = $answers[$question->uuid] ?? null;
            $choice = $choiceUuid === null ? null : $question->choices->firstWhere('uuid', $choiceUuid);

            if ($choice === null) {
                throw new InvalidArgumentException(__('formation.answer_all'));
            }

            $isCorrect = $choice->is_correct;
            $correct += $isCorrect ? 1 : 0;
            $graded[$question->uuid] = ['choice' => $choiceUuid, 'correct' => $isCorrect];
        }

        $score = (int) round($correct / $questions->count() * 100);
        $passingScore = $this->settings->integer('candidature.quiz_min_score');

        $attempt = $user->quizAttempts()->create([
            'score' => $score,
            'correct_count' => $correct,
            'question_count' => $questions->count(),
            'passing_score' => $passingScore,
            'passed' => $score >= $passingScore,
            'answers' => $graded,
        ]);

        $this->audit->record('formation.quiz.attempted', $attempt, ['score' => $score, 'passed' => $attempt->passed], actor: $user);

        return $attempt;
    }

    public static function hasPassed(User $user): bool
    {
        return $user->quizAttempts()->where('passed', true)->exists();
    }

    public static function bestScore(User $user): ?int
    {
        $score = $user->quizAttempts()->max('score');

        return $score === null ? null : (int) $score;
    }
}
