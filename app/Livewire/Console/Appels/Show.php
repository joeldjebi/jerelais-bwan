<?php

namespace App\Livewire\Console\Appels;

use App\Domain\Access\Enums\Permission;
use App\Domain\Access\Support\CommuneAccess;
use App\Domain\AppelRelais\Actions\PublishAppel;
use App\Domain\AppelRelais\Enums\AppelStatus;
use App\Domain\AppelRelais\Enums\AppelTarget;
use App\Domain\AppelRelais\Models\AppelRelais;
use App\Domain\Contribution\Models\RelaisItem;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Suivi d'un appel (ADM-14) : statistiques de réponse, envois rattachés, accord et clôture.
 *
 * @property-read Collection<int, RelaisItem> $responses
 */
class Show extends Component
{
    #[Locked]
    public AppelRelais $appel;

    public function mount(): void
    {
        abort_unless($this->isInScope(), 404);
    }

    public function approve(PublishAppel $publish): void
    {
        abort_unless($this->user()->hasPermissionTo(Permission::SettingsManage->value), 403);
        $publish->approve($this->appel, $this->user());
        $this->appel->refresh();
        $this->dispatch('toast', message: __('Appel approuvé et publié.'));
    }

    public function close(PublishAppel $publish): void
    {
        abort_unless($this->appel->created_by === $this->user()->id || ($this->user()->hasPermissionTo(Permission::AppelsCreate->value) && app(CommuneAccess::class)->communeIdsFor($this->user()) === null), 403);
        $publish->close($this->appel, $this->user());
        $this->appel->refresh();
        $this->dispatch('toast', message: __('Appel clos.'));
    }

    /**
     * @return Collection<int, RelaisItem>
     */
    #[Computed]
    public function responses(): Collection
    {
        return $this->appel->responses()
            ->where('state', '!=', 'uploading')
            ->with(['category:id,name', 'commune:id,name', 'relaisProfile:id,display_name,number', 'lockedBy:id,name', 'medias' => fn ($query) => $query->with('variants')])
            ->latest('received_at')
            ->get();
    }

    public function render(): View
    {
        $this->appel->loadMissing(['communes:id,name', 'regions:id,name', 'lieuType:id,name', 'creator:id,name']);

        return view('livewire.console.appels.show', [
            'pending' => $this->appel->status === AppelStatus::PendingApproval,
        ])->title($this->appel->title);
    }

    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    /**
     * Même périmètre que la liste : appels nationaux, appels créés par l'utilisateur ou visant ses communes.
     */
    private function isInScope(): bool
    {
        $ids = app(CommuneAccess::class)->communeIdsFor($this->user());

        return $ids === null
            || $this->appel->created_by === $this->user()->id
            || $this->appel->target === AppelTarget::National
            || $this->appel->communes()->whereIn('communes.id', $ids)->exists();
    }
}
