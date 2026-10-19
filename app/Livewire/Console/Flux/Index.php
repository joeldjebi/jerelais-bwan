<?php

namespace App\Livewire\Console\Flux;

use App\Domain\Access\Enums\Role;
use App\Domain\Access\Support\CommuneAccess;
use App\Domain\Contribution\Models\Category;
use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Contribution\Support\NetworkAlerts;
use App\Domain\Contribution\Support\RelaisSearch;
use App\Domain\Territoire\Models\Commune;
use App\Domain\Territoire\Models\Region;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Flux de tous les relais dès leur envoi, mis à jour sans recharger la page (ADM-01),
 * avec recherche et filtres (ADM-04) et alertes (ADM-03).
 *
 * @property-read LengthAwarePaginator<int, RelaisItem> $items
 * @property-read array{urgent: int, overdue: int, window: int, surges: list<array{commune: string, count: int}>} $alerts
 * @property-read Collection<int, Region> $regions
 * @property-read Collection<int, Commune> $communes
 * @property-read Collection<int, Category> $categories
 * @property-read Collection<int, User> $coordinators
 */
#[Title('Flux des relais')]
class Index extends Component
{
    use WithPagination;

    /** @var array{q: string, region: string, commune: string, category: string, relais: string, coordinator: string, from: string, to: string, media: string, state: string, urgency: string} */
    #[Url(except: '')]
    public array $filters = [
        'q' => '', 'region' => '', 'commune' => '', 'category' => '', 'relais' => '', 'coordinator' => '',
        'from' => '', 'to' => '', 'media' => '', 'state' => '', 'urgency' => '',
    ];

    public bool $advanced = false;

    public function updatedFilters(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset('filters');
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, RelaisItem>
     */
    #[Computed]
    public function items(): LengthAwarePaginator
    {
        return RelaisSearch::query($this->filters)
            ->with(['category:id,name', 'commune:id,name', 'relaisProfile:id,display_name,number', 'medias' => fn ($query) => $query->with('variants')])
            ->latest('received_at')
            ->paginate(30);
    }

    /**
     * @return array{urgent: int, overdue: int, window: int, surges: list<array{commune: string, count: int}>}
     */
    #[Computed]
    public function alerts(): array
    {
        return app(NetworkAlerts::class)->current();
    }

    /**
     * @return Collection<int, Region>
     */
    #[Computed(persist: true)]
    public function regions(): Collection
    {
        return Region::query()->orderBy('name')->get(['id', 'uuid', 'name']);
    }

    /**
     * @return Collection<int, Commune>
     */
    #[Computed]
    public function communes(): Collection
    {
        $ids = app(CommuneAccess::class)->communeIdsFor($this->user());

        return Commune::query()
            ->when($ids !== null, fn ($query) => $query->whereIn('id', $ids))
            ->when($this->filters['region'] !== '', fn ($query) => $query->whereHas('region', fn ($inner) => $inner->where('uuid', $this->filters['region'])))
            ->orderBy('name')->get(['id', 'uuid', 'name']);
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
     * @return Collection<int, User>
     */
    #[Computed(persist: true)]
    public function coordinators(): Collection
    {
        return User::query()->role(Role::Coordonnateur->value)->orderBy('name')->get(['id', 'uuid', 'name']);
    }

    public function render(): View
    {
        return view('livewire.console.flux.index');
    }

    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
