<?php

namespace App\Livewire\Console\Appels;

use App\Domain\Access\Support\CommuneAccess;
use App\Domain\AppelRelais\Actions\PublishAppel;
use App\Domain\AppelRelais\Enums\AppelTarget;
use App\Domain\AppelRelais\Models\AppelRelais;
use App\Domain\Territoire\Models\Commune;
use App\Domain\Territoire\Models\LieuType;
use App\Domain\Territoire\Models\Region;
use App\Livewire\Console\Appels\Concerns\EditsAppel;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Appels à relais (ADM-14, COO-21) : création ciblée, suivi des réponses.
 *
 * @property-read Collection<int, AppelRelais> $appels
 * @property-read Collection<int, Commune> $communes
 * @property-read Collection<int, Region> $regions
 * @property-read Collection<int, LieuType> $lieuTypes
 * @property-read bool $isNational
 */
#[Title('Appels à relais')]
class Index extends Component
{
    use EditsAppel;

    public bool $creating = false;

    public function openCreate(): void
    {
        $this->resetAppelForm();
        $this->creating = true;
    }

    public function create(PublishAppel $publish): mixed
    {
        $this->validate($this->appelRules(), attributes: $this->appelAttributes());

        /** @var User $user */
        $user = Auth::user();

        try {
            $appel = $publish->handle($user, $this->appelPayload());
        } catch (InvalidArgumentException $exception) {
            $this->addError($this->target === 'regions' ? 'selectedRegions' : 'selectedCommunes', $exception->getMessage());

            return null;
        }

        return $this->redirectRoute('console.appels.show', $appel, navigate: true);
    }

    /**
     * @return Collection<int, AppelRelais>
     */
    #[Computed]
    public function appels(): Collection
    {
        /** @var User $user */
        $user = Auth::user();
        $ids = app(CommuneAccess::class)->communeIdsFor($user);

        return AppelRelais::query()
            ->withCount(['responses', 'responses as validated_count' => fn ($query) => $query->whereIn('state', ['validated', 'selected', 'broadcast'])])
            ->with(['creator:id,name', 'communes:id,name', 'regions:id,name', 'lieuType:id,name'])
            ->when($ids !== null, fn ($query) => $query->where(fn ($inner) => $inner
                ->where('created_by', $user->id)
                ->orWhere('target', AppelTarget::National->value)
                ->orWhereHas('communes', fn ($communes) => $communes->whereIn('communes.id', $ids))))
            ->latest()
            ->get();
    }

    public function render(): View
    {
        return view('livewire.console.appels.index');
    }
}
