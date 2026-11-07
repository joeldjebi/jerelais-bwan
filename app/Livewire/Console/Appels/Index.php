<?php

namespace App\Livewire\Console\Appels;

use App\Domain\Access\Enums\Permission;
use App\Domain\Access\Support\CommuneAccess;
use App\Domain\AppelRelais\Actions\PublishAppel;
use App\Domain\AppelRelais\Enums\AppelTarget;
use App\Domain\AppelRelais\Models\AppelRelais;
use App\Domain\Territoire\Models\Commune;
use App\Domain\Territoire\Models\LieuType;
use App\Domain\Territoire\Models\Region;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
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
    public bool $creating = false;

    public string $title = '';

    public string $instructions = '';

    public string $deadline = '';

    public string $target = 'communes';

    /** @var list<string> */
    public array $selectedCommunes = [];

    /** @var list<string> */
    public array $selectedRegions = [];

    public string $lat = '';

    public string $lng = '';

    public string $radiusKm = '5';

    public string $lieuType = '';

    public function openCreate(): void
    {
        $this->reset('title', 'instructions', 'selectedCommunes', 'selectedRegions', 'lat', 'lng', 'lieuType');
        $this->deadline = now()->addDay()->timezone('Africa/Abidjan')->format('Y-m-d\\TH:i');
        $this->target = $this->isNational ? 'national' : 'communes';
        $this->resetErrorBag();
        $this->creating = true;
    }

    public function create(PublishAppel $publish): mixed
    {
        $this->validate([
            'title' => ['required', 'string', 'max:150'],
            'instructions' => ['required', 'string', 'max:2000'],
            'deadline' => ['required', 'date', 'after:now'],
            'target' => ['required', Rule::enum(AppelTarget::class)],
            'lat' => ['required_if:target,radius', 'nullable', 'numeric', 'between:-90,90'],
            'lng' => ['required_if:target,radius', 'nullable', 'numeric', 'between:-180,180'],
            'radiusKm' => ['required_if:target,radius', 'nullable', 'numeric', 'min:0.1', 'max:500'],
        ], attributes: ['title' => __('sujet'), 'instructions' => __('consignes'), 'deadline' => __('date limite'), 'lat' => __('latitude'), 'lng' => __('longitude'), 'radiusKm' => __('rayon')]);

        /** @var User $user */
        $user = Auth::user();

        try {
            $appel = $publish->handle($user, [
                'title' => $this->title,
                'instructions' => $this->instructions,
                'target' => $this->target,
                'deadline_at' => $this->deadline,
                'communes' => $this->selectedCommunes,
                'regions' => $this->selectedRegions,
                'lat' => $this->lat === '' ? null : (float) $this->lat,
                'lng' => $this->lng === '' ? null : (float) $this->lng,
                'radius_km' => $this->radiusKm === '' ? null : (float) $this->radiusKm,
                'lieu_type_id' => $this->lieuType === '' ? null : LieuType::query()->where('uuid', $this->lieuType)->value('id'),
            ]);
        } catch (InvalidArgumentException $exception) {
            $this->addError('selectedCommunes', $exception->getMessage());

            return null;
        }

        return $this->redirectRoute('console.appels.show', $appel, navigate: true);
    }

    #[Computed]
    public function isNational(): bool
    {
        return Auth::user()?->hasPermissionTo(Permission::AppelsCreate->value) ?? false;
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
            ->with('creator:id,name')
            ->when($ids !== null, fn ($query) => $query->where(fn ($inner) => $inner
                ->where('created_by', $user->id)
                ->orWhere('target', AppelTarget::National->value)
                ->orWhereHas('communes', fn ($communes) => $communes->whereIn('communes.id', $ids))))
            ->latest()
            ->get();
    }

    /**
     * @return Collection<int, Commune>
     */
    #[Computed]
    public function communes(): Collection
    {
        /** @var User $user */
        $user = Auth::user();
        $ids = app(CommuneAccess::class)->communeIdsFor($user);

        return Commune::query()->active()->when($ids !== null, fn ($query) => $query->whereIn('id', $ids))->orderBy('name')->get(['id', 'uuid', 'name']);
    }

    /**
     * @return Collection<int, Region>
     */
    #[Computed(persist: true)]
    public function regions(): Collection
    {
        return Region::query()->orderBy('name')->get(['id', 'uuid', 'name']);
    }

    /**
     * @return Collection<int, LieuType>
     */
    #[Computed(persist: true)]
    public function lieuTypes(): Collection
    {
        return LieuType::query()->orderBy('name')->get(['id', 'uuid', 'name']);
    }

    public function render(): View
    {
        return view('livewire.console.appels.index');
    }
}
