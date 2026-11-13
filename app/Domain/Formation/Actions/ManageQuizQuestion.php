<?php

namespace App\Domain\Formation\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Formation\Models\QuizQuestion;
use App\Domain\Formation\Support\Reorder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Questions du quiz gérées depuis la console (MOB-05) : énoncé, explication, 2 à 6 réponses dont une seule bonne.
 * Les tentatives passées gardent leur correction. Chaque changement est audité.
 */
class ManageQuizQuestion
{
    public const int MIN_CHOICES = 2;

    public const int MAX_CHOICES = 6;

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{prompt: string, explanation: string}  $data
     * @param  list<array{label: string, is_correct: bool}>  $choices
     */
    public function create(array $data, array $choices, User $actor): QuizQuestion
    {
        $this->ensureValidChoices($choices);

        return DB::transaction(function () use ($data, $choices, $actor): QuizQuestion {
            $question = QuizQuestion::query()->create([
                ...$data,
                'position' => (int) QuizQuestion::query()->max('position') + 1,
                'is_active' => true,
            ]);
            $this->replaceChoices($question, $choices);

            $this->audit->record('formation.question.created', $question, ['prompt' => $question->prompt], $actor);

            return $question;
        });
    }

    /**
     * Les réponses sont mises à jour à leur place (mêmes identifiants) : un quiz déjà ouvert reste corrigeable.
     *
     * @param  array{prompt: string, explanation: string}  $data
     * @param  list<array{label: string, is_correct: bool}>  $choices
     */
    public function update(QuizQuestion $question, array $data, array $choices, User $actor): void
    {
        $this->ensureValidChoices($choices);

        DB::transaction(function () use ($question, $data, $choices, $actor): void {
            $question->fill($data)->save();
            $this->replaceChoices($question, $choices);

            $this->audit->record('formation.question.updated', $question, ['prompt' => $question->prompt], $actor);
        });
    }

    public function setActive(QuizQuestion $question, bool $active, User $actor): void
    {
        if ($question->is_active === $active) {
            return;
        }

        DB::transaction(function () use ($question, $active, $actor): void {
            $question->forceFill(['is_active' => $active])->save();
            $this->audit->record('formation.question.'.($active ? 'published' : 'unpublished'), $question, ['prompt' => $question->prompt], $actor);
        });
    }

    public function move(QuizQuestion $question, int $direction, User $actor): void
    {
        DB::transaction(function () use ($question, $direction, $actor): void {
            $ordered = QuizQuestion::query()->orderBy('position')->orderBy('id')->lockForUpdate()->get()->values()->all();

            if (Reorder::swap($ordered, $question->id, $direction)) {
                $this->audit->record('formation.question.moved', $question, ['prompt' => $question->prompt], $actor);
            }
        });
    }

    public function delete(QuizQuestion $question, User $actor): void
    {
        DB::transaction(function () use ($question, $actor): void {
            $this->audit->record('formation.question.deleted', $question, ['prompt' => $question->prompt], $actor);
            $question->delete();
        });
    }

    /**
     * @param  list<array{label: string, is_correct: bool}>  $choices
     */
    private function ensureValidChoices(array $choices): void
    {
        $count = count($choices);

        if ($count < self::MIN_CHOICES || $count > self::MAX_CHOICES) {
            throw new InvalidArgumentException(__('formation.admin.choices_count', ['min' => self::MIN_CHOICES, 'max' => self::MAX_CHOICES]));
        }

        if (count(array_filter($choices, fn (array $choice): bool => $choice['is_correct'])) !== 1) {
            throw new InvalidArgumentException(__('formation.admin.one_correct'));
        }
    }

    /**
     * @param  list<array{label: string, is_correct: bool}>  $choices
     */
    private function replaceChoices(QuizQuestion $question, array $choices): void
    {
        $existing = $question->choices()->get()->values();

        foreach ($choices as $index => $choice) {
            $current = $existing->get($index);
            $values = [...$choice, 'position' => $index + 1];

            $current === null ? $question->choices()->create($values) : $current->fill($values)->save();
        }

        $existing->slice(count($choices))->each->delete();
        $question->unsetRelation('choices');
    }
}
