<div class="space-y-8">
    <x-console.page-header
        :eyebrow="__('Réseau')"
        :title="__('Communes')"
        :subtitle="__('Ouvrez les communes aux Relais au rythme du déploiement : pilote à Abidjan, puis lancement national.')"
    />

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3" data-bento>
        <x-console.stat :label="__('Communes')" value="" :count="$this->counts['total']" icon="map-pin" tint="blue" :hint="__('Dans le référentiel')" />
        <x-console.stat :label="__('Ouvertes aux Relais')" value="" :count="$this->counts['open']" icon="check" tint="green" :hint="__('Inscriptions possibles')" />
        <x-console.stat :label="__('Contours importés')" value="" :count="$this->counts['contours']" icon="layers" :tint="$this->counts['contours'] === 0 ? 'pink' : 'purple'"
            :hint="$this->counts['contours'] === 0 ? __('Nécessaires au routage par GPS') : __('Routage par GPS actif')" />
    </div>

    <x-console.group :title="trans_choice(':count commune|:count communes', $this->communes->total())">
        <x-slot:actions>
            <span wire:loading wire:target="search,region,status,nextPage,previousPage" class="size-4 animate-spin rounded-full border-2 border-line border-t-tile-blue"></span>
        </x-slot:actions>

        <div class="flex flex-wrap items-center gap-3 border-b border-line p-3">
            <x-console.search wire:model.live.debounce.250ms="search" :placeholder="__('Rechercher une commune')" />
            <div class="w-52">
                <x-console.select name="region" wire:model.live="region" class="h-9! text-[0.875rem]!">
                    <option value="">{{ __('Toutes les régions') }}</option>
                    @foreach ($this->regions as $region)
                        <option value="{{ $region->uuid }}">{{ $region->name }}</option>
                    @endforeach
                </x-console.select>
            </div>
            <x-console.segmented property="status" :current="$status" :options="['' => __('Toutes'), 'open' => __('Ouvertes'), 'closed' => __('Fermées')]" />
        </div>

        @if ($this->communes->isEmpty())
            <x-console.empty icon="map-pin" tint="blue" :title="__('Aucune commune ne correspond')" :text="__('Modifiez la recherche ou les filtres.')">
                <x-ui.button variant="secondary" size="sm" wire:click="resetFilters">{{ __('Réinitialiser') }}</x-ui.button>
            </x-console.empty>
        @else
            <ul wire:loading.class="opacity-50" wire:target="search,region,status" class="transition-opacity duration-300" data-stagger>
                @foreach ($this->communes as $commune)
                    <li wire:key="commune-{{ $commune->uuid }}" class="flex items-center gap-3.5 px-4 py-3 transition-colors duration-200 hover:bg-black/[0.02] dark:hover:bg-white/[0.03]">
                        <x-console.tile icon="map-pin" :tint="$commune->is_active ? 'green' : 'blue'" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-[0.9375rem] font-semibold">{{ $commune->name }}</p>
                            <p class="truncate text-[0.8125rem] text-mute">
                                {{ $commune->region?->name ?? __('Sans région') }}
                                · {{ $commune->coordinators_count > 0 ? trans_choice(':count coordonnateur|:count coordonnateurs', $commune->coordinators_count) : __('aucun coordonnateur') }}
                            </p>
                        </div>
                        <div class="hidden md:block">
                            @if ($commune->has_contour)
                                <x-console.badge tone="success">{{ __('Contour importé') }}</x-console.badge>
                            @else
                                <x-console.badge tone="warning">{{ __('Contour manquant') }}</x-console.badge>
                            @endif
                        </div>
                        <span class="hidden w-16 text-right text-[0.8125rem] text-mute sm:block">{{ $commune->is_active ? __('Ouverte') : __('Fermée') }}</span>
                        <x-console.switch :on="$commune->is_active" :label="__('Ouvrir :name aux Relais', ['name' => $commune->name])" wire:click="askToggle('{{ $commune->uuid }}')" />
                    </li>
                @endforeach
            </ul>

            @if ($this->communes->hasPages())
                <div class="flex items-center justify-between border-t border-line px-4 py-3 text-[0.8125rem] text-mute">
                    <span>{{ __(':from–:to sur :total', ['from' => $this->communes->firstItem(), 'to' => $this->communes->lastItem(), 'total' => $this->communes->total()]) }}</span>
                    <div class="flex gap-1.5">
                        <x-ui.button size="sm" variant="secondary" wire:click="previousPage" :disabled="$this->communes->onFirstPage()">{{ __('Précédent') }}</x-ui.button>
                        <x-ui.button size="sm" variant="secondary" wire:click="nextPage" :disabled="! $this->communes->hasMorePages()">{{ __('Suivant') }}</x-ui.button>
                    </div>
                </div>
            @endif
        @endif
    </x-console.group>

    <x-console.group :title="__('Contours officiels')" :footer="__('Les contours permettent d’attribuer chaque relais à la commune où il a été filmé. Fichier GeoJSON fourni par REFLET TV.')">
        <div class="flex items-start gap-3.5 p-4">
            <x-console.tile icon="layers" tint="purple" />
            <div class="min-w-0 flex-1">
                <p class="text-[0.9375rem] font-semibold">{{ __('Importer les contours') }}</p>
                <code class="mt-2 block overflow-x-auto rounded-[0.65rem] bg-black/[0.045] px-3 py-2 font-mono text-[0.8125rem] dark:bg-white/[0.06]">php artisan territoire:import-communes communes.geojson --name-field=NOM --region-field=REGION</code>
            </div>
        </div>
    </x-console.group>

    <x-console.confirm
        model="confirmingToggle"
        action="confirmToggle"
        :tone="$this->pending?->is_active ? 'danger' : 'primary'"
        :title="$this->pending?->is_active ? __('Fermer :name ?', ['name' => $this->pending->name]) : __('Ouvrir :name ?', ['name' => $this->pending?->name])"
        :text="$this->pending?->is_active
            ? __('Les nouvelles inscriptions seront suspendues. Les Relais existants ne sont pas affectés.')
            : __('Les habitants pourront candidater pour devenir Relais. Pensez à désigner un coordonnateur.')"
        :confirm-label="$this->pending?->is_active ? __('Fermer') : __('Ouvrir')"
    />
</div>
