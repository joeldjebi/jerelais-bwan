<?php

namespace App\Livewire\Console\Identity;

use App\Domain\Access\Enums\Permission;
use App\Domain\Identity\Enums\IdentityStatus;
use App\Domain\Identity\Models\IdentityVerification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Vérifications d'identité des Relais inscrits depuis l'application (MOB-01), réservées à l'administration.
 *
 * @property-read LengthAwarePaginator<int, IdentityVerification> $verifications
 * @property-read array<string, int> $counts
 */
#[Title('Vérifications d’identité')]
class Index extends Component
{
    use WithPagination;

    public const array FILTERS = ['a-verifier' => IdentityStatus::Submitted, 'verifiees' => IdentityStatus::Verified, 'a-corriger' => IdentityStatus::Rejected];

    #[Url(as: 'file')]
    public string $filter = 'a-verifier';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    public function mount(): void
    {
        abort_unless(Auth::user()?->hasPermissionTo(Permission::UsersManage->value) === true, 403);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['filter', 'search'], true)) {
            $this->resetPage();
        }
    }

    /**
     * @return LengthAwarePaginator<int, IdentityVerification>
     */
    #[Computed]
    public function verifications(): LengthAwarePaginator
    {
        $status = self::FILTERS[$this->filter] ?? IdentityStatus::Submitted;
        $term = trim($this->search);

        return IdentityVerification::query()
            ->with(['user:id,uuid,name', 'commune:id,name', 'localite:id,name'])
            ->where('status', $status->value)
            ->when($term !== '', fn ($query) => $query->whereHas('user', fn ($inner) => $inner->whereLike('name', '%'.$term.'%')))
            ->orderBy($status === IdentityStatus::Submitted ? 'submitted_at' : 'decided_at', $status === IdentityStatus::Submitted ? 'asc' : 'desc')
            ->paginate(20);
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function counts(): array
    {
        $byStatus = IdentityVerification::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return array_map(fn (IdentityStatus $status): int => (int) ($byStatus[$status->value] ?? 0), self::FILTERS);
    }

    public function render(): View
    {
        return view('livewire.console.identity.index');
    }
}
