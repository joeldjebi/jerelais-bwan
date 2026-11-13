<?php

namespace App\Livewire\Console\Appels\Concerns;

use App\Domain\Access\Enums\Permission;
use App\Domain\Access\Support\CommuneAccess;
use App\Domain\AppelRelais\Enums\AppelTarget;
use App\Domain\AppelRelais\Models\AppelRelais;
use App\Domain\AppelRelais\Support\AppelAudience;
use App\Domain\Territoire\Models\Commune;
use App\Domain\Territoire\Models\LieuType;
use App\Domain\Territoire\Models\Region;
use App\Models\User;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Throwable;

/**
 * Formulaire d'un appel à relais (création et modification) : sujet, consignes, date limite, zone, type de lieu,
 * avec le nombre de Relais qui seront prévenus.
 *
 * @property-read Collection<int, Commune> $communes
 * @property-read Collection<int, Region> $regions
 * @property-read Collection<int, LieuType> $lieuTypes
 * @property-read bool $isNational
 * @property-read int|null $audienceCount
 */
trait EditsAppel
{
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

    protected function resetAppelForm(): void
    {
        $this->reset('title', 'instructions', 'selectedCommunes', 'selectedRegions', 'lat', 'lng', 'lieuType');
        $this->radiusKm = '5';
        $this->deadline = now()->addDay()->timezone('Africa/Abidjan')->format('Y-m-d\TH:i');
        $this->target = $this->isNational ? 'national' : 'communes';
        $this->resetErrorBag();
    }

    protected function fillAppelForm(AppelRelais $appel): void
    {
        $this->resetErrorBag();
        $this->title = $appel->title;
        $this->instructions = $appel->instructions;
        $this->deadline = $appel->deadline_at->timezone('Africa/Abidjan')->format('Y-m-d\TH:i');
        $this->target = $appel->target->value;
        $this->selectedCommunes = array_values($appel->communes()->pluck('communes.uuid')->map(fn ($uuid): string => (string) $uuid)->all());
        $this->selectedRegions = array_values($appel->regions()->pluck('regions.uuid')->map(fn ($uuid): string => (string) $uuid)->all());
        $this->lat = $appel->center === null ? '' : (string) $appel->center->getLatitude();
        $this->lng = $appel->center === null ? '' : (string) $appel->center->getLongitude();
        $this->radiusKm = $appel->radius_m === null ? '5' : (string) round($appel->radius_m / 1000, 1);
        $this->lieuType = (string) $appel->lieuType?->uuid;
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function appelRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'instructions' => ['required', 'string', 'max:2000'],
            'deadline' => ['required', 'date', 'after:now'],
            'target' => ['required', Rule::enum(AppelTarget::class)],
            'selectedCommunes' => [Rule::requiredIf($this->target === AppelTarget::Communes->value || ! $this->isNational), 'array'],
            'selectedRegions' => [Rule::requiredIf($this->isNational && $this->target === AppelTarget::Regions->value), 'array'],
            'lat' => ['required_if:target,radius', 'nullable', 'numeric', 'between:-90,90'],
            'lng' => ['required_if:target,radius', 'nullable', 'numeric', 'between:-180,180'],
            'radiusKm' => ['required_if:target,radius', 'nullable', 'numeric', 'min:0.1', 'max:500'],
            'lieuType' => ['nullable', 'uuid', Rule::exists('lieu_types', 'uuid')],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function appelAttributes(): array
    {
        return [
            'title' => __('sujet'), 'instructions' => __('consignes'), 'deadline' => __('date limite'),
            'selectedCommunes' => __('communes'), 'selectedRegions' => __('régions'),
            'lat' => __('latitude'), 'lng' => __('longitude'), 'radiusKm' => __('rayon'), 'lieuType' => __('type de lieu'),
        ];
    }

    /**
     * @return array{title: string, instructions: string, target: string, deadline_at: string, communes: list<string>, regions: list<string>, lat: float|null, lng: float|null, radius_km: float|null, lieu_type_id: int|null}
     */
    protected function appelPayload(): array
    {
        return [
            'title' => $this->title,
            'instructions' => $this->instructions,
            'target' => $this->target,
            'deadline_at' => $this->deadline,
            'communes' => $this->selectedCommunes,
            'regions' => $this->selectedRegions,
            'lat' => $this->lat === '' ? null : (float) $this->lat,
            'lng' => $this->lng === '' ? null : (float) $this->lng,
            'radius_km' => $this->radiusKm === '' ? null : (float) $this->radiusKm,
            'lieu_type_id' => $this->lieuType === '' ? null : (int) LieuType::query()->where('uuid', $this->lieuType)->value('id'),
        ];
    }

    /**
     * Nombre de Relais que la zone saisie toucherait (aperçu avant publication). Null si la zone est incomplète.
     */
    #[Computed]
    public function audienceCount(): ?int
    {
        $target = AppelTarget::tryFrom($this->isNational ? $this->target : AppelTarget::Communes->value);

        if ($target === null) {
            return null;
        }

        $preview = new AppelRelais(['target' => $target, 'radius_m' => (int) round(((float) $this->radiusKm) * 1000)]);

        if ($target === AppelTarget::Radius) {
            if (! is_numeric($this->lat) || ! is_numeric($this->lng)) {
                return null;
            }

            try {
                $preview->center = Point::makeGeodetic((float) $this->lat, (float) $this->lng);
            } catch (Throwable) {
                return null;
            }
        }

        $communeIds = array_values(array_map('intval', Commune::query()->whereIn('uuid', $this->selectedCommunes)->pluck('id')->all()));
        $regionIds = array_values(array_map('intval', Region::query()->whereIn('uuid', $this->selectedRegions)->pluck('id')->all()));

        if (! $this->isNational) {
            /** @var User $user */
            $user = Auth::user();
            $allowed = app(CommuneAccess::class)->communeIdsFor($user) ?? [];
            $communeIds = array_values(array_intersect($communeIds, $allowed)) ?: $allowed;
        }

        return AppelAudience::profilesFor($preview, $communeIds, $regionIds)->count();
    }

    #[Computed]
    public function isNational(): bool
    {
        return Auth::user()?->hasPermissionTo(Permission::AppelsCreate->value) ?? false;
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
}
