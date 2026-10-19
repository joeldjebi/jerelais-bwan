<div class="space-y-8">
    <x-console.page-header
        :eyebrow="__('Réseau')"
        :title="__('Communes')"
        :subtitle="__('Ouvrez les communes aux Relais au rythme du déploiement : pilote à Abidjan, puis lancement national.')"
    />

    <div class="grid grid-cols-3 gap-4">
        <x-console.stat :label="__('Communes')" :value="$this->counts['total']" icon="map-pin" />
        <x-console.stat :label="__('Ouvertes aux Relais')" :value="$this->counts['open']" icon="check" tone="success" />
        <x-console.stat :label="__('Contours importés')" :value="$this->counts['contours']" icon="layers" :tone="$this->counts['contours'] === 0 ? 'alert' : 'default'"
            :hint="$this->counts['contours'] === 0 ? __('Nécessaires au routage par GPS') : null" />
    </div>

    <x-console.panel :padded="false">
        <div class="flex flex-wrap items-center gap-3 border-b border-line p-4">
            <label class="relative min-w-[14rem] flex-1">
                <span class="sr-only">{{ __('Rechercher une commune') }}</span>
                <x-ui.icon name="search" class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-mute" />
                <input type="search" wire:model.live.debounce.250ms="search" placeholder="{{ __('Rechercher une commune…') }}"
                    class="h-10 w-full rounded-xl border-0 bg-ink/[0.04] pr-3 pl-10 text-[0.875rem] placeholder:text-mute focus:bg-surface focus:ring-2 focus:ring-relais-500/45 focus:outline-none">
            </label>
            <div class="w-48">
                <x-console.select name="region" wire:model.live="region" class="h-10! text-[0.875rem]!">
                    <option value="">{{ __('Toutes les régions') }}</option>
                    @foreach ($this->regions as $region)
                        <option value="{{ $region->uuid }}">{{ $region->name }}</option>
                    @endforeach
                </x-console.select>
            </div>
            <div class="flex gap-1 rounded-xl bg-ink/[0.05] p-1 text-[0.8125rem] font-medium">
                @foreach (['' => __('Toutes'), 'open' => __('Ouvertes'), 'closed' => __('Fermées')] as $value => $label)
                    <button type="button" wire:click="$set('status', '{{ $value }}')"
                        @class(['rounded-lg px-3 py-1.5 transition-colors duration-300', 'bg-surface text-ink shadow-sm' => $status === $value, 'text-mute hover:text-ink' => $status !== $value])>{{ $label }}</button>
                @endforeach
            </div>
        </div>

        <div class="relative">
            <div wire:loading.flex wire:target="search,region,status,gotoPage,nextPage,previousPage" class="absolute inset-0 z-10 hidden items-start justify-center bg-surface/60 pt-16 backdrop-blur-[1px]">
                <span class="size-5 animate-spin rounded-full border-2 border-line border-t-relais-500"></span>
            </div>

            @if ($this->communes->isEmpty())
                <x-console.empty icon="map-pin" :title="__('Aucune commune ne correspond')" :text="__('Modifiez la recherche ou les filtres.')">
                    <x-ui.button variant="secondary" size="sm" wire:click="resetFilters">{{ __('Réinitialiser') }}</x-ui.button>
                </x-console.empty>
            @else
                <table class="w-full text-left text-[0.875rem]">
                    <thead>
                        <tr class="border-b border-line text-xs font-medium text-mute">
                            <th class="px-6 py-3 font-medium">{{ __('Commune') }}</th>
                            <th class="hidden px-4 py-3 font-medium md:table-cell">{{ __('Contour') }}</th>
                            <th class="hidden px-4 py-3 font-medium sm:table-cell">{{ __('Coordonnateurs') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('Statut') }}</th>
                            <th class="px-6 py-3"><span class="sr-only">{{ __('Actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($this->communes as $commune)
                            <tr wire:key="commune-{{ $commune->uuid }}" class="transition-colors duration-200 hover:bg-ink/[0.02]">
                                <td class="px-6 py-3.5">
                                    <p class="font-semibold">{{ $commune->name }}</p>
                                    <p class="text-xs text-mute">{{ $commune->region?->name ?? __('Sans région') }}</p>
                                </td>
                                <td class="hidden px-4 py-3.5 md:table-cell">
                                    @if ($commune->has_contour)
                                        <x-console.badge tone="success" dot>{{ __('Importé') }}</x-console.badge>
                                    @else
                                        <x-console.badge tone="warning" dot>{{ __('Manquant') }}</x-console.badge>
                                    @endif
                                </td>
                                <td class="hidden px-4 py-3.5 tabular-nums sm:table-cell">
                                    @if ($commune->coordinators_count > 0)
                                        {{ trans_choice(':count coordonnateur|:count coordonnateurs', $commune->coordinators_count) }}
                                    @else
                                        <span class="text-mute">{{ __('Aucun') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5">
                                    @if ($commune->is_active)
                                        <x-console.badge tone="success" dot>{{ __('Ouverte') }}</x-console.badge>
                                    @else
                                        <x-console.badge dot>{{ __('Fermée') }}</x-console.badge>
                                    @endif
                                </td>
                                <td class="px-6 py-3.5 text-right">
                                    <x-ui.button size="sm" :variant="$commune->is_active ? 'ghost' : 'secondary'" wire:click="askToggle('{{ $commune->uuid }}')">
                                        {{ $commune->is_active ? __('Fermer') : __('Ouvrir') }}
                                    </x-ui.button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                @if ($this->communes->hasPages())
                    <div class="flex items-center justify-between border-t border-line px-6 py-3 text-[0.8125rem] text-mute">
                        <span>{{ __(':from–:to sur :total', ['from' => $this->communes->firstItem(), 'to' => $this->communes->lastItem(), 'total' => $this->communes->total()]) }}</span>
                        <div class="flex gap-1">
                            <x-ui.button size="sm" variant="secondary" wire:click="previousPage" :disabled="$this->communes->onFirstPage()">{{ __('Précédent') }}</x-ui.button>
                            <x-ui.button size="sm" variant="secondary" wire:click="nextPage" :disabled="! $this->communes->hasMorePages()">{{ __('Suivant') }}</x-ui.button>
                        </div>
                    </div>
                @endif
            @endif
        </div>
    </x-console.panel>

    <x-console.panel :title="__('Importer les contours officiels')" :description="__('Nécessaires pour attribuer chaque relais à la commune où il a été filmé.')">
        <p class="text-[0.875rem] leading-relaxed text-mute">{{ __('Les contours sont importés depuis un fichier GeoJSON fourni par REFLET TV, avec la commande :') }}</p>
        <pre class="mt-3 overflow-x-auto rounded-xl bg-ink/[0.04] px-4 py-3 font-mono text-[0.8125rem]">php artisan territoire:import-communes communes.geojson --name-field=NOM --region-field=REGION</pre>
    </x-console.panel>

    <x-console.confirm
        model="confirmingToggle"
        action="confirmToggle"
        :tone="$this->pending?->is_active ? 'danger' : 'primary'"
        :title="$this->pending?->is_active ? __('Fermer :name ?', ['name' => $this->pending->name]) : __('Ouvrir :name ?', ['name' => $this->pending?->name])"
        :text="$this->pending?->is_active
            ? __('Les nouvelles inscriptions de Relais seront suspendues pour cette commune. Les Relais existants ne sont pas affectés.')
            : __('Les habitants de cette commune pourront candidater pour devenir Relais. Pensez à désigner un coordonnateur.')"
        :confirm-label="$this->pending?->is_active ? __('Fermer la commune') : __('Ouvrir la commune')"
    />
</div>
