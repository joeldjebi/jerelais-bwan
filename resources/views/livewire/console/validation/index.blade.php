<div class="space-y-8" wire:poll.5s.keep-alive>
    <x-console.page-header
        :eyebrow="__('Pilotage')"
        :title="__('Validation des relais')"
        :subtitle="__('Les relais reçus de votre périmètre, urgents d’abord puis du plus ancien au plus récent. Ouvrir un relais le réserve pour vous pendant 10 minutes.')"
    />

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4" data-bento>
        <x-console.stat :label="__('À valider')" value="" :count="$this->counts['a-valider']" icon="inbox" tint="blue" :hint="__('Reçus ou en vérification')" />
        <x-console.stat :label="__('Urgents')" value="" :count="$this->counts['urgents']" icon="bolt" :tint="$this->counts['urgents'] > 0 ? 'pink' : 'gray'" :hint="__('Délai cible : 30 min')" />
        <x-console.stat :label="__('En retard')" value="" :count="$this->counts['retard']" icon="clock" :tint="$this->counts['retard'] > 0 ? 'orange' : 'gray'" :hint="__('Délai cible dépassé')" />
        <x-console.stat :label="__('Sensibles')" value="" :count="$this->counts['sensibles']" icon="flag" tint="purple" :hint="__('Arbitrage de la rédaction')" />
    </div>

    <x-console.group :title="trans_choice(':count relais|:count relais', $this->items->total())">
        <x-slot:actions>
            <span class="inline-flex items-center gap-1.5 text-[0.75rem] text-mute"><span class="size-1.5 animate-pulse rounded-full bg-tile-green"></span>{{ __('Temps réel') }}</span>
        </x-slot:actions>

        <div class="flex flex-wrap items-center gap-3 border-b border-line p-3">
            <x-console.segmented property="filter" :current="$filter" :options="['a-valider' => __('À valider'), 'complement' => __('Complément'), 'sensibles' => __('Sensibles'), 'valides' => __('Validés'), 'rejetes' => __('Rejetés')]" />
        </div>

        @if ($this->items->isEmpty())
            <x-console.empty icon="inbox" tint="blue" :title="$filter === 'a-valider' ? __('Aucun relais en attente') : __('Aucun relais')" :text="__('Les nouveaux relais de votre périmètre apparaissent ici dès leur réception.')" />
        @else
            <ul>
                @foreach ($this->items as $relais)
                    <x-console.relais-row :relais="$relais" :sla="$filter === 'a-valider'" />
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
