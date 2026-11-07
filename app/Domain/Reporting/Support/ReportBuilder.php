<?php

namespace App\Domain\Reporting\Support;

use App\Domain\Candidature\Models\Candidature;
use App\Domain\Contribution\Models\RelaisItem;
use App\Domain\Media\Models\Media;
use App\Domain\Relais\Enums\RelaisStatus;
use App\Domain\Relais\Models\RelaisProfile;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Indicateurs de pilotage (§13, ADM-26) sur une période et sur le périmètre de l'utilisateur
 * (le CommuneScope s'applique : un coordonnateur n'obtient que sa commune ; une commande planifiée, tout le réseau).
 *
 * @phpstan-type Table array{title: string, columns: list<string>, rows: list<list<string|int|float|null>>}
 * @phpstan-type Section array{key: string, title: string, figures: list<array{label: string, value: string|int|float|null}>, tables: list<Table>}
 */
class ReportBuilder
{
    /**
     * @return array{from: CarbonImmutable, to: CarbonImmutable, sections: list<Section>}
     */
    public function build(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $to = $to->endOfDay();
        $items = fn (): Builder => RelaisItem::query()->whereBetween('received_at', [$from, $to]);

        return [
            'from' => $from,
            'to' => $to,
            'sections' => [
                $this->network($from, $to),
                $this->activity($items, $from, $to),
                $this->quality($items),
                $this->reactivity($items),
                $this->editorial($items, $from, $to),
                $this->technical($from, $to),
            ],
        ];
    }

    /**
     * @return Section
     */
    private function network(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $candidatures = Candidature::query()->whereBetween('submitted_at', [$from, $to]);
        $active = RelaisProfile::query()->where('status', RelaisStatus::Active->value)
            ->whereHas('items', fn (Builder $query) => $query->where('submitted_at', '>=', $to->subDays(30)));

        return [
            'key' => 'reseau',
            'title' => __('Réseau'),
            'figures' => [
                ['label' => __('Candidatures reçues'), 'value' => (clone $candidatures)->count()],
                ['label' => __('Candidatures acceptées'), 'value' => (clone $candidatures)->where('status', 'accepted')->count()],
                ['label' => __('Candidatures refusées'), 'value' => (clone $candidatures)->where('status', 'rejected')->count()],
                ['label' => __('Relais actifs (un envoi sur 30 jours)'), 'value' => (clone $active)->count()],
                ['label' => __('Communes couvertes'), 'value' => (clone $active)->distinct('commune_id')->count('commune_id')],
                ['label' => __('Quartiers couverts'), 'value' => (clone $active)->whereNotNull('localite_id')->distinct('localite_id')->count('localite_id')],
            ],
            'tables' => [],
        ];
    }

    /**
     * @param  callable(): Builder<RelaisItem>  $items
     * @return Section
     */
    private function activity(callable $items, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $perDay = $items()->selectRaw("to_char(received_at at time zone 'Africa/Abidjan', 'YYYY-MM-DD') as day, count(*) as total")->groupBy('day')->orderBy('day')->pluck('total', 'day');
        $perCommune = $items()->join('communes', 'communes.id', '=', 'relais_items.commune_id')->selectRaw('communes.name as name, count(*) as total')->groupBy('communes.name')->orderByDesc('total')->pluck('total', 'name');
        $perCategory = $items()->join('categories', 'categories.id', '=', 'relais_items.category_id')->selectRaw('categories.name as name, count(*) as total')->groupBy('categories.name')->orderByDesc('total')->pluck('total', 'name');
        $perKind = Media::query()->whereIn('relais_item_id', $items()->select('relais_items.id'))->selectRaw('kind, count(*) as total')->groupBy('kind')->pluck('total', 'kind');

        return [
            'key' => 'activite',
            'title' => __('Activité'),
            'figures' => [
                ['label' => __('Relais reçus'), 'value' => $items()->count()],
                ['label' => __('Réponses à des appels à relais'), 'value' => $items()->whereNotNull('appel_relais_id')->count()],
                ['label' => __('Moyenne par jour'), 'value' => $this->decimal($items()->count() / max(1, $from->diffInDays($to) + 1))],
            ],
            'tables' => [
                $this->table(__('Relais reçus par jour'), [__('Jour'), __('Relais')], $perDay),
                $this->table(__('Par commune'), [__('Commune'), __('Relais')], $perCommune),
                $this->table(__('Par catégorie'), [__('Catégorie'), __('Relais')], $perCategory),
                $this->table(__('Par type de média'), [__('Type'), __('Médias')], $perKind),
            ],
        ];
    }

    /**
     * @param  callable(): Builder<RelaisItem>  $items
     * @return Section
     */
    private function quality(callable $items): array
    {
        $decided = $items()->whereIn('state', ['validated', 'selected', 'broadcast', 'archived', 'rejected'])->count();
        $validated = $items()->whereIn('state', ['validated', 'selected', 'broadcast', 'archived'])->count();
        $reasons = $items()->join('rejection_reasons', 'rejection_reasons.id', '=', 'relais_items.rejection_reason_id')
            ->selectRaw('rejection_reasons.label as label, count(*) as total')->groupBy('rejection_reasons.label')->orderByDesc('total')->limit(10)->pluck('total', 'label');
        $duplicates = Media::query()->whereIn('relais_item_id', $items()->select('relais_items.id'))->whereNotNull('sha256')
            ->whereIn('sha256', Media::query()->select('sha256')->whereNotNull('sha256')->groupBy('sha256')->havingRaw('count(*) > 1'))->count();
        $sensitive = $items()->whereHas('statusHistory', fn (Builder $query) => $query->where('to', 'sensitive'))->count();

        return [
            'key' => 'qualite',
            'title' => __('Qualité'),
            'figures' => [
                ['label' => __('Taux de validation'), 'value' => $decided === 0 ? '—' : round($validated / $decided * 100).' %'],
                ['label' => __('Médias en doublon détectés'), 'value' => $duplicates],
                ['label' => __('Relais signalés sensibles'), 'value' => $sensitive],
            ],
            'tables' => [$this->table(__('Motifs de rejet les plus fréquents'), [__('Motif'), __('Relais')], $reasons)],
        ];
    }

    /**
     * @param  callable(): Builder<RelaisItem>  $items
     * @return Section
     */
    private function reactivity(callable $items): array
    {
        $decisions = DB::table('relais_status_history as h')
            ->join('relais_items as r', 'r.id', '=', 'h.relais_item_id')
            ->whereIn('r.id', $items()->select('relais_items.id'))
            ->whereIn('h.to', ['validated', 'rejected', 'complement_requested', 'sensitive'])
            ->whereRaw('h.id = (select min(h2.id) from relais_status_history h2 where h2.relais_item_id = r.id and h2.to in (?, ?, ?, ?))', ['validated', 'rejected', 'complement_requested', 'sensitive']);

        $minutes = 'extract(epoch from (h.created_at - r.received_at)) / 60';
        $average = (clone $decisions)->avg(DB::raw($minutes));
        $byCoordinator = (clone $decisions)->join('users as u', 'u.id', '=', 'h.actor_id')->selectRaw("u.name as name, round(avg({$minutes})) as minutes, count(*) as total")->groupBy('u.name')->orderBy('minutes')->get();
        $byCommune = (clone $decisions)->join('communes as c', 'c.id', '=', 'r.commune_id')->selectRaw("c.name as name, round(avg({$minutes})) as minutes, count(*) as total")->groupBy('c.name')->orderBy('minutes')->get();

        return [
            'key' => 'reactivite',
            'title' => __('Réactivité'),
            'figures' => [
                ['label' => __('Délai moyen avant décision'), 'value' => $average === null ? '—' : round((float) $average).' min'],
                ['label' => __('Relais escaladés (délai dépassé)'), 'value' => $items()->where('escalation_level', '>', 0)->count()],
                ['label' => __('Relais en retard aujourd’hui'), 'value' => RelaisItem::query()->whereIn('state', ['received', 'in_review'])->where('sla_due_at', '<', now())->count()],
            ],
            'tables' => [
                ['title' => __('Par coordonnateur'), 'columns' => [__('Coordonnateur'), __('Délai moyen (min)'), __('Décisions')], 'rows' => array_values($byCoordinator->map(fn ($row): array => [(string) $row->name, (int) $row->minutes, (int) $row->total])->all())],
                ['title' => __('Par commune'), 'columns' => [__('Commune'), __('Délai moyen (min)'), __('Décisions')], 'rows' => array_values($byCommune->map(fn ($row): array => [(string) $row->name, (int) $row->minutes, (int) $row->total])->all())],
            ],
        ];
    }

    /**
     * @param  callable(): Builder<RelaisItem>  $items
     * @return Section
     */
    private function editorial(callable $items, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $broadcast = RelaisItem::query()->whereBetween('broadcast_at', [$from, $to]);
        $byProgramme = (clone $broadcast)->selectRaw("coalesce(broadcast_programme, '—') as name, count(*) as total")->groupBy('name')->orderByDesc('total')->pluck('total', 'name');
        $delay = (clone $broadcast)->avg(DB::raw('extract(epoch from (broadcast_at - received_at)) / 3600'));
        $top = (clone $broadcast)->join('relais_profiles as p', 'p.id', '=', 'relais_items.relais_profile_id')
            ->selectRaw("p.display_name || ' · ' || coalesce(p.number, '') as name, count(*) as total")->groupBy('p.display_name', 'p.number')->orderByDesc('total')->limit(10)->pluck('total', 'name');

        return [
            'key' => 'editorial',
            'title' => __('Valeur éditoriale'),
            'figures' => [
                ['label' => __('Relais sélectionnés'), 'value' => $items()->whereNotNull('selected_at')->count()],
                ['label' => __('Relais diffusés'), 'value' => (clone $broadcast)->count()],
                ['label' => __('Délai moyen envoi → diffusion'), 'value' => $delay === null ? '—' : $this->decimal((float) $delay).' h'],
            ],
            'tables' => [
                $this->table(__('Diffusions par émission'), [__('Émission'), __('Relais')], $byProgramme),
                $this->table(__('Relais les plus diffusés'), [__('Relais'), __('Diffusions')], $top),
            ],
        ];
    }

    /**
     * @return Section
     */
    private function technical(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $medias = Media::query()->whereBetween('created_at', [$from, $to])
            ->whereIn('relais_item_id', RelaisItem::query()->select('id'));
        $finished = (clone $medias)->whereIn('status', ['ready', 'rejected'])->count();
        $ready = (clone $medias)->where('status', 'ready')->count();
        $uploadSeconds = (clone $medias)->whereNotNull('uploaded_at')->avg(DB::raw('extract(epoch from (uploaded_at - created_at))'));

        return [
            'key' => 'technique',
            'title' => __('Technique'),
            'figures' => [
                ['label' => __('Taux de réussite des envois'), 'value' => $finished === 0 ? '—' : round($ready / $finished * 100).' %'],
                ['label' => __('Durée moyenne de téléversement'), 'value' => $uploadSeconds === null ? '—' : round((float) $uploadSeconds).' s'],
                ['label' => __('Volume reçu sur la période'), 'value' => $this->size((int) (clone $medias)->sum('size'))],
                ['label' => __('Volume de stockage total'), 'value' => $this->size((int) Media::query()->where('status', 'ready')->sum('size'))],
            ],
            'tables' => [],
        ];
    }

    /**
     * @param  iterable<string, int|string>  $data
     * @param  list<string>  $columns
     * @return Table
     */
    private function table(string $title, array $columns, iterable $data): array
    {
        $rows = [];

        foreach ($data as $label => $value) {
            $rows[] = [(string) $label, (int) $value];
        }

        return ['title' => $title, 'columns' => $columns, 'rows' => $rows];
    }

    private function size(int $bytes): string
    {
        return $bytes >= 1073741824 ? $this->decimal($bytes / 1073741824, 2).' Go' : $this->decimal($bytes / 1048576).' Mo';
    }

    private function decimal(float $value, int $decimals = 1): string
    {
        return number_format($value, $decimals, ',', ' ');
    }
}
