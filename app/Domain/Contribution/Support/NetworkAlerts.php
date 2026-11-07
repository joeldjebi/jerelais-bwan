<?php

namespace App\Domain\Contribution\Support;

use App\Domain\Contribution\Enums\Urgency;
use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Contribution\States\InReview;
use App\Domain\Contribution\States\Received;
use App\Domain\Settings\Settings;
use App\Domain\Territoire\Models\Commune;

/**
 * Alertes de la console (ADM-03) : relais urgents en attente, relais en retard de validation,
 * et afflux inhabituel sur une même commune (signe d'un événement).
 */
class NetworkAlerts
{
    public function __construct(private readonly Settings $settings) {}

    /**
     * @return array{urgent: int, overdue: int, window: int, surges: list<array{commune: string, count: int}>}
     */
    public function current(): array
    {
        $awaiting = [Received::getMorphClass(), InReview::getMorphClass()];
        $window = $this->settings->integer('alerts.surge_window_minutes');
        $threshold = $this->settings->integer('alerts.surge_threshold');

        $surges = RelaisItem::query()
            ->selectRaw('commune_id, count(*) as total')
            ->whereNotNull('commune_id')
            ->where('received_at', '>=', now()->subMinutes($window))
            ->groupBy('commune_id')
            ->havingRaw('count(*) >= ?', [$threshold])
            ->orderByDesc('total')
            ->get();

        $names = Commune::query()->whereIn('id', $surges->pluck('commune_id'))->pluck('name', 'id');

        return [
            'urgent' => RelaisItem::query()->whereIn('state', $awaiting)->where('urgency', Urgency::Urgent->value)->count(),
            'overdue' => RelaisItem::query()->whereIn('state', $awaiting)->where('sla_due_at', '<', now())->count(),
            'window' => $window,
            'surges' => array_values($surges->map(fn ($row): array => [
                'commune' => (string) ($names[$row->getAttribute('commune_id')] ?? '—'),
                'count' => (int) $row->getAttribute('total'),
            ])->all()),
        ];
    }
}
