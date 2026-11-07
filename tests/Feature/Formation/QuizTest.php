<?php

use App\Domain\Access\Enums\Role;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Candidature\Models\Candidature;
use App\Domain\Formation\Models\QuizAttempt;
use App\Domain\Formation\Models\QuizQuestion;
use App\Domain\Settings\Settings;
use App\Livewire\Relais\Dossier;
use App\Models\User;
use Database\Seeders\FormationSeeder;
use Database\Seeders\LegalDocumentsSeeder;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed([FormationSeeder::class, LegalDocumentsSeeder::class]);
    $this->candidat = User::factory()->withRole(Role::Candidat)->create();
});

/**
 * @return array<string, string> réponses justes, sauf les $wrong premières
 */
function quizAnswers(int $wrong = 0): array
{
    $answers = [];

    foreach (QuizQuestion::query()->published()->with('choices')->get() as $index => $question) {
        $choice = $index < $wrong ? $question->choices->firstWhere('is_correct', false) : $question->choices->firstWhere('is_correct', true);
        $answers[$question->uuid] = $choice->uuid;
    }

    return $answers;
}

it('publie les fiches de formation [MOB-05]', function () {
    $this->getJson(route('api.v1.formation.index'))
        ->assertOk()
        ->assertJsonCount(4, 'data')
        ->assertJsonPath('data.0.title', 'Bien filmer');
});

it('envoie les questions sans jamais révéler les bonnes réponses', function () {
    Sanctum::actingAs($this->candidat);

    $response = $this->getJson(route('api.v1.quiz.show'))
        ->assertOk()
        ->assertJsonCount(8, 'data')
        ->assertJsonPath('meta.passing_score', 70)
        ->assertJsonPath('meta.passed', false);

    expect($response->getContent())->not->toContain('is_correct')->not->toContain('explanation');
});

it('corrige le quiz et applique le score minimal paramétré [MOB-05]', function () {
    Sanctum::actingAs($this->candidat);

    $this->postJson(route('api.v1.quiz.attempts.store'), ['answers' => quizAnswers(wrong: 3)])
        ->assertCreated()
        ->assertJsonPath('data.score', 63)
        ->assertJsonPath('data.passed', false)
        ->assertJsonPath('data.correction.0.is_correct', false)
        ->assertJsonPath('data.correction.0.explanation', 'À l’horizontale, l’image remplit l’écran de télévision.');

    app(Settings::class)->set('candidature.quiz_min_score', 60);

    $this->postJson(route('api.v1.quiz.attempts.store'), ['answers' => quizAnswers(wrong: 3)])
        ->assertJsonPath('data.passed', true);

    expect(QuizAttempt::query()->count())->toBe(2)
        ->and(AuditLog::query()->where('action', 'formation.quiz.attempted')->count())->toBe(2);
});

it('exige une réponse à chaque question', function () {
    Sanctum::actingAs($this->candidat);

    $this->postJson(route('api.v1.quiz.attempts.store'), ['answers' => array_slice(quizAnswers(), 0, 5, true)])
        ->assertJsonValidationErrors(['answers' => 'Répondez à toutes les questions avant de valider.']);
});

it('limite le nombre de tentatives par heure', function () {
    Sanctum::actingAs($this->candidat);

    foreach (range(1, 10) as $attempt) {
        $this->postJson(route('api.v1.quiz.attempts.store'), ['answers' => quizAnswers(wrong: 8)])->assertCreated();
    }

    $this->postJson(route('api.v1.quiz.attempts.store'), ['answers' => quizAnswers()])->assertTooManyRequests();
});

it('bloque le dépôt du dossier tant que le quiz n’est pas réussi', function () {
    $candidature = Candidature::factory()->completed()->for($this->candidat)->create();
    Sanctum::actingAs($this->candidat);

    $this->postJson(route('api.v1.candidature.submit'))
        ->assertJsonValidationErrors(['formation' => 'Obtenez au moins 70 % au quiz pour déposer votre dossier.']);

    $this->postJson(route('api.v1.quiz.attempts.store'), ['answers' => quizAnswers()])->assertJsonPath('data.passed', true);
    $this->postJson(route('api.v1.candidature.submit'))->assertOk();

    expect($candidature->fresh()->status->value)->toBe('submitted');
});

it('fait passer le quiz depuis le site, question par question [MOB-05, WEB-02]', function () {
    Candidature::factory()->for($this->candidat)->create();
    $answers = quizAnswers(wrong: 1);

    $component = Livewire::actingAs($this->candidat)->test(Dossier::class)
        ->call('goTo', 'formation')
        ->assertSee('Bien filmer')
        ->call('startQuiz')
        ->assertSee('Question 1 sur 8');

    foreach ($answers as $question => $choice) {
        $component->call('answer', $question, $choice)->call('nextQuestion');
    }

    $component->call('submitQuiz')
        ->assertSet('formationView', 'resultat')
        ->assertSee('Quiz réussi, bravo !')
        ->assertSee('88');
});
