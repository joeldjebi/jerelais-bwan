<div class="space-y-8">
    <x-console.page-header
        :eyebrow="__('Réseau')"
        :title="__('Communes')"
        :subtitle="__('Ouvrez les communes aux Relais au rythme du déploiement : pilote à Abidjan, puis lancement national.')"
    >
        <x-slot:actions>
            <x-ui.button wire:click="openCreate">{{ __('Ajouter une commune') }}</x-ui.button>
        </x-slot:actions>
    </x-console.page-header>

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
                        @if ($editing === $commune->uuid)
                            <form wire:submit="saveEdit" class="flex min-w-0 flex-1 flex-wrap items-center gap-2">
                                <div class="min-w-40 flex-1">
                                    <input type="text" wire:model="editName" aria-label="{{ __('Nom de la commune') }}" maxlength="120" autofocus
                                        class="h-9 w-full rounded-[0.6rem] border-0 bg-black/[0.055] px-3 text-[0.9375rem] focus:bg-surface focus:shadow-[0_0_0_3px_rgb(10_132_255/0.3)] focus:outline-none dark:bg-white/[0.08]">
                                    @error('editName') <p class="mt-1 text-xs font-medium text-status-rejected">{{ $message }}</p> @enderror
                                </div>
                                <select wire:model="editRegion" aria-label="{{ __('Région') }}" class="h-9 rounded-[0.6rem] border-0 bg-black/[0.055] px-3 text-[0.875rem] dark:bg-white/[0.08]">
                                    <option value="">{{ __('Sans région') }}</option>
                                    @foreach ($this->regions as $region)
                                        <option value="{{ $region->uuid }}">{{ $region->name }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="text-[0.875rem] font-semibold text-tile-blue hover:opacity-80">{{ __('Enregistrer') }}</button>
                                <button type="button" wire:click="cancelEdit" class="text-[0.875rem] text-mute hover:text-ink">{{ __('Annuler') }}</button>
                            </form>
                        @else
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-[0.9375rem] font-semibold">{{ $commune->name }}</p>
                                <p class="truncate text-[0.8125rem] text-mute">
                                    {{ $commune->region?->name ?? __('Sans région') }}
                                    · {{ $commune->coordinators_count > 0 ? trans_choice(':count coordonnateur|:count coordonnateurs', $commune->coordinators_count) : __('aucun coordonnateur') }}
                                </p>
                            </div>
                            <button type="button" wire:click="startEdit('{{ $commune->uuid }}')" class="text-[0.8125rem] font-medium text-tile-blue hover:opacity-80">{{ __('Modifier') }}</button>
                        @endif
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
    {{-- Création d'une commune (ADM-20) --}}
    <x-console.slide-over model="creating" :title="__('Nouvelle commune')" :description="__('Elle reste fermée aux Relais tant que vous ne l’ouvrez pas. Importez ensuite son contour pour le routage par GPS.')">
        <form id="create-commune" wire:submit="create" class="space-y-6">
            <x-console.group>
                <div class="space-y-4 p-4">
                    <x-console.input :label="__('Nom de la commune')" name="newName" wire:model="newName" maxlength="120" autocomplete="off" />
                    <x-console.select :label="__('Région')" name="newRegion" wire:model="newRegion">
                        <option value="">{{ __('Sans région') }}</option>
                        @foreach ($this->regions as $region)
                            <option value="{{ $region->uuid }}">{{ $region->name }}</option>
                        @endforeach
                    </x-console.select>
                    <x-console.input :label="__('Code officiel (facultatif)')" name="newCode" wire:model="newCode" maxlength="20" autocomplete="off" :hint="__('Code INS ou de l’administration, s’il existe.')" />
                </div>
            </x-console.group>
        </form>

        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Annuler') }}</x-ui.button>
            <x-ui.button type="submit" form="create-commune" wire:loading.attr="disabled" wire:target="create">{{ __('Créer la commune') }}</x-ui.button>
        </x-slot:footer>
    </x-console.slide-over>
</div>
