<?php

namespace App\Livewire\Console\Relais;

use App\Domain\Access\Enums\Permission;
use App\Domain\Access\Support\CommuneAccess;
use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Relais\Actions\ChangeRelaisStatus;
use App\Domain\Relais\Enums\RelaisStatus;
use App\Domain\Relais\Models\RelaisProfile;
use App\Domain\Territoire\Models\Commune;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Fiche d'un Relais (COO-04, ADM-22) : identité de badge, historique, statistiques, suspension, radiation,
 * réintégration et transfert de commune.
 *
 * @property-read Collection<int, RelaisItem> $history
 * @property-read array<string, int> $stats
 * @property-read Collection<int, Commune> $communes
 */
class Show extends Component
{
    #[Locked]
    public RelaisProfile $profile;

    public string $action = '';

    public bool $acting = false;

    public string $reason = '';

    public string $targetCommune = '';

    public function mount(RelaisProfile $profile): void
    {
        abort_unless(app(CommuneAccess::class)->canAccessCommune($this->user(), $profile->commune_id), 404);
        $this->profile = $profile;
    }

    public function open(string $action): void
    {
        $this->reset('reason', 'targetCommune');
        $this->resetErrorBag();
        $this->action = $action;
        $this->acting = true;
    }

    public function apply(ChangeRelaisStatus $change): void
    {
        $user = $this->user();
        abort_unless($user->hasPermissionTo(Permission::RelaisProfilesSuspend->value), 403);
        $this->resetErrorBag();

        try {
            if ($this->action === 'transfer') {
                abort_unless($user->hasPermissionTo(Permission::TerritoireManage->value), 403);
                $commune = Commune::query()->active()->where('uuid', $this->targetCommune)->first();

                if ($commune === null) {
                    $this->addError('reason', __('Choisissez la commune de destination.'));

                    return;
                }

                $change->transfer($this->profile, $commune, $user, $this->reason);
            } else {
                $change->handle($this->profile, RelaisStatus::from($this->action), $user, $this->reason);
            }
        } catch (InvalidArgumentException $exception) {
            $this->addError('reason', $exception->getMessage());

            return;
        }

        $this->acting = false;
        $this->profile->refresh();
        $this->dispatch('toast', message: __('Statut mis à jour.'));
    }

    /**
     * @return Collection<int, RelaisItem>
     */
    #[Computed]
    public function history(): Collection
    {
        // Périmètre communal conservé : un coordonnateur ne voit pas les relais de « son » Relais routés vers une autre commune.
        return RelaisItem::query()
            ->where('relais_profile_id', $this->profile->id)
            ->whereNotNull('submitted_at')
            ->with(['category:id,name', 'commune:id,name', 'relaisProfile:id,display_name,number', 'lockedBy:id,name', 'medias' => fn ($query) => $query->with('variants')])
            ->latest('received_at')
            ->limit(30)
            ->get();
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function stats(): array
    {
        $states = $this->profile->items()->whereNotNull('submitted_at')->selectRaw('state, count(*) as total')->groupBy('state')->pluck('total', 'state');
        $validated = (int) collect(['validated', 'selected', 'broadcast', 'archived'])->sum(fn ($state) => $states[$state] ?? 0);
        $rejected = (int) ($states['rejected'] ?? 0);

        return [
            'sent' => (int) $states->sum(),
            'validated' => $validated,
            'rejected' => $rejected,
            'broadcast' => (int) ($states['broadcast'] ?? 0),
            'reliability' => $validated + $rejected === 0 ? -1 : (int) round($validated / ($validated + $rejected) * 100),
        ];
    }

    /**
     * @return Collection<int, Commune>
     */
    #[Computed(persist: true)]
    public function communes(): Collection
    {
        return Commune::query()->active()->orderBy('name')->get(['id', 'uuid', 'name']);
    }

    public function render(): View
    {
        $this->profile->loadMissing(['commune.region', 'localite', 'candidature']);

        return view('livewire.console.relais.show')->title($this->profile->display_name);
    }

    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
