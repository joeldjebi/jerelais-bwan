<?php

namespace App\Livewire\Console\Team;

use App\Domain\Access\Actions\ChangeUserStatus;
use App\Domain\Access\Actions\CreateStaffMember;
use App\Domain\Access\Enums\CommuneAssignment;
use App\Domain\Access\Enums\Permission;
use App\Domain\Access\Enums\Role;
use App\Domain\Auth\Enums\UserStatus;
use App\Domain\Territoire\Models\Commune;
use App\Domain\Territoire\Models\Region;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Gestion des membres de l'équipe : rédaction, coordination, administration (ADM-21, ADM-24).
 */
#[Title('Équipe')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $role = '';

    public bool $creating = false;

    public string $name = '';

    public string $email = '';

    public string $newRole = 'coordonnateur';

    /** @var list<string> */
    public array $communeUuids = [];

    /** @var list<string> */
    public array $regionUuids = [];

    public string $communeSearch = '';

    public string $assignment = 'titulaire';

    #[Locked]
    public ?string $pendingMember = null;

    public bool $confirmingStatus = false;

    public function mount(): void
    {
        $this->authorizeAccess();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'role'], true)) {
            $this->resetPage();
        }
    }

    public function openCreate(): void
    {
        $this->reset('name', 'email', 'newRole', 'communeUuids', 'regionUuids', 'communeSearch', 'assignment');
        $this->resetValidation();
        $this->creating = true;
    }

    public function create(CreateStaffMember $createStaffMember): void
    {
        $this->authorizeAccess();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')],
            'newRole' => ['required', Rule::in(array_map(fn (Role $role) => $role->value, self::staffRoles()))],
            'communeUuids' => [Rule::requiredIf($this->newRole === Role::Coordonnateur->value), 'array'],
            'communeUuids.*' => ['string', Rule::exists('communes', 'uuid')],
            'regionUuids' => [Rule::requiredIf($this->newRole === Role::Superviseur->value), 'array'],
            'regionUuids.*' => ['string', Rule::exists('regions', 'uuid')],
            'assignment' => ['required', Rule::enum(CommuneAssignment::class)],
        ], attributes: [
            'name' => __('nom'),
            'newRole' => __('rôle'),
            'communeUuids' => __('communes'),
            'regionUuids' => __('régions'),
        ]);

        /** @var User $actor */
        $actor = Auth::user();

        $member = $createStaffMember->handle(
            $validated['name'],
            $validated['email'],
            Role::from($validated['newRole']),
            $actor,
            array_values(Commune::query()->whereIn('uuid', $this->communeUuids)->pluck('id')->map(fn ($id): int => (int) $id)->all()),
            array_values(Region::query()->whereIn('uuid', $this->regionUuids)->pluck('id')->map(fn ($id): int => (int) $id)->all()),
            CommuneAssignment::from($validated['assignment']),
        );

        $this->creating = false;
        $this->dispatch('toast', message: __(':name a été ajouté·e. Un lien d’activation a été envoyé par e-mail.', ['name' => $member->name]));
    }

    public function askStatusChange(string $uuid): void
    {
        $this->pendingMember = $uuid;
        $this->confirmingStatus = true;
    }

    public function confirmStatusChange(ChangeUserStatus $changeStatus): void
    {
        $this->authorizeAccess();

        $member = User::query()->where('uuid', $this->pendingMember)->firstOrFail();

        /** @var User $actor */
        $actor = Auth::user();

        if ($member->is($actor)) {
            $this->confirmingStatus = false;
            $this->dispatch('toast', message: __('Vous ne pouvez pas suspendre votre propre compte.'), tone: 'error');

            return;
        }

        $suspend = $member->status->canSignIn();
        $changeStatus->handle($member, $suspend ? UserStatus::Suspended : UserStatus::Active, $actor);

        $this->confirmingStatus = false;
        $this->pendingMember = null;

        $this->dispatch('toast', message: $suspend
            ? __(':name est suspendu·e : toutes ses sessions ont été fermées.', ['name' => $member->name])
            : __(':name est réactivé·e.', ['name' => $member->name]));
    }

    #[Computed]
    public function pending(): ?User
    {
        return $this->pendingMember ? User::query()->where('uuid', $this->pendingMember)->first() : null;
    }

    /**
     * @return LengthAwarePaginator<int, User>
     */
    #[Computed]
    public function members(): LengthAwarePaginator
    {
        return User::role($this->role !== '' ? [$this->role] : array_map(fn (Role $role) => $role->value, self::staffRoles()))
            ->with(['roles', 'communes', 'regions'])
            ->when($this->search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->whereLike('name', '%'.$this->search.'%')
                ->orWhereLike('email', '%'.$this->search.'%')))
            ->orderByRaw('last_login_at desc nulls last')
            ->orderBy('name')
            ->paginate(15);
    }

    /**
     * @return Collection<int, Commune>
     */
    #[Computed]
    public function communeOptions(): Collection
    {
        return Commune::query()
            ->select(['id', 'uuid', 'name', 'region_id'])
            ->when($this->communeSearch !== '', fn ($query) => $query->whereLike('name', '%'.$this->communeSearch.'%'))
            ->orderBy('name')
            ->limit(40)
            ->get();
    }

    /**
     * @return Collection<int, Region>
     */
    #[Computed]
    public function regionOptions(): Collection
    {
        return Region::query()->orderBy('name')->get();
    }

    /**
     * @return list<Role>
     */
    public static function staffRoles(): array
    {
        return [Role::Admin, Role::RedacteurEnChef, Role::Journaliste, Role::LectureSeule, Role::Superviseur, Role::Coordonnateur];
    }

    public function render(): View
    {
        return view('livewire.console.team.index');
    }

    private function authorizeAccess(): void
    {
        abort_unless(Auth::user()?->can(Permission::UsersManage->value) === true, 403);
    }
}
