<?php

namespace App\Livewire\Console\Validation;

use App\Domain\Contribution\Actions\DecideRelaisItem;
use App\Domain\Contribution\Actions\LockRelaisItem;
use App\Domain\Contribution\Enums\RelaisDecision;
use App\Domain\Contribution\Exceptions\RelaisLocked;
use App\Domain\Contribution\Models\Category;
use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Contribution\States\InReview;
use App\Domain\Contribution\Support\RelaisVerification;
use App\Domain\Media\Actions\FindMediaDuplicates;
use App\Domain\Media\Models\Media;
use App\Domain\Validation\Enums\RejectionTarget;
use App\Domain\Validation\Models\RejectionReason;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Vérification et décision d'un relais (COO-11 à COO-18). L'ouverture réserve le relais 10 minutes.
 *
 * @property-read Collection<int, RejectionReason> $reasons
 * @property-read Collection<int, Category> $categories
 * @property-read list<array{media: Media, other: Media, identical: bool, distance: int}> $duplicates
 * @property-read array{sent: int, validated: int, rejected: int, reliability: int|null} $stats
 */
class Show extends Component
{
    #[Locked]
    public RelaisItem $relais;

    #[Locked]
    public ?string $lockMessage = null;

    public string $sheet = '';

    public string $comment = '';

    public string $reason = '';

    public string $blur = '';

    /** @var array{title: string, description: string, category: string, declared_place: string} */
    public array $corrections = ['title' => '', 'description' => '', 'category' => '', 'declared_place' => ''];

    public string $note = '';

    public function mount(RelaisItem $relais, LockRelaisItem $lock): void
    {
        Gate::authorize('view', $relais);
        $this->relais = $relais;

        if ($relais->state->isAwaitingValidation() && Gate::allows('validate', $relais)) {
            try {
                $lock->handle($relais, $this->user());
            } catch (RelaisLocked $exception) {
                $this->lockMessage = $exception->getMessage();
            }
        }

        $this->note = (string) $relais->internal_note;
        $this->load();
    }

    /**
     * Le modèle est réhydraté sans ses relations à chaque requête Livewire : on les recharge.
     */
    public function hydrate(): void
    {
        $this->load();
    }

    public function open(string $sheet): void
    {
        $this->resetErrorBag();
        $this->reset('comment', 'reason', 'blur');
        $this->corrections = [
            'title' => (string) $this->relais->title,
            'description' => (string) $this->relais->description,
            'category' => (string) $this->relais->category?->uuid,
            'declared_place' => (string) $this->relais->declared_place,
        ];
        $this->sheet = $sheet;
    }

    public function decide(string $decision, DecideRelaisItem $decide): void
    {
        Gate::authorize('validate', $this->relais);
        $choice = RelaisDecision::from($decision);

        $this->validate([
            'comment' => ['nullable', 'string', 'max:1000'],
            'corrections.title' => ['nullable', 'string', 'max:120'],
            'corrections.description' => ['nullable', 'string', 'max:2000'],
            'corrections.declared_place' => ['nullable', 'string', 'max:200'],
        ]);

        try {
            $decide->handle(
                $this->relais,
                $choice,
                $this->user(),
                comment: $this->comment,
                reason: $choice === RelaisDecision::Reject ? $this->reasons->firstWhere('uuid', $this->reason) : null,
                blurRequired: $this->blur === '' ? null : $this->blur === '1',
                corrections: $choice === RelaisDecision::Validate ? $this->corrections : [],
            );
        } catch (InvalidArgumentException|RelaisLocked $exception) {
            $this->addError('decision', $exception->getMessage());

            return;
        }

        $this->sheet = '';
        $this->load();
        $this->dispatch('toast', message: __('Décision enregistrée : :state. Le Relais est prévenu.', ['state' => mb_strtolower($this->relais->state->label())]));
    }

    /**
     * Note interne (COO-18) : visible de l'équipe, jamais du Relais.
     */
    public function saveNote(): void
    {
        Gate::authorize('validate', $this->relais);
        $this->validate(['note' => ['nullable', 'string', 'max:2000']]);

        $this->relais->forceFill(['internal_note' => trim($this->note) ?: null])->save();
        $this->dispatch('toast', message: __('Note interne enregistrée.'));
    }

    public function release(LockRelaisItem $lock): mixed
    {
        $lock->release($this->relais, $this->user());

        return $this->redirectRoute('console.validation', navigate: true);
    }

    #[Computed]
    public function canDecide(): bool
    {
        return $this->lockMessage === null
            && $this->relais->state instanceof InReview
            && Gate::allows('validate', $this->relais);
    }

    /**
     * @return Collection<int, RejectionReason>
     */
    #[Computed(persist: true)]
    public function reasons(): Collection
    {
        return RejectionReason::query()->activeFor(RejectionTarget::Relais)->get();
    }

    /**
     * @return Collection<int, Category>
     */
    #[Computed(persist: true)]
    public function categories(): Collection
    {
        return Category::query()->active()->get(['id', 'uuid', 'name']);
    }

    /**
     * @return list<array{media: Media, other: Media, identical: bool, distance: int}>
     */
    #[Computed]
    public function duplicates(): array
    {
        return app(FindMediaDuplicates::class)->handle($this->relais);
    }

    /**
     * @return array{sent: int, validated: int, rejected: int, reliability: int|null}
     */
    #[Computed]
    public function stats(): array
    {
        return app(RelaisVerification::class)->relaisStats($this->relais);
    }

    public function render(): View
    {
        return view('livewire.console.validation.show')->title($this->relais->title ?: __('Relais'));
    }

    private function load(): void
    {
        $this->relais->refresh()->load([
            'category', 'commune', 'declaredCommune', 'relaisProfile', 'lockedBy', 'rejectionReason',
            'medias.variants', 'statusHistory.actor',
        ]);
    }

    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
