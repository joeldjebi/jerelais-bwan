@php
    $urgencyTone = ['urgent' => 'danger', 'important' => 'warning', 'normal' => 'neutral'];
    $stateTone = ['received' => 'info', 'in_review' => 'accent', 'complement_requested' => 'warning', 'sensitive' => 'warning', 'validated' => 'success', 'selected' => 'success', 'broadcast' => 'success', 'rejected' => 'danger'];
    $kindIcon = ['photo' => 'photo', 'video' => 'video', 'audio' => 'mic'];
@endphp

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
                    @php
                        $media = $relais->medias->first();
                        $thumb = $media?->variants->firstWhere('kind', 'thumbnail');
                        $overdue = $relais->isOverdue();
                        $locked = $relais->isLockedFor(auth()->user());
                    @endphp
                    <li wire:key="relais-{{ $relais->uuid }}">
                        <a href="{{ route('console.validation.show', $relais) }}" wire:navigate class="group flex items-center gap-3.5 px-4 py-3 transition-colors duration-200 hover:bg-black/[0.02] dark:hover:bg-white/[0.03]">
                            <span class="relative grid size-14 shrink-0 place-items-center overflow-hidden rounded-[0.8rem] bg-black/[0.05] dark:bg-white/[0.08]">
                                @if ($thumb)
                                    <img src="{{ route('console.medias.show', [$media, 'thumbnail']) }}" alt="" class="size-full object-cover" loading="lazy">
                                @else
                                    <x-ui.icon :name="$kindIcon[$media?->kind->value ?? 'photo']" class="size-5 text-mute" />
                                @endif
                                @if ($relais->medias->count() > 1)
                                    <span class="absolute right-1 bottom-1 rounded-full bg-black/60 px-1.5 text-[0.625rem] font-semibold text-white">{{ $relais->medias->count() }}</span>
                                @endif
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="flex items-center gap-2">
                                    <span class="truncate text-[0.9375rem] font-semibold">{{ $relais->title ?: __('Sans titre') }}</span>
                                    @if ($relais->urgency->value !== 'normal')<x-console.badge :tone="$urgencyTone[$relais->urgency->value]">{{ $relais->urgency->label() }}</x-console.badge>@endif
                                    @if ($relais->has_minors)<x-console.badge tone="warning">{{ __('Mineurs') }}</x-console.badge>@endif
                                </span>
                                <span class="block truncate text-[0.8125rem] text-mute">
                                    {{ collect([$relais->category?->name, $relais->commune?->name, $relais->relaisProfile?->display_name])->filter()->join(' · ') }}
                                </span>
                            </span>
                            @if ($locked)
                                <span class="hidden items-center gap-1.5 text-[0.75rem] text-mute md:inline-flex"><x-ui.icon name="shield" class="size-3.5" />{{ $relais->lockedBy?->name }}</span>
                            @endif
                            @if ($filter === 'a-valider' && $relais->sla_due_at)
                                <span @class(['hidden w-28 text-right text-[0.8125rem] tabular-nums sm:block', 'font-semibold text-status-rejected' => $overdue, 'text-mute' => ! $overdue])>
                                    {{ $overdue ? __('Retard :d', ['d' => $relais->sla_due_at->diffForHumans(short: true, syntax: \Carbon\CarbonInterface::DIFF_ABSOLUTE)]) : __('Reste :d', ['d' => $relais->sla_due_at->diffForHumans(short: true, syntax: \Carbon\CarbonInterface::DIFF_ABSOLUTE)]) }}
                                </span>
                            @else
                                <x-console.badge :tone="$stateTone[$relais->state->getValue()] ?? 'neutral'" class="hidden sm:inline-flex">{{ $relais->state->label() }}</x-console.badge>
                            @endif
                            <svg class="size-4 text-mute/60 transition-transform duration-500 ease-spring group-hover:translate-x-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
                        </a>
                    </li>
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
