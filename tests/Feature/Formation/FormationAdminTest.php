<?php

use App\Domain\Access\Enums\Role;
use App\Domain\Formation\Models\FormationModule;
use App\Domain\Formation\Models\QuizQuestion;
use App\Domain\Territoire\Models\Commune;
use App\Livewire\Console\Formation\Index as FormationPage;
use App\Models\User;
use Database\Seeders\FormationSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(FormationSeeder::class);
    $this->admin = User::factory()->withTwoFactor()->withRole(Role::Admin)->create();
});

it('crée une fiche de formation publiée aussitôt dans l’application [MOB-05]', function () {
    Livewire::actingAs($this->admin)->test(FormationPage::class)
        ->call('openModule')
        ->set('title', 'Filmer la nuit')
        ->set('summary', 'Lumière, stabilité, son.')
        ->set('body', "## Conseils\n\n- Rapprochez-vous d’une source de lumière.")
        ->set('icon', 'camera')
        ->set('videoUrl', 'https://www.youtube.com/watch?v=abc')
        ->call('saveModule')
        ->assertHasNoErrors()
        ->assertSet('editingModule', false)
        ->assertSee('Filmer la nuit');

    $module = FormationModule::query()->where('title', 'Filmer la nuit')->sole();
    expect($module)->slug->toBe('filmer-la-nuit')->is_active->toBeTrue()->position->toBe(FormationModule::query()->max('position'));

    $this->getJson(route('api.v1.formation.index'))->assertOk()->assertJsonFragment(['title' => 'Filmer la nuit']);
    $this->assertDatabaseHas('audit_logs', ['action' => 'formation.module.created', 'actor_id' => $this->admin->id]);
});

it('modifie, réordonne, retire puis supprime une fiche [MOB-05]', function () {
    [$first, $second] = FormationModule::query()->orderBy('position')->take(2)->get()->all();

    $page = Livewire::actingAs($this->admin)->test(FormationPage::class)
        ->call('openModule', $second->uuid)
        ->assertSet('title', $second->title)
        ->set('title', 'Votre sécurité, toujours')
        ->call('saveModule')
        ->assertHasNoErrors()
        ->call('moveModule', $second->uuid, -1);

    expect(FormationModule::query()->orderBy('position')->first()->id)->toBe($second->id)
        ->and($second->fresh()->title)->toBe('Votre sécurité, toujours');

    $page->call('toggleModule', $first->uuid);
    expect($first->fresh()->is_active)->toBeFalse();
    $this->getJson(route('api.v1.formation.index'))->assertJsonMissing(['title' => $first->title]);

    $page->call('askDelete', 'module', $first->uuid)->call('confirmDelete');
    expect(FormationModule::query()->whereKey($first->id)->exists())->toBeFalse();

    $this->assertDatabaseHas('audit_logs', ['action' => 'formation.module.deleted', 'actor_id' => $this->admin->id]);
});

it('refuse une fiche sans titre ou avec un lien non sécurisé', function () {
    Livewire::actingAs($this->admin)->test(FormationPage::class)
        ->call('openModule')
        ->set('summary', 'Résumé')
        ->set('body', 'Contenu')
        ->set('videoUrl', 'http://exemple.com/video')
        ->call('saveModule')
        ->assertHasErrors(['title', 'videoUrl'])
        ->assertSet('editingModule', true);
});

it('ajoute une question au quiz, avec une seule bonne réponse [MOB-05]', function () {
    Livewire::actingAs($this->admin)->test(FormationPage::class)
        ->set('tab', 'quiz')
        ->call('openQuestion')
        ->set('prompt', 'Que faire si la scène est dangereuse ?')
        ->set('explanation', 'Votre sécurité passe avant l’image.')
        ->call('addChoice')
        ->set('choices', ['Filmer de plus près', 'Se mettre à l’abri', 'Appeler un ami'])
        ->set('correctChoice', 1)
        ->call('saveQuestion')
        ->assertHasNoErrors()
        ->assertSee('Que faire si la scène est dangereuse ?');

    $question = QuizQuestion::query()->where('prompt', 'Que faire si la scène est dangereuse ?')->with('choices')->sole();
    expect($question->choices->pluck('label')->all())->toBe(['Filmer de plus près', 'Se mettre à l’abri', 'Appeler un ami'])
        ->and($question->choices->firstWhere('is_correct', true)->label)->toBe('Se mettre à l’abri');

    $this->assertDatabaseHas('audit_logs', ['action' => 'formation.question.created', 'actor_id' => $this->admin->id]);
});

it('garde les identifiants des réponses quand une question est modifiée', function () {
    $question = QuizQuestion::query()->with('choices')->orderBy('position')->first();
    $uuids = $question->choices->pluck('uuid')->all();
    $labels = $question->choices->pluck('label')->all();
    $labels[0] = 'Réponse reformulée';

    Livewire::actingAs($this->admin)->test(FormationPage::class)
        ->call('openQuestion', $question->uuid)
        ->set('choices', $labels)
        ->set('correctChoice', 0)
        ->call('saveQuestion')
        ->assertHasNoErrors();

    $choices = $question->fresh()->choices;
    expect($choices->pluck('uuid')->all())->toBe($uuids)
        ->and($choices->first()->label)->toBe('Réponse reformulée')
        ->and($choices->first()->is_correct)->toBeTrue()
        ->and($choices->where('is_correct', true))->toHaveCount(1);
});

it('refuse une question avec des réponses vides ou en double', function () {
    Livewire::actingAs($this->admin)->test(FormationPage::class)
        ->call('openQuestion')
        ->set('prompt', 'Question ?')
        ->set('explanation', 'Explication.')
        ->set('choices', ['Oui', 'oui'])
        ->call('saveQuestion')
        ->assertHasErrors(['choices.0'])
        ->set('choices', ['Oui', ''])
        ->call('saveQuestion')
        ->assertHasErrors(['choices.1']);
});

it('retire, réordonne et supprime une question du quiz [MOB-05]', function () {
    [$first, $second] = QuizQuestion::query()->orderBy('position')->take(2)->get()->all();
    $total = QuizQuestion::query()->count();

    Livewire::actingAs($this->admin)->test(FormationPage::class)
        ->set('tab', 'quiz')
        ->call('moveQuestion', $first->uuid, 1)
        ->call('toggleQuestion', $second->uuid)
        ->call('askDelete', 'question', $first->uuid)
        ->call('confirmDelete');

    expect(QuizQuestion::query()->count())->toBe($total - 1)
        ->and($second->fresh()->is_active)->toBeFalse()
        ->and(QuizQuestion::query()->published()->count())->toBe($total - 2);
});

it('réserve la formation aux administrateurs [Annexe A]', function () {
    $this->actingAs(User::factory()->withTwoFactor()->coordonnateurOf(Commune::factory()->create())->create())
        ->get(route('console.formation'))
        ->assertForbidden();

    $this->actingAs($this->admin)->get(route('console.formation'))->assertOk()->assertSee('Bien filmer');
});
