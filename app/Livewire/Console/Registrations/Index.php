<?php

namespace App\Livewire\Console\Registrations;

use App\Domain\Access\Actions\ReviewRegistration;
use App\Domain\Access\Enums\Permission;
use App\Domain\Access\Scopes\CommuneScope;
use App\Domain\Auth\Enums\UserStatus;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Inscriptions faites depuis l'application, en attente de validation (MOB-01).
 * Réservé à l'administration : ces comptes n'ont pas encore de commune, donc aucun périmètre de coordonnateur.
 *
 * @property-read LengthAwarePaginator<int, User> $registrations
 * @property-read User|null $selected
 */
#[Title('Inscriptions')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Locked]
    public ?string $selectedUuid = null;

    #[Locked]
    public string $decision = '';

    public bool $confirming = false;

    public function mount(): void
    {
        $this->authorizeAccess();
    }

    public function updated(string $property): void
    {
        if ($property === 'search') {
            $this->resetPage();
        }
    }

    public function ask(string $uuid, string $decision): void
    {
        $this->selectedUuid = $uuid;
        $this->decision = $decision === 'approve' ? 'approve' : 'reject';
        $this->confirming = true;
    }

    public function confirm(ReviewRegistration $review): void
    {
        $this->authorizeAccess();

        $user = User::query()->where('uuid', $this->selectedUuid)->where('status', UserStatus::Pending->value)->first();
        $this->confirming = false;

        if ($user === null) {
            $this->dispatch('toast', message: __('Cette inscription a déjà été traitée.'), tone: 'error');

            return;
        }

        /** @var User $actor */
        $actor = Auth::user();
        $name = $user->name;

        if ($this->decision === 'approve') {
            $review->approve($user, $actor);
            $this->dispatch('toast', message: __(':name peut maintenant se connecter à l’application.', ['name' => $name]));
        } else {
            $review->reject($user, $actor);
            $this->dispatch('toast', message: __('Inscription de :name refusée : ses données ont été effacées.', ['name' => $name]));
        }

        $this->reset('selectedUuid', 'decision');
    }

    /**
     * @return LengthAwarePaginator<int, User>
     */
    #[Computed]
    public function registrations(): LengthAwarePaginator
    {
        $term = trim($this->search);

        return User::query()
            ->where('status', UserStatus::Pending->value)
            ->with(['candidature' => fn ($query) => $query->withoutGlobalScope(CommuneScope::class)])
            ->when($term !== '', fn ($query) => $query->whereLike('name', '%'.$term.'%'))
            ->orderBy('created_at')
            ->paginate(20);
    }

    #[Computed]
    public function selected(): ?User
    {
        return $this->selectedUuid ? User::query()->where('uuid', $this->selectedUuid)->first() : null;
    }

    public function render(): View
    {
        return view('livewire.console.registrations.index');
    }

    private function authorizeAccess(): void
    {
        abort_unless(Auth::user()?->hasPermissionTo(Permission::UsersManage->value) === true, 403);
    }
}
