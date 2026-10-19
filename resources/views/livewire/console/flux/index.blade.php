@php
    use App\Domain\Contribution\Enums\Urgency;

    $states = ['received', 'in_review', 'complement_requested', 'sensitive', 'validated', 'selected', 'broadcast', 'rejected', 'archived'];
    $active = collect($filters)->filter(fn ($value) => $value !== '')->count();
@endphp

<div class="space-y-8" wire:poll.5s.keep-alive>
    <x-console.page-header
        :eyebrow="__('Pilotage')"
        :title="__('Flux des relais')"
        :subtitle="__('Tous les relais de votre périmètre dès leur envoi, avec leur statut, mis à jour automatiquement.')"
    >
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="map-pin" :href="route('console.map')" wire:navigate>{{ __('Carte') }}</x-ui.button>
            <x-ui.button variant="dark" icon="signal" :href="route('console.newsroom')">{{ __('Salle de rédaction') }}</x-ui.button>
        </x-slot:actions>
    </x-console.page-header>

    <x-console.alerts :alerts="$this->alerts" data-fade />

    <x-console.group :title="trans_choice(':count relais|:count relais', $this->items->total())">
        <x-slot:actions>
            @if ($active > 0)
                <button type="button" wire:click="resetFilters" class="text-[0.8125rem] font-semibold text-tile-blue hover:opacity-80">{{ __('Effacer les filtres') }}</button>
            @endif
            <span class="inline-flex items-center gap-1.5 text-[0.75rem] text-mute"><span class="size-1.5 animate-pulse rounded-full bg-tile-green"></span>{{ __('Temps réel') }}</span>
        </x-slot:actions>

        <div class="space-y-3 border-b border-line p-3">
            <div class="flex flex-wrap items-center gap-3">
                <x-console.search wire:model.live.debounce.300ms="filters.q" :placeholder="__('Titre, description, lieu')" />
                <div class="w-44">
                    <x-console.select name="filters.state" wire:model.live="filters.state" class="h-9! text-[0.875rem]!">
                        <option value="">{{ __('Tous les statuts') }}</option>
                        @foreach ($states as $state)
                            <option value="{{ $state }}">{{ __("relais_items.states.{$state}") }}</option>
                        @endforeach
                    </x-console.select>
                </div>
                <div class="w-40">
                    <x-console.select name="filters.urgency" wire:model.live="filters.urgency" class="h-9! text-[0.875rem]!">
                        <option value="">{{ __('Toute urgence') }}</option>
                        @foreach (Urgency::cases() as $urgency)
                            <option value="{{ $urgency->value }}">{{ $urgency->label() }}</option>
                        @endforeach
                    </x-console.select>
                </div>
                <button type="button" wire:click="$toggle('advanced')" class="press inline-flex h-9 items-center gap-1.5 rounded-[0.65rem] bg-black/[0.055] px-3 text-[0.875rem] font-medium dark:bg-white/[0.08]">
                    <x-ui.icon name="layers" class="size-4" />{{ $advanced ? __('Moins de filtres') : __('Plus de filtres') }}
                </button>
            </div>

            @if ($advanced)
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4" wire:key="advanced-filters">
                    <x-console.select :label="__('Région')" name="filters.region" wire:model.live="filters.region">
                        <option value="">{{ __('Toutes') }}</option>
                        @foreach ($this->regions as $region)<option value="{{ $region->uuid }}">{{ $region->name }}</option>@endforeach
                    </x-console.select>
                    <x-console.select :label="__('Commune')" name="filters.commune" wire:model.live="filters.commune">
                        <option value="">{{ __('Toutes') }}</option>
                        @foreach ($this->communes as $commune)<option value="{{ $commune->uuid }}">{{ $commune->name }}</option>@endforeach
                    </x-console.select>
                    <x-console.select :label="__('Catégorie')" name="filters.category" wire:model.live="filters.category">
                        <option value="">{{ __('Toutes') }}</option>
                        @foreach ($this->categories as $category)<option value="{{ $category->uuid }}">{{ $category->name }}</option>@endforeach
                    </x-console.select>
                    <x-console.select :label="__('Type de média')" name="filters.media" wire:model.live="filters.media">
                        <option value="">{{ __('Tous') }}</option>
                        <option value="photo">{{ __('Photo') }}</option>
                        <option value="video">{{ __('Vidéo') }}</option>
                        <option value="audio">{{ __('Audio') }}</option>
                    </x-console.select>
                    <x-console.input :label="__('Relais (nom ou numéro)')" name="filters.relais" wire:model.live.debounce.400ms="filters.relais" />
                    <x-console.select :label="__('Coordonnateur')" name="filters.coordinator" wire:model.live="filters.coordinator">
                        <option value="">{{ __('Tous') }}</option>
                        @foreach ($this->coordinators as $coordinator)<option value="{{ $coordinator->uuid }}">{{ $coordinator->name }}</option>@endforeach
                    </x-console.select>
                    <x-console.input :label="__('Du')" type="date" name="filters.from" wire:model.live="filters.from" />
                    <x-console.input :label="__('Au')" type="date" name="filters.to" wire:model.live="filters.to" />
                </div>
            @endif
        </div>

        @if ($this->items->isEmpty())
            <x-console.empty icon="inbox" tint="blue" :title="__('Aucun relais')" :text="$active > 0 ? __('Aucun relais ne correspond à ces filtres.') : __('Les relais apparaîtront ici dès leur envoi.')" />
        @else
            <ul wire:loading.class="opacity-50" wire:target="filters" class="transition-opacity duration-300">
                @foreach ($this->items as $relais)
                    <x-console.relais-row :relais="$relais" />
                @endforeach
            </ul>

            @if ($this->items->hasPages())
                <div class="flex items-center justify-between border-t border-line px-4 py-3 text-[0.8125rem] text-mute">
                    <span>{{ __(':from–:to sur :total', ['from' => $this->items->firstItem(), 'to' => $this->items->lastItem(), 'total' => $this->items->total()]) }}</span>
                    <div class="flex gap-1.5">
                        <x-ui.button size="sm" variant="secondary" wire:click="previousPage" :disabled="$this->items->onFirstPage()">{{ __('Précédent') }}</x-ui.button>
                        <x-ui.button size="sm" variant="secondary" wire:click="nextPage" :disabled="! $this->items->hasMorePages()">{{ __('Suivant') }}</x-ui.button>
                    </div>
                </div>
            @endif
        @endif
    </x-console.group>
</div>
