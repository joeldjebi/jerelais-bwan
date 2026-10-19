<?php

namespace App\Livewire\Console\Relais;

use App\Domain\Relais\Enums\RelaisStatus;
use App\Domain\Relais\Models\RelaisProfile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Relais du périmètre (COO-04) ou du réseau national (ADM-22) : recherche, statut, activité.
 *
 * @property-read LengthAwarePaginator<int, RelaisProfile> $profiles
 */
#[Title('Relais')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    public function updated(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, RelaisProfile>
     */
    #[Computed]
    public function profiles(): LengthAwarePaginator
    {
        $term = trim($this->search);

        return RelaisProfile::query()
            ->with(['commune:id,name', 'localite:id,name'])
            ->withCount(['items as sent_count' => fn ($query) => $query->whereNotNull('submitted_at')])
            ->withCount(['items as validated_count' => fn ($query) => $query->whereIn('state', ['validated', 'selected', 'broadcast', 'archived'])])
            ->when($term !== '', fn ($query) => $query->where(fn ($inner) => $inner->where('display_name', 'ilike', "%{$term}%")->orWhere('number', 'ilike', "%{$term}%")))
            ->when($this->status !== '', fn ($query) => $query->where('status', RelaisStatus::from($this->status)->value))
            ->orderBy('display_name')
            ->paginate(30);
    }

    public function render(): View
    {
        return view('livewire.console.relais.index');
    }
}
