@php
    $presenter = app(\App\Domain\Audit\Support\AuditPresenter::class);
    $firstName = Str::before($this->user->name ?? '', ' ') ?: __('bienvenue');
    $setup = $this->setup;
    $toneTint = ['success' => 'green', 'danger' => 'pink', 'warning' => 'orange', 'accent' => 'brand', 'neutral' => 'gray'];
@endphp

<div class="space-y-6">
    <x-console.page-header
        :eyebrow="ucfirst(now()->translatedFormat('l j F'))"
        :title="__('Bonjour, :name', ['name' => $firstName])"
        :subtitle="$this->isCoordination
            ? __('Voici l’état de votre commune. Les relais à vérifier apparaîtront ici dès leur envoi.')
            : __('Voici l’état du réseau JE RELAIS.')"
    />

    @if ($this->isCoordination)
        <div class="grid auto-rows-[minmax(10.5rem,auto)] grid-cols-2 gap-4 lg:grid-cols-4" data-bento>
            @php $commune = $this->user->communes->first(); @endphp
            <x-console.widget tone="orange" class="col-span-2 flex flex-col justify-between">
                <div class="flex items-center gap-2 text-[0.8125rem] font-semibold text-white/80">
                    <x-ui.icon name="map-pin" class="size-4" />{{ __('Ma commune') }}
                </div>
                <div>
                    <p class="text-[2.75rem] leading-none font-bold tracking-[-0.035em]">{{ $commune?->name ?? __('Aucune') }}</p>
                    <p class="mt-2 text-[0.875rem] text-white/80">{{ $commune?->region?->name }} · {{ $commune?->pivot->assignment === 'suppleant' ? __('Suppléant') : __('Titulaire') }}</p>
                </div>
            </x-console.widget>

            <x-console.widget tone="ink" class="col-span-2 flex items-center">
                <x-console.clock />
            </x-console.widget>

            @foreach ($this->stats as $stat)
                <x-console.stat :label="$stat['label']" :value="$stat['value']" :count="$stat['count']" :suffix="$stat['suffix']" :icon="$stat['icon']" :hint="$stat['hint']" :tone="$stat['tone']" :href="$stat['route'] && Route::has($stat['route']) ? route($stat['route']) : null" />
            @endforeach

            <x-console.panel :title="__('File de validation')" :description="__('Triée par urgence puis par ancienneté')" icon="inbox" tint="orange" :padded="false" class="col-span-2 lg:col-span-4">
                @if ($this->queue->isEmpty())
                    <x-console.empty icon="check" tint="green" :title="__('Tout est à jour')" :text="__('Aucun relais en attente. Les relais filmés dans votre commune arriveront ici en temps réel, avec un signal pour les urgents.')" />
                @else
                    <ul>
                        @foreach ($this->queue as $relais)
                            <x-console.relais-row :relais="$relais" :sla="true" />
                        @endforeach
                    </ul>
                @endif
            </x-console.panel>

            {{-- Activité de la commune sur 30 jours (COO-22) --}}
            <x-console.widget class="col-span-2 flex flex-col justify-between">
                <p class="text-[0.8125rem] font-semibold text-mute">{{ __('30 derniers jours') }}</p>
                <div class="grid grid-cols-4 gap-2 text-center">
                    @foreach ([__('Reçus') => $this->communeStats['received'], __('Validés') => $this->communeStats['validated'], __('Rejetés') => $this->communeStats['rejected'], __('Diffusés') => $this->communeStats['broadcast']] as $label => $value)
                        <div><p class="text-[1.875rem] leading-none font-bold tabular-nums" data-count="{{ $value }}">{{ $value }}</p><p class="mt-1.5 text-[0.75rem] text-mute">{{ $label }}</p></div>
                    @endforeach
                </div>
            </x-console.widget>

            <x-console.panel :title="__('Couverture par quartier')" :description="__('Relais actifs par quartier')" icon="map-pin" tint="blue" :padded="false" class="col-span-2">
                @if ($this->coverage === [])
                    <x-console.empty icon="map-pin" tint="blue" :title="__('Aucun Relais actif')" :text="__('Acceptez des candidatures pour couvrir votre commune.')" />
                @else
                    @php $max = max(array_column($this->coverage, 'relais')); @endphp
                    <ul class="space-y-2.5 px-4 py-3">
                        @foreach ($this->coverage as $area)
                            <li class="text-[0.875rem]">
                                <div class="flex justify-between"><span>{{ $area['name'] }}</span><span class="font-semibold tabular-nums">{{ $area['relais'] }}</span></div>
                                <div class="mt-1 h-1.5 rounded-full bg-black/[0.06] dark:bg-white/[0.08]"><div class="h-full rounded-full bg-tile-blue" style="width: {{ round($area['relais'] / max(1, $max) * 100) }}%"></div></div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-console.panel>
        </div>
    @else
        <div class="grid auto-rows-[minmax(10.5rem,auto)] grid-cols-2 gap-4 lg:grid-cols-4" data-bento>
            {{-- Anneaux d'activité --}}
            <x-console.widget class="col-span-2 row-span-2 flex flex-col">
                <div class="flex items-center gap-2.5">
                    <x-console.tile :icon="$setup !== [] ? 'bolt' : 'signal'" :tint="$setup !== [] ? 'orange' : 'green'" size="sm" />
                    <p class="text-[0.8125rem] font-semibold text-mute">{{ $setup !== [] ? __('Mise en service') : __('Couverture du réseau') }}</p>
                </div>
                <div class="mt-4 flex flex-1 flex-col items-center gap-6 sm:flex-row">
                    <svg viewBox="0 0 120 120" class="size-44 shrink-0 -rotate-90" aria-hidden="true">
                        @foreach ($this->rings as $index => $ring)
                            @php
                                $radius = 52 - $index * 15;
                                $circumference = round(2 * M_PI * $radius, 2);
                                $offset = round($circumference * (1 - max(0.02, $ring['ratio'])), 2);
                            @endphp
                            <circle cx="60" cy="60" r="{{ $radius }}" fill="none" stroke="{{ $ring['color'] }}" stroke-opacity="0.18" stroke-width="12" />
                            <circle cx="60" cy="60" r="{{ $radius }}" fill="none" stroke="{{ $ring['color'] }}" stroke-width="12" stroke-linecap="round"
                                stroke-dasharray="{{ $circumference }}" stroke-dashoffset="{{ $offset }}"
                                data-ring data-circumference="{{ $circumference }}" data-offset="{{ $offset }}" />
                        @endforeach
                    </svg>
                    <ul class="w-full space-y-3">
                        @foreach ($this->rings as $ring)
                            <li>
                                <p class="text-[0.8125rem] font-semibold" style="color: {{ $ring['color'] }}">{{ $ring['label'] }}</p>
                                <p class="text-[1.625rem] leading-tight font-bold tracking-[-0.02em] tabular-nums">{{ $ring['value'] }}</p>
                            </li>
                        @endforeach
                    </ul>
                </div>
                @if ($setup !== [])
                    @php $next = collect($setup)->firstWhere('done', false); @endphp
                    @if ($next)
                        <a href="{{ $next['route'] ? route($next['route']) : '#' }}" @if ($next['route'] && $next['route'] !== 'console.security') wire:navigate @endif
                            class="group/next mt-5 flex items-center gap-3 rounded-2xl bg-black/[0.035] px-4 py-3 transition-colors duration-300 hover:bg-black/[0.06] dark:bg-white/[0.06]">
                            <span class="text-[0.75rem] font-semibold text-mute">{{ __('Prochaine étape') }}</span>
                            <span class="flex-1 truncate text-[0.875rem] font-semibold">{{ $next['label'] }}</span>
                            <svg class="size-4 text-mute transition-transform duration-500 ease-spring group-hover/next:translate-x-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
                        </a>
                    @endif
                @endif
            </x-console.widget>

            {{-- Horloge --}}
            <x-console.widget tone="ink" class="col-span-2 flex items-center">
                <x-console.clock />
            </x-console.widget>

            {{-- Communes (widget orange plein) --}}
            @php $communes = $this->stats[0]; @endphp
            <x-console.widget tone="orange" :href="$communes['route'] ? route($communes['route']) : null" class="flex flex-col justify-between">
                <div class="flex items-center gap-2 text-[0.8125rem] font-semibold text-white/85">
                    <x-ui.icon name="map-pin" class="size-4" />{{ __('Communes') }}
                </div>
                <div>
                    <p class="text-[2.75rem] leading-none font-bold tracking-[-0.035em] tabular-nums"><span data-count="{{ $communes['count'] }}">{{ $communes['count'] }}</span><span class="text-[1.5rem] text-white/70">{{ $communes['suffix'] }}</span></p>
                    <p class="mt-1.5 text-[0.8125rem] text-white/85">{{ __('ouvertes aux Relais') }}</p>
                </div>
            </x-console.widget>

            @foreach (array_slice($this->stats, 1, 1) as $stat)
                <x-console.stat :label="$stat['label']" :value="$stat['value']" :count="$stat['count']" :suffix="$stat['suffix']" :icon="$stat['icon']" :hint="$stat['hint']" tint="green" :href="$stat['route'] ? route($stat['route']) : null" />
            @endforeach

            {{-- Carte du réseau --}}
            <x-console.widget tone="navy" class="col-span-2 row-span-2 flex flex-col">
                <div class="flex items-center justify-between">
                    <p class="flex items-center gap-2 text-[0.8125rem] font-semibold text-white/80"><x-ui.icon name="signal" class="size-4 text-relais-400" />{{ __('Réseau JE RELAIS') }}</p>
                    <p class="text-[0.75rem] text-white/50">{{ __('Côte d’Ivoire') }}</p>
                </div>
                <div class="mx-auto mt-2 w-full max-w-[22rem] flex-1">
                    @include('public.partials.carte-civ')
                </div>
            </x-console.widget>

            @foreach (array_slice($this->stats, 2) as $stat)
                <x-console.stat :label="$stat['label']" :value="$stat['value']" :count="$stat['count']" :suffix="$stat['suffix']" :icon="$stat['icon']" :hint="$stat['hint']" :tint="$loop->first ? 'blue' : 'purple'" :href="$stat['route'] ? route($stat['route']) : null" />
            @endforeach

            {{-- Activité (notifications) --}}
            <x-console.panel :title="__('Activité')" :description="__('Dernières actions tracées')" icon="shield" tint="purple" :padded="false" class="col-span-2 lg:col-span-4">
                <x-slot:actions>
                    @can(\App\Domain\Access\Enums\Permission::AuditView->value)
                        <a href="{{ route('console.audit') }}" wire:navigate class="text-[0.875rem] font-semibold text-relais-600 hover:underline dark:text-relais-400">{{ __('Tout afficher') }}</a>
                    @endcan
                </x-slot:actions>
                @if ($this->activity->isEmpty())
                    <x-console.empty icon="shield" tint="purple" :title="__('Rien à signaler')" :text="__('Les connexions, décisions et modifications importantes s’afficheront ici.')" />
                @else
                    <ul class="grid gap-2 px-5 pb-5 md:grid-cols-2" data-stagger>
                        @foreach ($this->activity as $entry)
                            <li class="flex items-center gap-3 rounded-2xl bg-black/[0.03] px-3.5 py-3 transition-colors duration-300 hover:bg-black/[0.05] dark:bg-white/[0.05]">
                                <x-console.tile :icon="$presenter->icon($entry)" :tint="$toneTint[$presenter->tone($entry)]" />
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-[0.875rem] font-semibold">{{ $presenter->label($entry) }}</p>
                                    <p class="truncate text-[0.8125rem] text-mute">{{ $entry->actor?->name ?? __('Système ou application') }}</p>
                                </div>
                                <time class="shrink-0 text-[0.75rem] text-mute" datetime="{{ $entry->occurred_at->toIso8601String() }}">{{ $entry->occurred_at->diffForHumans(short: true) }}</time>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-console.panel>
        </div>
    @endif
</div>
