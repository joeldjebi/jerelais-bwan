<?php

namespace App\Livewire\Console\Newsroom;

use App\Domain\Contribution\Enums\Urgency;
use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Contribution\States\Broadcast;
use App\Domain\Contribution\States\InReview;
use App\Domain\Contribution\States\Received;
use App\Domain\Contribution\States\Selected;
use App\Domain\Contribution\States\Validated;
use App\Domain\Contribution\Support\NetworkAlerts;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Mode « salle de rédaction » (ADM-05) : derniers relais, urgents, compteurs du jour, alertes ; rafraîchi toutes les 5 s.
 *
 * @property-read Collection<int, RelaisItem> $latest
 * @property-read Collection<int, RelaisItem> $urgent
 * @property-read array<string, int> $today
 * @property-read array{urgent: int, overdue: int, window: int, surges: list<array{commune: string, count: int}>} $alerts
 */
#[Layout('layouts::newsroom')]
#[Title('Salle de rédaction')]
class Index extends Component
{
    /**
     * @return Collection<int, RelaisItem>
     */
    #[Computed]
    public function latest(): Collection
    {
        return RelaisItem::query()
            ->whereIn('state', [Received::getMorphClass(), InReview::getMorphClass(), Validated::getMorphClass(), Selected::getMorphClass(), Broadcast::getMorphClass()])
            ->with(['category:id,name', 'commune:id,name', 'medias' => fn ($query) => $query->with('variants')])
            ->latest('received_at')
            ->limit(9)
            ->get();
    }

    /**
     * @return Collection<int, RelaisItem>
     */
    #[Computed]
    public function urgent(): Collection
    {
        return RelaisItem::query()
            ->whereIn('state', [Received::getMorphClass(), InReview::getMorphClass()])
            ->where('urgency', Urgency::Urgent->value)
            ->with('commune:id,name')
            ->oldest('received_at')
            ->limit(6)
            ->get();
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function today(): array
    {
        $base = fn () => RelaisItem::query()->where('received_at', '>=', now()->startOfDay());

        return [
            'received' => $base()->count(),
            'pending' => $base()->whereIn('state', [Received::getMorphClass(), InReview::getMorphClass()])->count(),
            'validated' => $base()->whereIn('state', [Validated::getMorphClass(), Selected::getMorphClass(), Broadcast::getMorphClass()])->count(),
            'broadcast' => $base()->where('state', Broadcast::getMorphClass())->count(),
        ];
    }

    /**
     * @return array{urgent: int, overdue: int, window: int, surges: list<array{commune: string, count: int}>}
     */
    #[Computed]
    public function alerts(): array
    {
        return app(NetworkAlerts::class)->current();
    }

    public function render(): View
    {
        return view('livewire.console.newsroom.index');
    }
}
