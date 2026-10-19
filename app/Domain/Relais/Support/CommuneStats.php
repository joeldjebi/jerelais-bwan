<?php

namespace App\Domain\Relais\Support;

use App\Domain\Candidature\Enums\CandidatureStatus;
use App\Domain\Candidature\Models\Candidature;
use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Relais\Enums\RelaisStatus;
use App\Domain\Relais\Models\RelaisProfile;
use Illuminate\Support\Facades\DB;

/**
 * Tableau de bord de la commune (COO-22) sur le périmètre de l'utilisateur connecté (CommuneScope) :
 * Relais actifs, relais reçus, validés, rejetés, diffusés, délai moyen de validation, couverture par quartier.
 */
class CommuneStats
{
    /**
     * @return array{active_relais: int, to_validate: int, candidatures: int, received: int, validated: int, rejected: int, broadcast: int, average_minutes: int|null}
     */
    public function summary(int $days = 30): array
    {
        $since = now()->subDays($days);
        $recent = fn () => RelaisItem::query()->where('received_at', '>=', $since);
        $byState = $recent()->selectRaw('state, count(*) as total')->groupBy('state')->pluck('total', 'state');

        $decided = RelaisItem::query()
            ->join('relais_status_history as decision', function ($join): void {
                $join->on('decision.relais_item_id', '=', 'relais_items.id')->whereIn('decision.to', ['validated', 'rejected', 'complement_requested', 'sensitive']);
            })
            ->where('relais_items.received_at', '>=', $since)
            ->groupBy('relais_items.id', 'relais_items.received_at')
            ->selectRaw('extract(epoch from (min(decision.created_at) - relais_items.received_at)) / 60 as minutes');

        $average = DB::query()->fromSub($decided, 'decisions')->avg('minutes');

        return [
            'active_relais' => RelaisProfile::query()->where('status', RelaisStatus::Active->value)->count(),
            'to_validate' => RelaisItem::query()->whereIn('state', ['received', 'in_review'])->count(),
            'candidatures' => Candidature::query()->whereIn('status', [CandidatureStatus::Submitted->value, CandidatureStatus::UnderReview->value])->count(),
            'received' => (int) $byState->sum(),
            'validated' => (int) (($byState['validated'] ?? 0) + ($byState['selected'] ?? 0) + ($byState['broadcast'] ?? 0)),
            'rejected' => (int) ($byState['rejected'] ?? 0),
            'broadcast' => (int) ($byState['broadcast'] ?? 0),
            'average_minutes' => $average === null ? null : (int) round((float) $average),
        ];
    }

    /**
     * Couverture par quartier : Relais actifs et relais reçus sur 30 jours, par localité.
     *
     * @return list<array{name: string, relais: int, items: int}>
     */
    public function coverage(): array
    {
        $relais = RelaisProfile::query()
            ->where('status', RelaisStatus::Active->value)
            ->leftJoin('localites', 'localites.id', '=', 'relais_profiles.localite_id')
            ->selectRaw("coalesce(localites.name, '—') as name, count(*) as total")
            ->groupBy('localites.name')
            ->pluck('total', 'name');

        return array_values($relais->map(fn (mixed $total, string $name): array => ['name' => $name === '—' ? __('Quartier non précisé') : $name, 'relais' => (int) $total, 'items' => 0])
            ->sortByDesc('relais')->values()->all());
    }
}
