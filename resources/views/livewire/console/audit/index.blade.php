@php
    use App\Domain\Audit\Support\AuditPresenter;

    $presenter = $this->presenter();
    $toneTint = ['success' => 'green', 'danger' => 'pink', 'warning' => 'orange', 'accent' => 'brand', 'neutral' => 'gray'];
@endphp

<div class="space-y-8">
    <x-console.page-header
        :eyebrow="__('Administration')"
        :title="__('Journal d’audit')"
        :subtitle="__('Toutes les actions sensibles, horodatées et chaînées. Le journal ne peut être ni modifié ni effacé.')"
    >
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="shield" wire:click="verify" wire:loading.attr="disabled" wire:target="verify">
                <span wire:loading.remove wire:target="verify">{{ __('Vérifier l’intégrité') }}</span>
                <span wire:loading wire:target="verify">{{ __('Vérification…') }}</span>
            </x-ui.button>
        </x-slot:actions>
    </x-console.page-header>

    @if ($integrity)
        <x-console.widget :tone="$integrity['broken_at'] === null ? 'green' : 'ink'" class="flex items-center gap-4">
            <span class="grid size-12 shrink-0 place-items-center rounded-full bg-white/20">
                <x-ui.icon :name="$integrity['broken_at'] === null ? 'check' : 'x'" class="size-6 text-white" stroke="2.5" />
            </span>
            <div>
                <p class="text-[1.0625rem] font-semibold">{{ $integrity['broken_at'] === null ? __('Chaîne d’empreintes intacte') : __('Chaîne rompue') }}</p>
                <p class="text-[0.875rem] text-white/85">
                    {{ $integrity['broken_at'] === null
                        ? __(':count entrées vérifiées : aucune ligne n’a été modifiée ni retirée.', ['count' => $integrity['checked']])
                        : __('La ligne :id ne correspond plus à son empreinte. Alertez immédiatement l’équipe technique.', ['id' => $integrity['broken_at']]) }}
                </p>
            </div>
        </x-console.widget>
    @endif

    <x-console.group :title="trans_choice(':count entrée|:count entrées', $this->entries->total())">
        <div class="flex flex-wrap items-center gap-3 border-b border-line p-3">
            <div class="min-w-[14rem] flex-1">
                <x-console.select name="action" wire:model.live="action" class="h-9! text-[0.875rem]!">
                    <option value="">{{ __('Toutes les actions') }}</option>
                    @foreach (AuditPresenter::actionOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </x-console.select>
            </div>
            <x-console.segmented property="period" :current="$period" :options="['1' => __('24 h'), '7' => __('7 jours'), '30' => __('30 jours'), 'all' => __('Tout')]" />
        </div>

        @if ($this->entries->isEmpty())
            <x-console.empty icon="shield" tint="purple" :title="__('Aucune entrée sur cette période')" :text="__('Élargissez la période ou retirez le filtre.')" />
        @else
            <ul wire:loading.class="opacity-50" wire:target="action,period" class="transition-opacity duration-300" data-stagger>
                @foreach ($this->entries as $entry)
                    <li wire:key="audit-{{ $entry->uuid }}">
                        <button type="button" wire:click="show('{{ $entry->uuid }}')" class="group flex w-full items-center gap-3.5 px-4 py-3 text-left transition-colors duration-200 hover:bg-black/[0.02] dark:hover:bg-white/[0.03]">
                            <x-console.tile :icon="$presenter->icon($entry)" :tint="$toneTint[$presenter->tone($entry)]" />
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-[0.9375rem] font-semibold">{{ $presenter->label($entry) }}</span>
                                <span class="block truncate text-[0.8125rem] text-mute">{{ $entry->actor?->name ?? __('Système ou application') }}@if ($entry->ip_address) · {{ $entry->ip_address }}@endif</span>
                            </span>
                            <span class="hidden text-right text-[0.8125rem] text-mute tabular-nums sm:block">{{ $entry->occurred_at->translatedFormat('j M, H:i') }}</span>
                            <svg class="size-4 text-mute/60 transition-transform duration-500 ease-spring group-hover:translate-x-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
                        </button>
                    </li>
                @endforeach
            </ul>

            @if ($this->entries->hasPages())
                <div class="flex items-center justify-between border-t border-line px-4 py-3 text-[0.8125rem] text-mute">
                    <span>{{ __(':from–:to sur :total', ['from' => $this->entries->firstItem(), 'to' => $this->entries->lastItem(), 'total' => $this->entries->total()]) }}</span>
                    <div class="flex gap-1.5">
                        <x-ui.button size="sm" variant="secondary" wire:click="previousPage" :disabled="$this->entries->onFirstPage()">{{ __('Précédent') }}</x-ui.button>
                        <x-ui.button size="sm" variant="secondary" wire:click="nextPage" :disabled="! $this->entries->hasMorePages()">{{ __('Suivant') }}</x-ui.button>
                    </div>
                </div>
            @endif
        @endif
    </x-console.group>

    <x-console.slide-over model="showing" :title="$this->entry ? $presenter->label($this->entry) : __('Détail')" :description="$this->entry?->occurred_at->translatedFormat('l j F Y à H:i:s')">
        @if ($entry = $this->entry)
            <div class="space-y-6">
                <x-console.group>
                    <ul>
                        @foreach ([
                            __('Auteur') => $entry->actor ? $entry->actor->name.' · '.$entry->actor->email : __('Système ou application'),
                            __('Action') => $entry->action,
                            __('Objet') => $entry->subject_type ? $entry->subject_type.' · '.$entry->subject_id : '—',
                            __('Adresse IP') => $entry->ip_address ?? '—',
                            __('Navigateur') => $entry->user_agent ?? '—',
                        ] as $label => $value)
                            <li class="flex gap-4 px-4 py-3 text-[0.875rem]">
                                <span class="w-24 shrink-0 text-mute">{{ $label }}</span>
                                <span class="min-w-0 flex-1 break-words">{{ $value }}</span>
                            </li>
                        @endforeach
                    </ul>
                </x-console.group>

                @if ($entry->payload)
                    <x-console.group :title="__('Données')">
                        <pre class="overflow-x-auto p-4 font-mono text-xs leading-relaxed">{{ json_encode($entry->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                    </x-console.group>
                @endif

                <x-console.group :title="__('Empreinte')" :footer="__('Chaque entrée est liée à la précédente : toute altération est détectée par la vérification d’intégrité.')">
                    <p class="p-4 font-mono text-[0.6875rem] break-all text-mute">{{ $entry->hash }}</p>
                </x-console.group>
            </div>
        @endif
    </x-console.slide-over>
</div>
