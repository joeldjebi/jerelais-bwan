@php
    $tints = ['reseau' => 'green', 'activite' => 'blue', 'qualite' => 'purple', 'reactivite' => 'orange', 'editorial' => 'pink', 'technique' => 'gray'];
    $icons = ['reseau' => 'users', 'activite' => 'chart', 'qualite' => 'check', 'reactivite' => 'clock', 'editorial' => 'signal', 'technique' => 'layers'];
@endphp

<div class="space-y-8">
    <x-console.page-header :eyebrow="__('Pilotage')" :title="__('Rapports')" :subtitle="__('Les indicateurs du réseau, de l’activité, de la qualité, de la réactivité, de la valeur éditoriale et de la technique.')">
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="layers" :href="route('console.reports.export', ['format' => 'xlsx', 'from' => $rangeFrom, 'to' => $rangeTo])">{{ __('Excel') }}</x-ui.button>
            <x-ui.button variant="dark" icon="book" :href="route('console.reports.export', ['format' => 'pdf', 'from' => $rangeFrom, 'to' => $rangeTo])">{{ __('PDF') }}</x-ui.button>
        </x-slot:actions>
    </x-console.page-header>

    <div class="flex flex-wrap items-end gap-3" data-fade>
        <x-console.segmented property="period" :current="$period" :options="['1' => __('Aujourd’hui'), '7' => __('7 jours'), '30' => __('30 jours'), '90' => __('Trimestre'), 'custom' => __('Période')]" />
        @if ($period === 'custom')
            <div class="w-40"><x-console.input :label="__('Du')" type="date" name="from" wire:model.live="from" /></div>
            <div class="w-40"><x-console.input :label="__('Au')" type="date" name="to" wire:model.live="to" /></div>
        @endif
        <span wire:loading class="size-4 animate-spin rounded-full border-2 border-line border-t-tile-blue"></span>
    </div>

    @foreach ($this->report['sections'] as $section)
        <section class="space-y-4" wire:key="section-{{ $section['key'] }}">
            <div class="flex items-center gap-3" data-fade>
                <x-console.tile :icon="$icons[$section['key']]" :tint="$tints[$section['key']]" />
                <h2 class="text-[1.375rem] font-bold tracking-[-0.02em]">{{ $section['title'] }}</h2>
            </div>
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-3" data-bento>
                @foreach ($section['figures'] as $figure)
                    <x-console.widget class="flex min-h-28 flex-col justify-between" data-enter>
                        <p class="text-[0.8125rem] font-semibold text-mute">{{ $figure['label'] }}</p>
                        <p class="text-[2rem] leading-none font-bold tracking-[-0.03em] tabular-nums">{{ $figure['value'] ?? '—' }}</p>
                    </x-console.widget>
                @endforeach
            </div>
            @if (collect($section['tables'])->contains(fn ($table) => $table['rows'] !== []))
                <div class="grid gap-4 lg:grid-cols-2">
                    @foreach ($section['tables'] as $table)
                        @continue($table['rows'] === [])
                        <x-console.group :title="$table['title']">
                            <table class="w-full text-[0.875rem]">
                                <thead><tr class="text-left text-mute">@foreach ($table['columns'] as $index => $column)<th @class(['px-4 py-2 font-medium', 'text-right' => $index > 0])>{{ $column }}</th>@endforeach</tr></thead>
                                <tbody>
                                    @foreach (array_slice($table['rows'], 0, 12) as $row)
                                        <tr class="border-t border-line">@foreach ($row as $index => $cell)<td @class(['px-4 py-2', 'text-right font-semibold tabular-nums' => $index > 0])>{{ $cell }}</td>@endforeach</tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </x-console.group>
                    @endforeach
                </div>
            @endif
        </section>
    @endforeach
</div>
