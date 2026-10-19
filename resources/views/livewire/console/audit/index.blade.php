@php
    use App\Domain\Audit\Support\AuditPresenter;

    $presenter = $this->presenter();
    $toneClasses = [
        'success' => 'bg-status-validated/15 text-status-validated',
        'danger' => 'bg-status-rejected/12 text-status-rejected',
        'warning' => 'bg-status-pending/15 text-[#b77400]',
        'accent' => 'bg-relais-500/12 text-relais-600',
        'neutral' => 'bg-ink/[0.06] text-ink-soft',
    ];
@endphp

<div class="space-y-8">
    <x-console.page-header
        index="03"
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
        <div @class([
            'flex items-center gap-4 rounded-[1.25rem] p-5 ring-1',
            'bg-status-validated/[0.07] ring-status-validated/25' => $integrity['broken_at'] === null,
            'bg-status-rejected/[0.07] ring-status-rejected/25' => $integrity['broken_at'] !== null,
        ])>
            <span @class(['grid size-10 place-items-center rounded-full text-white', 'bg-status-validated' => $integrity['broken_at'] === null, 'bg-status-rejected' => $integrity['broken_at'] !== null])>
                <x-ui.icon :name="$integrity['broken_at'] === null ? 'check' : 'x'" class="size-5" stroke="2.5" />
            </span>
            <div>
                <p class="font-semibold">{{ $integrity['broken_at'] === null ? __('Chaîne d’empreintes intacte') : __('Chaîne rompue') }}</p>
                <p class="text-[0.8125rem] text-mute">
                    {{ $integrity['broken_at'] === null
                        ? __(':count entrées vérifiées : aucune ligne n’a été modifiée ni retirée.', ['count' => $integrity['checked']])
                        : __('La ligne :id ne correspond plus à son empreinte. Alertez immédiatement l’équipe technique.', ['id' => $integrity['broken_at']]) }}
                </p>
            </div>
        </div>
    @endif

    <x-console.panel :padded="false">
        <div class="flex flex-wrap items-center gap-3 border-b border-line p-4">
            <div class="min-w-[14rem] flex-1">
                <x-console.select name="action" wire:model.live="action" class="h-10! text-[0.875rem]!">
                    <option value="">{{ __('Toutes les actions') }}</option>
                    @foreach (AuditPresenter::actionOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </x-console.select>
            </div>
            <div class="flex gap-1 rounded-xl bg-ink/[0.05] p-1 text-[0.8125rem] font-medium">
                @foreach (['1' => __('24 h'), '7' => __('7 jours'), '30' => __('30 jours'), 'all' => __('Tout')] as $value => $label)
                    <button type="button" wire:click="$set('period', '{{ $value }}')"
                        @class(['rounded-lg px-3 py-1.5 transition-colors duration-300', 'bg-surface text-ink shadow-sm' => $period === (string) $value, 'text-mute hover:text-ink' => $period !== (string) $value])>{{ $label }}</button>
                @endforeach
            </div>
        </div>

        @if ($this->entries->isEmpty())
            <x-console.empty icon="shield" :title="__('Aucune entrée sur cette période')" :text="__('Élargissez la période ou retirez le filtre.')" />
        @else
            <ul class="divide-y divide-line" wire:loading.class="opacity-60" wire:target="action,period" data-stagger>
                @foreach ($this->entries as $entry)
                    <li wire:key="audit-{{ $entry->uuid }}">
                        <button type="button" wire:click="show('{{ $entry->uuid }}')" class="group flex w-full items-center gap-4 px-6 py-3.5 text-left transition-colors duration-200 hover:bg-ink/[0.02]">
                            <span class="grid size-8 shrink-0 place-items-center rounded-full {{ $toneClasses[$presenter->tone($entry)] }}">
                                <x-ui.icon :name="$presenter->icon($entry)" class="size-4" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-[0.875rem] font-medium">{{ $presenter->label($entry) }}</span>
                                <span class="block truncate text-xs text-mute">{{ $entry->actor?->name ?? __('Système ou application') }}@if ($entry->ip_address) · {{ $entry->ip_address }}@endif</span>
                            </span>
                            <span class="hidden text-right text-xs text-mute tabular-nums sm:block">
                                {{ $entry->occurred_at->translatedFormat('j M, H:i') }}
                                <span class="block">{{ $entry->occurred_at->diffForHumans() }}</span>
                            </span>
                            <x-ui.icon name="arrow-right" class="size-4 text-mute opacity-0 transition-opacity duration-300 group-hover:opacity-100" />
                        </button>
                    </li>
                @endforeach
            </ul>

            @if ($this->entries->hasPages())
                <div class="flex items-center justify-between border-t border-line px-6 py-3 text-[0.8125rem] text-mute">
                    <span>{{ __(':from–:to sur :total', ['from' => $this->entries->firstItem(), 'to' => $this->entries->lastItem(), 'total' => $this->entries->total()]) }}</span>
                    <div class="flex gap-1">
                        <x-ui.button size="sm" variant="secondary" wire:click="previousPage" :disabled="$this->entries->onFirstPage()">{{ __('Précédent') }}</x-ui.button>
                        <x-ui.button size="sm" variant="secondary" wire:click="nextPage" :disabled="! $this->entries->hasMorePages()">{{ __('Suivant') }}</x-ui.button>
                    </div>
                </div>
            @endif
        @endif
    </x-console.panel>

    <x-console.slide-over model="showing" :title="$this->entry ? $presenter->label($this->entry) : __('Détail')" :description="$this->entry?->occurred_at->translatedFormat('l j F Y à H:i:s')">
        @if ($entry = $this->entry)
            <dl class="divide-y divide-line text-[0.875rem]">
                @foreach ([
                    __('Auteur') => $entry->actor ? $entry->actor->name.' · '.$entry->actor->email : __('Système ou application'),
                    __('Action') => $entry->action,
                    __('Objet') => $entry->subject_type ? $entry->subject_type.' · '.$entry->subject_id : '—',
                    __('Adresse IP') => $entry->ip_address ?? '—',
                    __('Navigateur') => $entry->user_agent ?? '—',
                ] as $label => $value)
                    <div class="grid grid-cols-[7rem_1fr] gap-3 py-3">
                        <dt class="text-mute">{{ $label }}</dt>
                        <dd class="break-words">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>

            @if ($entry->payload)
                <p class="mt-6 text-[0.8125rem] font-medium">{{ __('Données') }}</p>
                <pre class="mt-2 overflow-x-auto rounded-xl bg-ink/[0.04] p-4 font-mono text-xs leading-relaxed">{{ json_encode($entry->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
            @endif

            <p class="mt-6 text-[0.8125rem] font-medium">{{ __('Empreinte') }}</p>
            <p class="mt-2 rounded-xl bg-ink/[0.04] p-4 font-mono text-[0.6875rem] break-all text-mute">{{ $entry->hash }}</p>
        @endif
    </x-console.slide-over>
</div>
