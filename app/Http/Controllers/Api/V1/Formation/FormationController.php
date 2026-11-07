<?php

namespace App\Http\Controllers\Api\V1\Formation;

use App\Domain\Formation\Actions\GradeQuizAttempt;
use App\Domain\Formation\Models\FormationModule;
use App\Domain\Formation\Models\QuizChoice;
use App\Domain\Formation\Models\QuizQuestion;
use App\Domain\Settings\Settings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Formation\StoreQuizAttemptRequest;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Formation intégrée et quiz (MOB-05).
 */
#[Group('Formation et quiz', 'Fiches de formation et quiz à réussir avant le dépôt de la candidature (MOB-05).', weight: 6)]
class FormationController extends Controller
{
    /**
     * Fiches de formation.
     *
     * Fiches publiées, dans l'ordre, en Markdown et en HTML, avec une vidéo éventuelle. À lire avant le quiz.
     *
     * @response array{data: list<array{uuid: string, slug: string, title: string, summary: string, body_markdown: string, body_html: string, video_url: string|null}>}
     *
     * @unauthenticated
     */
    public function modules(): JsonResponse
    {
        return response()->json(['data' => FormationModule::query()->published()->get()->map(fn (FormationModule $module): array => [
            'uuid' => $module->uuid,
            'slug' => $module->slug,
            'title' => $module->title,
            'summary' => $module->summary,
            'body_markdown' => $module->body,
            'body_html' => $module->html()->toHtml(),
            'video_url' => $module->video_url,
        ])]);
    }

    /**
     * Questions du quiz.
     *
     * Questions et choix, sans les bonnes réponses. `meta` donne le score minimal (%), mon meilleur score et si le quiz est réussi.
     *
     * @response array{data: list<array{uuid: string, prompt: string, choices: list<array{uuid: string, label: string}>}>, meta: array{passing_score: int, best_score: int|null, passed: bool}}
     */
    public function quiz(Request $request, Settings $settings): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'data' => QuizQuestion::query()->published()->with('choices')->get()->map(fn (QuizQuestion $question): array => [
                'uuid' => $question->uuid,
                'prompt' => $question->prompt,
                'choices' => $question->choices->map(fn (QuizChoice $choice): array => ['uuid' => $choice->uuid, 'label' => $choice->label])->values(),
            ]),
            'meta' => [
                'passing_score' => $settings->integer('candidature.quiz_min_score'),
                'best_score' => GradeQuizAttempt::bestScore($user),
                'passed' => GradeQuizAttempt::hasPassed($user),
            ],
        ]);
    }

    /**
     * Envoyer mes réponses et obtenir la correction.
     *
     * Toutes les questions doivent recevoir une réponse. La correction indique la bonne réponse et son explication.
     */
    public function attempt(StoreQuizAttemptRequest $request, GradeQuizAttempt $grade): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $attempt = $grade->handle($user, $request->answers());
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['answers' => $exception->getMessage()]);
        }

        $questions = QuizQuestion::query()->published()->with('choices')->get();

        return response()->json(['data' => [
            'uuid' => $attempt->uuid,
            'score' => $attempt->score,
            'passing_score' => $attempt->passing_score,
            'passed' => $attempt->passed,
            'correction' => $questions->map(fn (QuizQuestion $question): array => [
                'question' => $question->uuid,
                'chosen' => $attempt->answers[$question->uuid]['choice'] ?? null,
                'correct' => $question->choices->firstWhere('is_correct', true)?->uuid,
                'is_correct' => $attempt->answers[$question->uuid]['correct'] ?? false,
                'explanation' => $question->explanation,
            ])->values(),
        ]], 201);
    }
}
