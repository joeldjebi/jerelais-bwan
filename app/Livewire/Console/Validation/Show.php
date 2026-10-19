<?php

namespace App\Livewire\Console\Validation;

use App\Domain\Access\Enums\Permission;
use App\Domain\Access\Enums\Role;
use App\Domain\Audit\AuditLogger;
use App\Domain\Contribution\Actions\DecideRelaisItem;
use App\Domain\Contribution\Actions\LockRelaisItem;
use App\Domain\Contribution\Enums\RelaisDecision;
use App\Domain\Contribution\Exceptions\RelaisLocked;
use App\Domain\Contribution\Models\Category;
use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Contribution\States\InReview;
use App\Domain\Contribution\Support\RelaisVerification;
use App\Domain\Editorial\Actions\ArbitrateRelaisItem;
use App\Domain\Editorial\Actions\SelectRelaisItem;
use App\Domain\Editorial\Exceptions\EditorialRefused;
use App\Domain\Editorial\Models\RelaisCollection;
use App\Domain\Media\Actions\FindMediaDuplicates;
use App\Domain\Media\Models\Media;
use App\Domain\Messagerie\Actions\SendMessage;
use App\Domain\Validation\Enums\RejectionTarget;
use App\Domain\Validation\Models\RejectionReason;
use App\Models\User;
use Carbon\CarbonImmutable;
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
 * @property-read bool $canEdit
 * @property-read bool $canExport
 * @property-read Collection<int, User> $journalists
 * @property-read Collection<int, RelaisCollection> $collectionsList
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

    /** Feuille éditoriale ouverte : arbitrate-validate, arbitrate-reject, select, broadcast, collection, derogation. */
    public string $editorial = '';

    public string $editorReason = '';

    public string $journalist = '';

    public string $programme = '';

    public string $broadcastAt = '';

    public string $broadcastProgramme = '';

    public string $broadcastUrl = '';

    public string $collection = '';

    public string $newCollection = '';

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

    public function openEditorial(string $sheet): void
    {
        $this->resetErrorBag();
        $this->reset('editorReason', 'collection', 'newCollection');
        $this->journalist = (string) $this->relais->assignee?->uuid;
        $this->programme = (string) $this->relais->programme;
        $this->broadcastAt = now()->timezone('Africa/Abidjan')->format('Y-m-d\TH:i');
        $this->broadcastProgramme = (string) $this->relais->programme;
        $this->editorial = $sheet;
    }

    /**
     * Arbitrage de la rédaction (§8.2) : décision sur un relais sensible ou retour sur une décision.
     */
    public function arbitrate(bool $validate, ArbitrateRelaisItem $arbitrate): void
    {
        $this->authorizeEditorial();

        try {
            $arbitrate->handle($this->relais, $validate, $this->user(), $this->editorReason, $this->blur === '' ? null : $this->blur === '1');
        } catch (EditorialRefused $exception) {
            $this->addError('editorial', $exception->getMessage());

            return;
        }

        $this->closeEditorial(__('Arbitrage enregistré.'));
    }

    public function selectRelais(SelectRelaisItem $select): void
    {
        $this->authorizeEditorial();
        $this->validate(['programme' => ['nullable', 'string', 'max:120']]);
        $journalist = $this->journalist === '' ? null : $this->journalists->firstWhere('uuid', $this->journalist);

        try {
            $select->select($this->relais, $this->user(), $journalist, $this->programme);
        } catch (EditorialRefused $exception) {
            $this->addError('editorial', $exception->getMessage());

            return;
        }

        $this->closeEditorial(__('Relais sélectionné.'));
    }

    public function unselect(SelectRelaisItem $select): void
    {
        $this->authorizeEditorial();

        try {
            $select->unselect($this->relais, $this->user());
        } catch (EditorialRefused $exception) {
            $this->dispatch('toast', message: $exception->getMessage(), tone: 'error');

            return;
        }

        $this->closeEditorial(__('Relais retiré de la sélection.'));
    }

    /**
     * Marquage « diffusé » (ADM-13) : date, émission, lien éventuel ; le Relais est prévenu.
     */
    public function markBroadcast(SelectRelaisItem $select): void
    {
        $this->authorizeEditorial();
        $this->validate([
            'broadcastAt' => ['required', 'date'],
            'broadcastProgramme' => ['required', 'string', 'max:120'],
            'broadcastUrl' => ['nullable', 'url:https,http', 'max:500'],
        ], attributes: ['broadcastAt' => __('date de diffusion'), 'broadcastProgramme' => __('émission'), 'broadcastUrl' => __('lien')]);

        try {
            $select->markBroadcast($this->relais, $this->user(), CarbonImmutable::parse($this->broadcastAt, 'Africa/Abidjan'), $this->broadcastProgramme, $this->broadcastUrl);
        } catch (EditorialRefused $exception) {
            $this->addError('editorial', $exception->getMessage());

            return;
        }

        $this->closeEditorial(__('Diffusion enregistrée. Le Relais est prévenu.'));
    }

    public function addToCollection(AuditLogger $audit): void
    {
        $this->authorizeEditorial();

        if ($this->newCollection !== '') {
            $this->validate(['newCollection' => ['string', 'max:120']]);
            $target = new RelaisCollection(['name' => trim($this->newCollection)]);
            $target->creator()->associate($this->user());
            $target->save();
            $audit->record('editorial.collection_created', $target, ['name' => $target->name]);
        } else {
            $target = RelaisCollection::query()->where('uuid', $this->collection)->first();
        }

        if ($target === null) {
            $this->addError('editorial', __('Choisissez une collection ou créez-en une.'));

            return;
        }

        $target->relais()->syncWithoutDetaching([$this->relais->id => ['added_by' => $this->user()->id, 'created_at' => now()]]);
        $target->touch();
        $audit->record('editorial.collection_added', $this->relais, ['collection' => $target->name], communeId: $this->relais->commune_id);

        $this->closeEditorial(__('Ajouté à « :name ».', ['name' => $target->name]));
    }

    /**
     * Ouvre (ou reprend) la conversation rattachée à ce relais.
     */
    public function writeToRelais(SendMessage $send): mixed
    {
        Gate::authorize('validate', $this->relais);
        abort_if($this->relais->relaisProfile === null, 404);

        $conversation = $send->conversationFor($this->relais->relaisProfile, $this->relais);

        return $this->redirectRoute('console.messagerie', ['c' => $conversation->uuid], navigate: true);
    }

    #[Computed]
    public function canEdit(): bool
    {
        return $this->user()->hasPermissionTo(Permission::RelaisArbitrate->value);
    }

    #[Computed]
    public function canExport(): bool
    {
        return $this->user()->hasPermissionTo(Permission::MediasDownloadOriginal->value);
    }

    /**
     * @return Collection<int, User>
     */
    #[Computed(persist: true)]
    public function journalists(): Collection
    {
        return User::query()->role([Role::Journaliste->value, Role::RedacteurEnChef->value])->orderBy('name')->get(['id', 'uuid', 'name']);
    }

    /**
     * @return Collection<int, RelaisCollection>
     */
    #[Computed]
    public function collectionsList(): Collection
    {
        return RelaisCollection::query()->latest('updated_at')->get(['id', 'uuid', 'name']);
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

    private function authorizeEditorial(): void
    {
        abort_unless($this->canEdit, 403);
        $this->resetErrorBag('editorial');
    }

    private function closeEditorial(string $message): void
    {
        $this->editorial = '';
        $this->load();
        $this->dispatch('toast', message: $message);
    }

    private function load(): void
    {
        $this->relais->refresh()->load([
            'category', 'commune', 'declaredCommune', 'relaisProfile', 'lockedBy', 'rejectionReason', 'assignee', 'collections',
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
