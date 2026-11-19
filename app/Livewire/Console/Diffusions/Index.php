<?php

namespace App\Livewire\Console\Diffusions;

use App\Domain\Access\Enums\Permission;
use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Editorial\Actions\PublishToShowcase;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Diffusions « Vu sur REFLET TV » (MOB-25, WEB-05) : les relais publiés sur la vitrine, c'est-à-dire exactement
 * ce que l'application reçoit avec GET /diffusions et ce que montre la page publique du site.
 *
 * @property-read LengthAwarePaginator<int, RelaisItem> $diffusions
 */
#[Title('Diffusions')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Locked]
    public ?string $pendingWithdraw = null;

    public bool $confirmingWithdraw = false;

    public function mount(): void
    {
        $this->authorizeAccess();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function askWithdraw(string $uuid): void
    {
        abort_unless(Str::isUuid($uuid), 404);
        $this->pendingWithdraw = $uuid;
        $this->confirmingWithdraw = true;
    }

    public function confirmWithdraw(PublishToShowcase $publish): void
    {
        $this->authorizeAccess();
        $relais = RelaisItem::query()->where('uuid', (string) $this->pendingWithdraw)->whereNotNull('showcased_at')->firstOrFail();

        /** @var User $user */
        $user = Auth::user();
        $publish->withdraw($relais, $user);

        $this->confirmingWithdraw = false;
        $this->pendingWithdraw = null;
        unset($this->diffusions);
        $this->dispatch('toast', message: __('Retiré de la vitrine : il n’apparaît plus dans l’application ni sur le site.'));
    }

    /**
     * @return LengthAwarePaginator<int, RelaisItem>
     */
    #[Computed]
    public function diffusions(): LengthAwarePaginator
    {
        return RelaisItem::query()
            ->whereNotNull('showcased_at')
            ->with(['category:id,name', 'commune:id,name', 'relaisProfile:id,display_name,number,candidature_id', 'relaisProfile.candidature'])
            ->when($this->search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->whereLike('showcase_title', '%'.$this->search.'%')
                ->orWhereLike('title', '%'.$this->search.'%')
                ->orWhereLike('broadcast_programme', '%'.$this->search.'%')))
            ->latest('showcased_at')
            ->paginate(20);
    }

    public function render(): View
    {
        return view('livewire.console.diffusions.index');
    }

    private function authorizeAccess(): void
    {
        abort_unless(Auth::user()?->can(Permission::RelaisArbitrate->value) === true, 403);
    }
}
