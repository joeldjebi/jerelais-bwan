<?php

namespace App\Livewire\Console\Map;

use App\Domain\Contribution\Enums\Urgency;
use App\Domain\Contribution\Models\Category;
use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Contribution\Support\RelaisSearch;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Carte interactive de la Côte d'Ivoire (ADM-02) : relais localisés, regroupés, filtrés par période,
 * catégorie, urgence et statut ; zoom jusqu'au quartier. Limitée au périmètre de l'utilisateur.
 *
 * @property-read list<array{uuid: string, lat: float, lng: float, title: string, urgency: string, state: string, label: string, commune: string|null, url: string}> $points
 * @property-read Collection<int, Category> $categories
 */
#[Title('Carte des relais')]
class Index extends Component
{
    public const LIMIT = 3000;

    #[Url(except: '7')]
    public string $period = '7';

    #[Url(except: '')]
    public string $category = '';

    #[Url(except: '')]
    public string $urgency = '';

    #[Url(except: '')]
    public string $state = '';

    public function updated(): void
    {
        $this->dispatch('map-points', points: $this->points);
    }

    /**
     * @return list<array{uuid: string, lat: float, lng: float, title: string, urgency: string, state: string, label: string, commune: string|null, url: string}>
     */
    #[Computed]
    public function points(): array
    {
        $from = $this->period === 'all' ? '' : now()->subDays((int) $this->period)->toDateString();

        return array_values(RelaisSearch::query(['from' => $from, 'category' => $this->category, 'urgency' => $this->urgency, 'state' => $this->state])
            ->whereNotNull('location')
            ->with('commune:id,name')
            ->latest('received_at')
            ->limit(self::LIMIT)
            ->get(['id', 'uuid', 'title', 'urgency', 'state', 'location', 'commune_id', 'received_at'])
            ->map(fn (RelaisItem $relais): array => [
                'uuid' => $relais->uuid,
                'lat' => (float) $relais->location?->getLatitude(),
                'lng' => (float) $relais->location?->getLongitude(),
                'title' => $relais->title ?: __('Sans titre'),
                'urgency' => $relais->urgency->value,
                'state' => $relais->state->getValue(),
                'label' => $relais->state->label(),
                'commune' => $relais->commune?->name,
                'url' => route('console.validation.show', $relais),
            ])
            ->all());
    }

    /**
     * @return Collection<int, Category>
     */
    #[Computed(persist: true)]
    public function categories(): Collection
    {
        return Category::query()->active()->get(['id', 'uuid', 'name']);
    }

    public function render(): View
    {
        return view('livewire.console.map.index', ['urgencies' => Urgency::cases()]);
    }
}
