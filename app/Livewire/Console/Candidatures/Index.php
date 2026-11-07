<?php

namespace App\Livewire\Console\Candidatures;

use App\Domain\Candidature\Enums\CandidatureStatus;
use App\Domain\Candidature\Models\Candidature;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * File des candidatures de la commune, triée par date de dépôt (COO-01).
 * Le CommuneScope limite automatiquement un coordonnateur à ses communes.
 *
 * @property-read LengthAwarePaginator<int, Candidature> $candidatures
 * @property-read array<string, int> $counts
 */
#[Title('Candidatures')]
class Index extends Component
{
    use WithPagination;

    public const FILTERS = [
        'a-traiter' => [CandidatureStatus::Submitted, CandidatureStatus::UnderReview],
        'attente' => [CandidatureStatus::OnHold],
        'complement' => [CandidatureStatus::ComplementRequested],
        'decidees' => [CandidatureStatus::Accepted, CandidatureStatus::Rejected],
    ];

    #[Url(as: 'file')]
    public string $filter = 'a-traiter';

    #[Url(as: 'q')]
    public string $search = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Candidature::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['filter', 'search'], true)) {
            $this->resetPage();
        }
    }

    /**
     * @return LengthAwarePaginator<int, Candidature>
     */
    #[Computed]
    public function candidatures(): LengthAwarePaginator
    {
        $statuses = self::FILTERS[$this->filter] ?? self::FILTERS['a-traiter'];
        $decided = $this->filter === 'decidees';
        $term = trim($this->search);

        return Candidature::query()
            ->with(['commune:id,name', 'localite:id,name'])
            ->whereIn('status', array_map(fn (CandidatureStatus $status): string => $status->value, $statuses))
            ->when($term !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('last_name', 'ilike', '%'.$term.'%')
                ->orWhere('first_names', 'ilike', '%'.$term.'%')))
            ->orderBy($decided ? 'decided_at' : 'submitted_at', $decided ? 'desc' : 'asc')
            ->paginate(20);
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function counts(): array
    {
        $byStatus = Candidature::query()->where('status', '!=', CandidatureStatus::Draft->value)
            ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $counts = [];

        foreach (self::FILTERS as $key => $statuses) {
            $counts[$key] = (int) collect($statuses)->sum(fn (CandidatureStatus $status): int => (int) ($byStatus[$status->value] ?? 0));
        }

        return $counts;
    }

    public function render(): View
    {
        return view('livewire.console.candidatures.index');
    }
}
