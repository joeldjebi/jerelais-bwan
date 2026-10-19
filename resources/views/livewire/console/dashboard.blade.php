@php
    $presenter = app(\App\Domain\Audit\Support\AuditPresenter::class);
    $firstName = Str::before($this->user->name ?? '', ' ') ?: __('bienvenue');
    $setup = $this->setup;
    $doneCount = collect($setup)->where('done', true)->count();
    $progress = $setup === [] ? 1 : $doneCount / count($setup);
    $circumference = 2 * M_PI * 26;
@endphp

<div class="space-y-8">
    <x-console.page-header
        :eyebrow="ucfirst(now()->translatedFormat('l j F Y'))"
        :title="__('Bonjour, :name.', ['name' => $firstName])"
        :subtitle="$this->isCoordination
            ? __('Voici l’état de votre commune. Les relais à vérifier apparaîtront ici dès leur envoi.')
            : __('Voici l’état du réseau JE RELAIS en un coup d’œil.')"
    >
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="search" x-on:click="$dispatch('palette-open')">{{ __('Aller à…') }}</x-ui.button>
        </x-slot:actions>
    </x-console.page-header>

    @if ($setup !== [] && $doneCount < count($setup))
        <x-console.panel :padded="false">
            <div class="grid gap-0 md:grid-cols-[18rem_1fr]">
                <div class="flex items-center gap-5 border-b border-line p-6 md:border-r md:border-b-0">
                    <svg viewBox="0 0 64 64" class="size-20 shrink-0 -rotate-90" aria-hidden="true">
                        <circle cx="32" cy="32" r="26" fill="none" stroke="var(--line)" stroke-width="6" />
                        <circle cx="32" cy="32" r="26" fill="none" stroke="var(--color-relais-500)" stroke-width="6" stroke-linecap="round"
                            stroke-dasharray="{{ $circumference }}" stroke-dashoffset="{{ $circumference * (1 - $progress) }}"
                            style="transition: stroke-dashoffset 1.2s var(--ease-out-expo)" />
                    </svg>
                    <div>
                        <p class="text-[0.8125rem] font-medium text-mute">{{ __('Mise en service') }}</p>
                        <p class="mt-0.5 font-display text-2xl font-semibold tracking-tight tabular-nums">{{ $doneCount }} / {{ count($setup) }}</p>
                        <p class="text-xs text-mute">{{ __('étapes terminées') }}</p>
                    </div>
                </div>
                <ul class="divide-y divide-line">
                    @foreach ($setup as $step)
                        <li>
                            @php $tag = $step['route'] && ! $step['done'] ? 'a' : 'div'; @endphp
                            <{{ $tag }} @if ($tag === 'a') href="{{ route($step['route']) }}" @if ($step['route'] !== 'console.security') wire:navigate @endif @endif
                                @class(['group flex items-center gap-4 px-6 py-3.5', 'transition-colors duration-300 hover:bg-ink/[0.025]' => $tag === 'a'])>
                                @if ($step['done'])
                                    <span class="grid size-6 shrink-0 place-items-center rounded-full bg-status-validated text-white">
                                        <x-ui.icon name="check" class="size-3.5" stroke="3" />
                                    </span>
                                @else
                                    <span class="size-6 shrink-0 rounded-full ring-2 ring-line ring-inset"></span>
                                @endif
                                <span class="min-w-0 flex-1">
                                    <span @class(['block text-[0.875rem] font-medium', 'text-mute line-through decoration-line' => $step['done']])>{{ $step['label'] }}</span>
                                    @unless ($step['done'])
                                        <span class="block text-xs text-mute">{{ $step['hint'] }}</span>
                                    @endunless
                                </span>
                                @if ($tag === 'a')
                                    <x-ui.icon name="arrow-right" class="size-4 text-mute transition-transform duration-500 ease-out-expo group-hover:translate-x-0.5" />
                                @endif
                            </{{ $tag }}>
                        </li>
                    @endforeach
                </ul>
            </div>
        </x-console.panel>
    @endif

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        @foreach ($this->stats as $stat)
            <x-console.stat :label="$stat['label']" :value="$stat['value']" :icon="$stat['icon']" :hint="$stat['hint']" :tone="$stat['tone']" :href="$stat['route'] ? route($stat['route']) : null" />
        @endforeach
    </div>

    @if ($this->isCoordination)
        <div class="grid gap-6 lg:grid-cols-[1.5fr_1fr]">
            <x-console.panel :title="__('File de validation')" :description="__('Triée par urgence puis par ancienneté.')" :padded="false">
                <x-console.empty icon="inbox" :title="__('Aucun relais en attente')" :text="__('Les relais filmés dans votre commune arriveront ici en temps réel, avec un signal pour les urgents.')" />
            </x-console.panel>

            <x-console.panel :title="__('Mes communes')" :padded="false">
                <ul class="divide-y divide-line">
                    @forelse ($this->user->communes as $commune)
                        <li class="flex items-center gap-3 px-6 py-4">
                            <span class="grid size-9 place-items-center rounded-xl bg-relais-500/12 text-relais-600"><x-ui.icon name="map-pin" class="size-4" /></span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-[0.875rem] font-semibold">{{ $commune->name }}</p>
                                <p class="truncate text-xs text-mute">{{ $commune->region?->name }}</p>
                            </div>
                            <x-console.badge :tone="$commune->pivot->assignment === 'titulaire' ? 'accent' : 'neutral'">{{ $commune->pivot->assignment === 'titulaire' ? __('Titulaire') : __('Suppléant') }}</x-console.badge>
                        </li>
                    @empty
                        <li><x-console.empty icon="map-pin" :title="__('Aucune commune rattachée')" :text="__('Un administrateur doit vous rattacher à votre commune.')" /></li>
                    @endforelse
                </ul>
            </x-console.panel>
        </div>
    @else
        <div class="grid gap-6 lg:grid-cols-[1.5fr_1fr]">
            <x-console.panel :title="__('Activité récente')" :description="__('Dernières actions tracées sur la plateforme.')" :padded="false">
                <x-slot:actions>
                    @can(\App\Domain\Access\Enums\Permission::AuditView->value)
                        <a href="{{ route('console.audit') }}" wire:navigate class="text-[0.8125rem] font-medium text-relais-600 hover:underline">{{ __('Tout voir') }}</a>
                    @endcan
                </x-slot:actions>
                @if ($this->activity->isEmpty())
                    <x-console.empty icon="shield" :title="__('Rien à signaler')" :text="__('Les connexions, décisions et modifications importantes s’afficheront ici.')" />
                @else
                    <ol class="relative px-6 py-4">
                        <span class="absolute top-6 bottom-6 left-[2.35rem] w-px bg-line" aria-hidden="true"></span>
                        @foreach ($this->activity as $entry)
                            <li class="relative flex items-start gap-4 py-2.5">
                                <span @class([
                                    'relative z-10 grid size-7 shrink-0 place-items-center rounded-full ring-4 ring-surface',
                                    'bg-status-validated/15 text-status-validated' => $presenter->tone($entry) === 'success',
                                    'bg-status-rejected/12 text-status-rejected' => $presenter->tone($entry) === 'danger',
                                    'bg-status-pending/15 text-[#b77400]' => $presenter->tone($entry) === 'warning',
                                    'bg-relais-500/12 text-relais-600' => $presenter->tone($entry) === 'accent',
                                    'bg-ink/[0.06] text-ink-soft' => $presenter->tone($entry) === 'neutral',
                                ])>
                                    <x-ui.icon :name="$presenter->icon($entry)" class="size-3.5" />
                                </span>
                                <div class="min-w-0 flex-1 pt-0.5">
                                    <p class="text-[0.875rem] font-medium">{{ $presenter->label($entry) }}</p>
                                    <p class="text-xs text-mute">{{ $entry->actor?->name ?? __('Système ou application') }} · {{ $entry->occurred_at->diffForHumans() }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </x-console.panel>

            <x-console.panel :title="__('Prochainement')" :description="__('Modules en cours de construction.')" :padded="false">
                <ul class="divide-y divide-line">
                    @foreach ([
                        ['inbox', __('Flux des relais en temps réel'), __('Étape 3')],
                        ['map-pin', __('Carte interactive du réseau'), __('Étape 4')],
                        ['bolt', __('Appels à relais'), __('Étape 4')],
                        ['chart', __('Rapports et indicateurs'), __('Étape 5')],
                    ] as [$icon, $label, $when])
                        <li class="flex items-center gap-3 px-6 py-3.5">
                            <x-ui.icon :name="$icon" class="size-4 text-mute" />
                            <span class="flex-1 text-[0.875rem]">{{ $label }}</span>
                            <x-console.badge>{{ $when }}</x-console.badge>
                        </li>
                    @endforeach
                </ul>
            </x-console.panel>
        </div>
    @endif
</div>
