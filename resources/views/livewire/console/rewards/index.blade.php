@php
    $labels = ['credited' => ['Rémunéré', 'success'], 'amended' => ['Montant corrigé', 'success'], 'declined' => ['Pas de rémunération', 'neutral'], 'cancelled' => ['Annulé', 'warning']];
@endphp

<div class="space-y-8">
    <x-console.page-header
        :eyebrow="__('Réseau')"
        :title="__('Rémunérations')"
        :subtitle="__('Décidez, relais par relais, de rémunérer ou non un relais validé, et du montant. La somme est créditée sur le portefeuille du Relais, qui la retire ensuite par Mobile Money.')"
    />

    <div class="grid grid-cols-2 gap-4" data-bento>
        <x-console.stat :label="__('À décider')" value="" :count="$this->counts['pending']" icon="clock" :tint="$this->counts['pending'] > 0 ? 'orange' : 'gray'" :hint="__('Relais validés sans décision')" />
        <x-console.stat :label="__('Décidés')" value="" :count="$this->counts['decided']" icon="check" tint="green" :hint="__('Rémunérés ou non')" />
    </div>

    <x-console.group :title="trans_choice(':count relais|:count relais', $this->items->total())" :footer="__('Ouvrez un relais pour décider : bloc « Rémunération » de sa fiche.')">
        <div class="flex flex-wrap items-center gap-3 border-b border-line p-3">
            <x-console.segmented property="tab" :current="$tab" :options="['a-decider' => __('À décider'), 'decides' => __('Décidés')]" />
        </div>
        @if ($this->items->isEmpty())
            <x-console.empty icon="cart" tint="green" :title="$tab === 'a-decider' ? __('Aucun relais à décider') : __('Aucune décision pour le moment')" :text="__('Les relais validés de votre périmètre apparaissent ici.')" />
        @else
            <ul>
                @foreach ($this->items as $relais)
                    <x-console.relais-row :relais="$relais">
                        @if ($tab === 'decides' && $relais->last_decision)
                            @php [$label, $tone] = $labels[$relais->last_decision] ?? ['—', 'neutral']; @endphp
                            <div class="flex shrink-0 items-center gap-2 pr-4">
                                @if (in_array($relais->last_decision, ['credited', 'amended'], true) && $relais->last_amount)
                                    <span class="text-[0.9375rem] font-semibold tabular-nums">{{ number_format((int) $relais->last_amount, 0, ',', ' ') }} FCFA</span>
                                @endif
                                <x-console.badge :tone="$tone">{{ __($label) }}</x-console.badge>
                            </div>
                        @endif
                    </x-console.relais-row>
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
