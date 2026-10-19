<x-layouts.public :title="__('Le réseau')" :description="__('Le réseau des Relais de REFLET TV, commune par commune.')">
    <section class="bg-white px-5 pt-32 pb-16 sm:pt-40">
        <div class="mx-auto max-w-[1024px]">
            <p class="text-[0.9375rem] font-medium text-relais-600" data-anim>{{ __('Le réseau') }}</p>
            <h1 class="mt-3 text-[clamp(2.75rem,7vw,5rem)] leading-[1] font-semibold tracking-[-0.045em]" data-anim>{{ __('Partout où il se passe quelque chose.') }}</h1>
            <div class="mt-12 grid grid-cols-2 gap-6 sm:grid-cols-3" data-anim>
                <div><p class="text-[clamp(3rem,7vw,4.5rem)] leading-none font-semibold tracking-[-0.04em] tabular-nums">{{ $total }}</p><p class="mt-2 text-graphite">{{ __('Relais actifs') }}</p></div>
                <div><p class="text-[clamp(3rem,7vw,4.5rem)] leading-none font-semibold tracking-[-0.04em] tabular-nums">{{ $communes->where('relais', '>', 0)->count() }}</p><p class="mt-2 text-graphite">{{ __('Communes couvertes') }}</p></div>
                <div><p class="text-[clamp(3rem,7vw,4.5rem)] leading-none font-semibold tracking-[-0.04em] tabular-nums">{{ $broadcast }}</p><p class="mt-2 text-graphite">{{ __('Relais diffusés') }}</p></div>
            </div>
        </div>
    </section>

    <section class="bg-mist px-5 py-20">
        <div class="mx-auto max-w-[1024px]">
            <h2 class="text-[clamp(2rem,4vw,2.75rem)] font-semibold tracking-[-0.035em]" data-anim>{{ __('Commune par commune') }}</h2>
            <p class="mt-2 text-graphite" data-anim>{{ __('Seul le nombre de Relais par commune est publié : jamais d’identité, jamais de position.') }}</p>
            @php $max = max(1, (int) $communes->max('relais')); @endphp
            <ul class="mt-10 grid gap-3 sm:grid-cols-2">
                @foreach ($communes as $commune)
                    <li class="rounded-2xl bg-white p-5" data-anim>
                        <div class="flex items-baseline justify-between gap-3">
                            <span class="text-[1.0625rem] font-semibold">{{ $commune['name'] }}</span>
                            <span class="text-[1.25rem] font-semibold tabular-nums">{{ $commune['relais'] }}</span>
                        </div>
                        <p class="text-[0.8125rem] text-graphite">{{ $commune['region'] }}</p>
                        <div class="mt-3 h-1.5 rounded-full bg-black/[0.06]"><div class="h-full rounded-full bg-relais-500" style="width: {{ round($commune['relais'] / $max * 100) }}%"></div></div>
                    </li>
                @endforeach
            </ul>
            <x-ui.button :href="route('relais.join')" size="lg" icon-trailing="arrow-right" class="mt-12">{{ __('Rejoindre le réseau') }}</x-ui.button>
        </div>
    </section>
</x-layouts.public>
