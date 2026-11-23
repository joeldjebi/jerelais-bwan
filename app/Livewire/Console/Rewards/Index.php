<?php

namespace App\Livewire\Console\Rewards;

use App\Domain\Access\Enums\Permission;
use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Contribution\States\Archived;
use App\Domain\Contribution\States\Broadcast;
use App\Domain\Contribution\States\Selected;
use App\Domain\Contribution\States\Validated;
use App\Domain\Gains\Models\GainDecision;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Rémunérations des relais (ADM-27) : relais validés en attente de décision, puis relais décidés (montant accordé,
 * pas de rémunération, correction, annulation). La décision se prend sur la fiche du relais. Périmètre communal
 * appliqué : un superviseur ne voit que sa zone.
 *
 * @property-read LengthAwarePaginator<int, RelaisItem> $items
 * @property-read array{pending: int, decided: int} $counts
 */
#[Title('Rémunérations')]
class Index extends Component
{
    use WithPagination;

    #[Url(except: 'a-decider')]
    public string $tab = 'a-decider';

    public function mount(): void
    {
        abort_unless(Auth::user()?->can(Permission::GainsDecide->value) === true, 403);
    }

    public function updatedTab(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, RelaisItem>
     */
    #[Computed]
    public function items(): LengthAwarePaginator
    {
        return $this->base($this->tab === 'decides')
            ->with(['category:id,name', 'commune:id,name', 'relaisProfile:id,display_name,number', 'lockedBy:id,name', 'medias' => fn ($query) => $query->with('variants')])
            ->addSelect(['last_decision' => GainDecision::query()->select('decision')->whereColumn('relais_item_id', 'relais_items.id')->latest('id')->limit(1)])
            ->addSelect(['last_amount' => GainDecision::query()->selectRaw('coalesce(amount, previous_amount)')->whereColumn('relais_item_id', 'relais_items.id')->latest('id')->limit(1)])
            ->latest('updated_at')
            ->paginate(25);
    }

    /**
     * @return array{pending: int, decided: int}
     */
    #[Computed]
    public function counts(): array
    {
        return ['pending' => $this->base(false)->count(), 'decided' => $this->base(true)->count()];
    }

    public function render(): View
    {
        return view('livewire.console.rewards.index');
    }

    /**
     * @return Builder<RelaisItem>
     */
    private function base(bool $decided): Builder
    {
        return RelaisItem::query()
            ->whereIn('state', [Validated::getMorphClass(), Selected::getMorphClass(), Broadcast::getMorphClass(), Archived::getMorphClass()])
            ->when($decided,
                fn (Builder $query) => $query->whereExists(fn ($sub) => $sub->selectRaw('1')->from('gain_decisions')->whereColumn('gain_decisions.relais_item_id', 'relais_items.id')),
                fn (Builder $query) => $query->whereNotExists(fn ($sub) => $sub->selectRaw('1')->from('gain_decisions')->whereColumn('gain_decisions.relais_item_id', 'relais_items.id'))
                    ->whereNotExists(fn ($sub) => $sub->selectRaw('1')->from('commissions')->whereColumn('commissions.relais_item_id', 'relais_items.id')->whereIn('commissions.status', ['due', 'paid'])));
    }
}
