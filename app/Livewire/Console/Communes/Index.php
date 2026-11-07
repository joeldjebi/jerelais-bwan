<?php

namespace App\Livewire\Console\Communes;

use App\Domain\Access\Enums\Permission;
use App\Domain\Territoire\Actions\ManageCommune;
use App\Domain\Territoire\Actions\SetCommuneAvailability;
use App\Domain\Territoire\Models\Commune;
use App\Domain\Territoire\Models\Region;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Référentiel des communes (ADM-20) et ouverture progressive aux Relais (§14 : pilote puis national).
 */
#[Title('Communes')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $region = '';

    #[Url(except: '')]
    public string $status = '';

    #[Locked]
    public ?string $pendingCommune = null;

    public bool $confirmingToggle = false;

    public bool $creating = false;

    public string $newName = '';

    public string $newRegion = '';

    public string $newCode = '';

    #[Locked]
    public ?string $editing = null;

    public string $editName = '';

    public string $editRegion = '';

    public function mount(): void
    {
        $this->authorizeAccess();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'region', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function askToggle(string $uuid): void
    {
        $this->pendingCommune = $uuid;
        $this->confirmingToggle = true;
    }

    public function confirmToggle(SetCommuneAvailability $setAvailability): void
    {
        $this->authorizeAccess();

        $commune = Commune::query()->where('uuid', $this->pendingCommune)->firstOrFail();
        $open = ! $commune->is_active;

        /** @var User $actor */
        $actor = Auth::user();
        $setAvailability->handle($commune, $open, $actor);

        $this->confirmingToggle = false;
        $this->pendingCommune = null;
        unset($this->counts);

        $this->dispatch('toast', message: $open
            ? __(':name est ouverte aux Relais.', ['name' => $commune->name])
            : __(':name est fermée aux nouvelles inscriptions.', ['name' => $commune->name]));
    }

    public function openCreate(): void
    {
        $this->reset('newName', 'newRegion', 'newCode');
        $this->resetValidation();
        $this->creating = true;
    }

    public function create(ManageCommune $manage): void
    {
        $this->authorizeAccess();

        $this->validate([
            'newName' => ['required', 'string', 'max:120'],
            'newRegion' => ['nullable', 'uuid', Rule::exists('regions', 'uuid')],
            'newCode' => ['nullable', 'string', 'max:20', Rule::unique('communes', 'code')],
        ], attributes: ['newName' => __('nom'), 'newRegion' => __('région'), 'newCode' => __('code')]);

        $name = trim($this->newName);

        if (Commune::query()->whereRaw('lower(name) = ?', [Str::lower($name)])->exists()) {
            $this->addError('newName', __('Cette commune existe déjà.'));

            return;
        }

        $commune = $manage->create($name, $this->regionFor($this->newRegion), trim($this->newCode) ?: null, $this->actor());

        $this->creating = false;
        unset($this->counts);
        $this->dispatch('toast', message: __(':name a été créée. Elle reste fermée aux Relais tant que vous ne l’ouvrez pas.', ['name' => $commune->name]));
    }

    public function startEdit(string $uuid): void
    {
        $this->authorizeAccess();
        $commune = Commune::query()->with('region')->where('uuid', $uuid)->firstOrFail();

        $this->resetValidation();
        $this->editing = $commune->uuid;
        $this->editName = $commune->name;
        $this->editRegion = (string) $commune->region?->uuid;
    }

    public function cancelEdit(): void
    {
        $this->reset('editing', 'editName', 'editRegion');
    }

    public function saveEdit(ManageCommune $manage): void
    {
        $this->authorizeAccess();
        $commune = Commune::query()->where('uuid', $this->editing)->firstOrFail();

        $this->validate([
            'editName' => ['required', 'string', 'max:120'],
            'editRegion' => ['nullable', 'uuid', Rule::exists('regions', 'uuid')],
        ], attributes: ['editName' => __('nom'), 'editRegion' => __('région')]);

        $name = trim($this->editName);

        if (Commune::query()->whereKeyNot($commune->id)->whereRaw('lower(name) = ?', [Str::lower($name)])->exists()) {
            $this->addError('editName', __('Cette commune existe déjà.'));

            return;
        }

        $manage->update($commune, $name, $this->regionFor($this->editRegion), $this->actor());

        $this->cancelEdit();
        $this->dispatch('toast', message: __('Commune mise à jour.'));
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'region', 'status');
        $this->resetPage();
    }

    #[Computed]
    public function pending(): ?Commune
    {
        return $this->pendingCommune ? Commune::query()->where('uuid', $this->pendingCommune)->first() : null;
    }

    /**
     * @return LengthAwarePaginator<int, Commune>
     */
    #[Computed]
    public function communes(): LengthAwarePaginator
    {
        return Commune::query()
            ->select(['communes.id', 'communes.uuid', 'communes.region_id', 'communes.name', 'communes.code', 'communes.is_virtual', 'communes.is_active'])
            ->selectRaw('communes.geom is not null as has_contour')
            ->with('region')
            ->withCount(['coordinators'])
            ->when($this->search !== '', fn ($query) => $query->whereLike('name', '%'.$this->search.'%'))
            ->when($this->region !== '', fn ($query) => $query->whereHas('region', fn ($region) => $region->where('uuid', $this->region)))
            ->when($this->status === 'open', fn ($query) => $query->where('is_active', true))
            ->when($this->status === 'closed', fn ($query) => $query->where('is_active', false))
            ->orderBy('name')
            ->paginate(12);
    }

    /**
     * @return Collection<int, Region>
     */
    #[Computed]
    public function regions(): Collection
    {
        return Region::query()->orderBy('name')->get();
    }

    /**
     * @return array{total: int, open: int, contours: int}
     */
    #[Computed]
    public function counts(): array
    {
        return [
            'total' => Commune::query()->count(),
            'open' => Commune::query()->active()->count(),
            'contours' => Commune::query()->whereNotNull('geom')->count(),
        ];
    }

    public function render(): View
    {
        return view('livewire.console.communes.index');
    }

    private function regionFor(string $uuid): ?Region
    {
        return Str::isUuid($uuid) ? Region::query()->where('uuid', $uuid)->first() : null;
    }

    private function actor(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }

    private function authorizeAccess(): void
    {
        abort_unless(Auth::user()?->can(Permission::TerritoireManage->value) === true, 403);
    }
}
