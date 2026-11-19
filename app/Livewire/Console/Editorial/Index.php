<?php

namespace App\Livewire\Console\Editorial;

use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Contribution\States\Broadcast;
use App\Domain\Contribution\States\Selected;
use App\Domain\Contribution\States\Sensitive;
use App\Domain\Contribution\States\Validated;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Bureau de la rédaction (ADM-10, ADM-13, §8.2) : relais sensibles à arbitrer, validés à exploiter,
 * sélection en cours (dont les relais qui me sont affectés), relais diffusés.
 *
 * @property-read LengthAwarePaginator<int, RelaisItem> $items
 * @property-read array<string, int> $counts
 */
#[Title('Exploitation')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'onglet')]
    public string $tab = 'a-exploiter';

    public bool $mine = false;

    public function updated(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, RelaisItem>
     */
    #[Computed]
    public function items(): LengthAwarePaginator
    {
        $with = ['category:id,name', 'commune:id,name', 'relaisProfile:id,display_name,number', 'lockedBy:id,name', 'assignee:id,name', 'medias' => fn ($query) => $query->with('variants')];

        // Vitrine « Vu sur REFLET TV » : exactement ce que renvoie GET /diffusions à l'application (MOB-25, WEB-05).
        if ($this->tab === 'vitrine') {
            return RelaisItem::query()->whereNotNull('showcased_at')->with($with)->latest('showcased_at')->paginate(25);
        }

        $state = match ($this->tab) {
            'a-arbitrer' => Sensitive::getMorphClass(),
            'selection' => Selected::getMorphClass(),
            'diffuses' => Broadcast::getMorphClass(),
            default => Validated::getMorphClass(),
        };

        return RelaisItem::query()
            ->where('state', $state)
            ->when($this->tab === 'selection' && $this->mine, fn ($query) => $query->where('assigned_to', Auth::id()))
            ->with(['category:id,name', 'commune:id,name', 'relaisProfile:id,display_name,number', 'lockedBy:id,name', 'assignee:id,name', 'medias' => fn ($query) => $query->with('variants')])
            ->orderByRaw("case urgency when 'urgent' then 0 when 'important' then 1 else 2 end")
            ->latest('updated_at')
            ->paginate(25);
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function counts(): array
    {
        $byState = RelaisItem::query()->selectRaw('state, count(*) as total')->groupBy('state')->pluck('total', 'state');

        return [
            'a-arbitrer' => (int) ($byState['sensitive'] ?? 0),
            'a-exploiter' => (int) ($byState['validated'] ?? 0),
            'selection' => (int) ($byState['selected'] ?? 0),
            'diffuses' => (int) ($byState['broadcast'] ?? 0),
            'vitrine' => RelaisItem::query()->whereNotNull('showcased_at')->count(),
        ];
    }

    public function render(): View
    {
        return view('livewire.console.editorial.index');
    }
}
