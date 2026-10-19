<?php

namespace App\Livewire\Console\Validation;

use App\Domain\Contribution\Enums\Urgency;
use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Contribution\States\Broadcast;
use App\Domain\Contribution\States\ComplementRequested;
use App\Domain\Contribution\States\InReview;
use App\Domain\Contribution\States\Received;
use App\Domain\Contribution\States\Rejected;
use App\Domain\Contribution\States\Selected;
use App\Domain\Contribution\States\Sensitive;
use App\Domain\Contribution\States\Validated;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * File de validation en temps réel (COO-10) : urgents d'abord, puis par ancienneté.
 * Le CommuneScope limite un coordonnateur à ses communes ; la page se rafraîchit toutes les 5 secondes.
 *
 * @property-read LengthAwarePaginator<int, RelaisItem> $items
 * @property-read array<string, int> $counts
 */
#[Title('Validation des relais')]
class Index extends Component
{
    use WithPagination;

    /** @var array<string, list<string>> */
    public const FILTERS = [
        'a-valider' => [Received::class, InReview::class],
        'complement' => [ComplementRequested::class],
        'sensibles' => [Sensitive::class],
        'valides' => [Validated::class, Selected::class, Broadcast::class],
        'rejetes' => [Rejected::class],
    ];

    #[Url(as: 'file')]
    public string $filter = 'a-valider';

    /** Nombre de relais urgents connu au dernier rafraîchissement (signal à chaque nouvel urgent). */
    public int $knownUrgents = 0;

    public function mount(): void
    {
        $this->knownUrgents = $this->counts['urgents'];
    }

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, RelaisItem>
     */
    #[Computed]
    public function items(): LengthAwarePaginator
    {
        $pending = $this->filter === 'a-valider';

        return RelaisItem::query()
            ->with(['category:id,name', 'commune:id,name', 'relaisProfile:id,display_name,number', 'lockedBy:id,name', 'medias' => fn ($query) => $query->with('variants')])
            ->whereIn('state', $this->stateNames(self::FILTERS[$this->filter] ?? self::FILTERS['a-valider']))
            ->when($pending, fn ($query) => $query
                ->orderByRaw("case urgency when 'urgent' then 0 when 'important' then 1 else 2 end")
                ->orderBy('received_at'))
            ->when(! $pending, fn ($query) => $query->latest('updated_at'))
            ->paginate(25);
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function counts(): array
    {
        $byState = RelaisItem::query()->selectRaw('state, count(*) as total')->groupBy('state')->pluck('total', 'state');
        $counts = [];

        foreach (self::FILTERS as $key => $states) {
            $counts[$key] = (int) collect($this->stateNames($states))->sum(fn (string $state): int => (int) ($byState[$state] ?? 0));
        }

        $awaiting = $this->stateNames(self::FILTERS['a-valider']);
        $counts['urgents'] = RelaisItem::query()->whereIn('state', $awaiting)->where('urgency', Urgency::Urgent->value)->count();
        $counts['retard'] = RelaisItem::query()->whereIn('state', $awaiting)->where('sla_due_at', '<', now())->count();

        return $counts;
    }

    public function render(): View
    {
        $urgents = $this->counts['urgents'];

        if ($urgents > $this->knownUrgents) {
            $this->dispatch('urgent-relais', count: $urgents - $this->knownUrgents);
        }

        $this->knownUrgents = $urgents;

        return view('livewire.console.validation.index');
    }

    /**
     * @param  list<string>  $states
     * @return list<string>
     */
    private function stateNames(array $states): array
    {
        return array_map(fn (string $state): string => $state::getMorphClass(), $states);
    }
}
