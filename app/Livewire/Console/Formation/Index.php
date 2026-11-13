<?php

namespace App\Livewire\Console\Formation;

use App\Domain\Access\Enums\Permission;
use App\Domain\Formation\Actions\ManageFormationModule;
use App\Domain\Formation\Actions\ManageQuizQuestion;
use App\Domain\Formation\Models\FormationModule;
use App\Domain\Formation\Models\QuizQuestion;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Formation des Relais (MOB-05) : fiches et quiz proposés dans l'application et sur le site.
 * Création, modification, ordre, publication et suppression ; chaque changement est audité.
 *
 * @property-read Collection<int, FormationModule> $modules
 * @property-read Collection<int, QuizQuestion> $questions
 */
#[Title('Formation et quiz')]
class Index extends Component
{
    #[Url(except: 'fiches')]
    public string $tab = 'fiches';

    public bool $editingModule = false;

    #[Locked]
    public ?string $moduleUuid = null;

    public string $title = '';

    public string $summary = '';

    public string $body = '';

    public string $icon = 'book';

    public string $videoUrl = '';

    public bool $editingQuestion = false;

    #[Locked]
    public ?string $questionUuid = null;

    public string $prompt = '';

    public string $explanation = '';

    /** @var array<int, string> */
    public array $choices = ['', ''];

    /** Index de la bonne réponse dans $choices. */
    public int $correctChoice = 0;

    public bool $confirmingDelete = false;

    /** « module:uuid » ou « question:uuid ». */
    #[Locked]
    public ?string $pendingDelete = null;

    public function mount(): void
    {
        $this->authorizeAccess();

        if (! in_array($this->tab, ['fiches', 'quiz'], true)) {
            $this->tab = 'fiches';
        }
    }

    public function openModule(?string $uuid = null): void
    {
        $this->authorizeAccess();
        $this->resetValidation();
        $module = $uuid === null ? null : $this->findModule($uuid);

        $this->moduleUuid = $module?->uuid;
        $this->title = $module->title ?? '';
        $this->summary = $module->summary ?? '';
        $this->body = $module->body ?? '';
        $this->icon = $module->icon ?? 'book';
        $this->videoUrl = (string) $module?->video_url;
        $this->editingModule = true;
    }

    public function saveModule(ManageFormationModule $manage): void
    {
        $this->authorizeAccess();
        $this->validate([
            'title' => ['required', 'string', 'max:120'],
            'summary' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
            'icon' => ['required', Rule::in(array_keys(ManageFormationModule::ICONS))],
            'videoUrl' => ['nullable', 'url:https', 'max:255'],
        ], attributes: ['title' => __('titre'), 'summary' => __('résumé'), 'body' => __('contenu'), 'videoUrl' => __('lien de la vidéo')]);

        $data = [
            'title' => trim($this->title),
            'summary' => trim($this->summary),
            'body' => trim($this->body),
            'icon' => $this->icon,
            'video_url' => trim($this->videoUrl) ?: null,
        ];

        if ($this->moduleUuid === null) {
            $manage->create($data, $this->actor());
            $message = __('Fiche créée et publiée.');
        } else {
            $manage->update($this->findModule($this->moduleUuid), $data, $this->actor());
            $message = __('Fiche enregistrée.');
        }

        $this->editingModule = false;
        unset($this->modules);
        $this->dispatch('toast', message: $message);
    }

    public function toggleModule(string $uuid, ManageFormationModule $manage): void
    {
        $this->authorizeAccess();
        $module = $this->findModule($uuid);
        $manage->setActive($module, ! $module->is_active, $this->actor());

        unset($this->modules);
        $this->dispatch('toast', message: $module->is_active ? __('Fiche publiée.') : __('Fiche retirée : elle n’est plus proposée.'));
    }

    public function moveModule(string $uuid, int $direction, ManageFormationModule $manage): void
    {
        $this->authorizeAccess();
        $manage->move($this->findModule($uuid), $direction, $this->actor());
        unset($this->modules);
    }

    public function openQuestion(?string $uuid = null): void
    {
        $this->authorizeAccess();
        $this->resetValidation();
        $question = $uuid === null ? null : $this->findQuestion($uuid);

        $this->questionUuid = $question?->uuid;
        $this->prompt = $question->prompt ?? '';
        $this->explanation = $question->explanation ?? '';
        $this->choices = $question === null ? ['', ''] : $question->choices->pluck('label')->values()->all();
        $this->correctChoice = $question === null ? 0 : max(0, (int) $question->choices->search(fn ($choice): bool => $choice->is_correct));
        $this->editingQuestion = true;
    }

    public function addChoice(): void
    {
        if (count($this->choices) < ManageQuizQuestion::MAX_CHOICES) {
            $this->choices[] = '';
        }
    }

    public function removeChoice(int $index): void
    {
        if (count($this->choices) <= ManageQuizQuestion::MIN_CHOICES || ! array_key_exists($index, $this->choices)) {
            return;
        }

        unset($this->choices[$index]);
        $this->choices = array_values($this->choices);

        if ($this->correctChoice === $index) {
            $this->correctChoice = 0;
        } elseif ($this->correctChoice > $index) {
            $this->correctChoice--;
        }
    }

    public function saveQuestion(ManageQuizQuestion $manage): void
    {
        $this->authorizeAccess();
        $this->validate([
            'prompt' => ['required', 'string', 'max:255'],
            'explanation' => ['required', 'string', 'max:2000'],
            'choices' => ['required', 'array', 'min:'.ManageQuizQuestion::MIN_CHOICES, 'max:'.ManageQuizQuestion::MAX_CHOICES],
            'choices.*' => ['required', 'string', 'max:255', 'distinct:ignore_case'],
            'correctChoice' => ['required', 'integer', 'min:0', 'max:'.(count($this->choices) - 1)],
        ], attributes: ['prompt' => __('question'), 'explanation' => __('explication'), 'choices.*' => __('réponse'), 'correctChoice' => __('bonne réponse')]);

        $data = ['prompt' => trim($this->prompt), 'explanation' => trim($this->explanation)];
        $choices = array_map(fn (string $label, int $index): array => ['label' => trim($label), 'is_correct' => $index === $this->correctChoice], $this->choices, array_keys($this->choices));

        try {
            if ($this->questionUuid === null) {
                $manage->create($data, $choices, $this->actor());
                $message = __('Question ajoutée au quiz.');
            } else {
                $manage->update($this->findQuestion($this->questionUuid), $data, $choices, $this->actor());
                $message = __('Question enregistrée.');
            }
        } catch (InvalidArgumentException $exception) {
            $this->addError('choices', $exception->getMessage());

            return;
        }

        $this->editingQuestion = false;
        unset($this->questions);
        $this->dispatch('toast', message: $message);
    }

    public function toggleQuestion(string $uuid, ManageQuizQuestion $manage): void
    {
        $this->authorizeAccess();
        $question = $this->findQuestion($uuid);
        $manage->setActive($question, ! $question->is_active, $this->actor());

        unset($this->questions);
        $this->dispatch('toast', message: $question->is_active ? __('Question remise dans le quiz.') : __('Question retirée du quiz.'));
    }

    public function moveQuestion(string $uuid, int $direction, ManageQuizQuestion $manage): void
    {
        $this->authorizeAccess();
        $manage->move($this->findQuestion($uuid), $direction, $this->actor());
        unset($this->questions);
    }

    public function askDelete(string $type, string $uuid): void
    {
        abort_unless(in_array($type, ['module', 'question'], true) && Str::isUuid($uuid), 404);

        $this->pendingDelete = "{$type}:{$uuid}";
        $this->confirmingDelete = true;
    }

    public function confirmDelete(ManageFormationModule $modules, ManageQuizQuestion $questions): void
    {
        $this->authorizeAccess();
        [$type, $uuid] = explode(':', (string) $this->pendingDelete, 2) + [null, null];

        match ($type) {
            'module' => $modules->delete($this->findModule((string) $uuid), $this->actor()),
            'question' => $questions->delete($this->findQuestion((string) $uuid), $this->actor()),
            default => abort(404),
        };

        $this->confirmingDelete = false;
        $this->pendingDelete = null;
        unset($this->modules, $this->questions);
        $this->dispatch('toast', message: $type === 'module' ? __('Fiche supprimée.') : __('Question supprimée.'));
    }

    /**
     * @return Collection<int, FormationModule>
     */
    #[Computed]
    public function modules(): Collection
    {
        return FormationModule::query()->orderBy('position')->orderBy('id')->get();
    }

    /**
     * @return Collection<int, QuizQuestion>
     */
    #[Computed]
    public function questions(): Collection
    {
        return QuizQuestion::query()->with('choices')->orderBy('position')->orderBy('id')->get();
    }

    public function render(): View
    {
        return view('livewire.console.formation.index', [
            'icons' => ManageFormationModule::ICONS,
            'maxChoices' => ManageQuizQuestion::MAX_CHOICES,
            'minChoices' => ManageQuizQuestion::MIN_CHOICES,
        ]);
    }

    private function findModule(string $uuid): FormationModule
    {
        abort_unless(Str::isUuid($uuid), 404);

        return FormationModule::query()->where('uuid', $uuid)->firstOrFail();
    }

    private function findQuestion(string $uuid): QuizQuestion
    {
        abort_unless(Str::isUuid($uuid), 404);

        return QuizQuestion::query()->with('choices')->where('uuid', $uuid)->firstOrFail();
    }

    private function actor(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }

    private function authorizeAccess(): void
    {
        abort_unless(Auth::user()?->can(Permission::SettingsManage->value) === true, 403);
    }
}
