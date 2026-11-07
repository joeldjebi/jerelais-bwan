<div class="space-y-8" wire:poll.10s.keep-alive>
    <x-console.page-header
        :eyebrow="__('Rédaction')"
        :title="__('Exploitation')"
        :subtitle="__('Des relais validés à l’antenne : arbitrez les sujets sensibles, sélectionnez, affectez, téléchargez en qualité d’origine et marquez les diffusions.')"
    />

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4" data-bento>
        <x-console.stat :label="__('À arbitrer')" value="" :count="$this->counts['a-arbitrer']" icon="flag" :tint="$this->counts['a-arbitrer'] > 0 ? 'orange' : 'gray'" :hint="__('Sujets sensibles')" />
        <x-console.stat :label="__('À exploiter')" value="" :count="$this->counts['a-exploiter']" icon="check" tint="green" :hint="__('Validés par les coordonnateurs')" />
        <x-console.stat :label="__('Sélectionnés')" value="" :count="$this->counts['selection']" icon="sparkles" tint="purple" :hint="__('En préparation')" />
        <x-console.stat :label="__('Diffusés')" value="" :count="$this->counts['diffuses']" icon="signal" tint="brand" :hint="__('Passés à l’antenne')" />
    </div>

    <x-console.group :title="trans_choice(':count relais|:count relais', $this->items->total())">
        <div class="flex flex-wrap items-center gap-3 border-b border-line p-3">
            <x-console.segmented property="tab" :current="$tab" :options="['a-arbitrer' => __('À arbitrer'), 'a-exploiter' => __('À exploiter'), 'selection' => __('Sélection'), 'diffuses' => __('Diffusés')]" />
            @if ($tab === 'selection')
                <label class="ml-auto inline-flex items-center gap-2 text-[0.875rem]">
                    <input type="checkbox" wire:model.live="mine" class="ios-check">{{ __('Affectés à moi') }}
                </label>
            @endif
        </div>

        @if ($this->items->isEmpty())
            <x-console.empty icon="sparkles" tint="purple" :title="__('Rien pour l’instant')" :text="__('Les relais apparaissent ici dès qu’ils sont validés ou transmis par un coordonnateur.')" />
        @else
            <ul>
                @foreach ($this->items as $relais)
                    <x-console.relais-row :relais="$relais" />
                @endforeach
            </ul>

            @if ($this->items->hasPages())
                <div class="flex items-center justify-end gap-1.5 border-t border-line px-4 py-3">
                    <x-ui.button size="sm" variant="secondary" wire:click="previousPage" :disabled="$this->items->onFirstPage()">{{ __('Précédent') }}</x-ui.button>
                    <x-ui.button size="sm" variant="secondary" wire:click="nextPage" :disabled="! $this->items->hasMorePages()">{{ __('Suivant') }}</x-ui.button>
                </div>
            @endif
        @endif
    </x-console.group>
</div>
