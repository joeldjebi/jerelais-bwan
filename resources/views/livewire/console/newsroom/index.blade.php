@php
    $stateColor = ['received' => 'bg-[#0a84ff]', 'in_review' => 'bg-relais-500', 'validated' => 'bg-tile-green', 'selected' => 'bg-tile-green', 'broadcast' => 'bg-tile-purple'];
    $hero = $this->latest->first();
    $heroMedia = $hero?->medias->first();
@endphp

<div class="flex h-full flex-col gap-6 p-8" wire:poll.5s.keep-alive>
    <header class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <x-ui.logo class="text-white" />
            <span class="text-[0.875rem] font-medium tracking-[0.18em] text-white/50 uppercase">{{ __('Salle de rédaction') }}</span>
        </div>
        <div class="flex items-center gap-6">
            <x-console.alerts :alerts="$this->alerts" />
            <span class="font-mono text-[2rem] font-semibold tabular-nums" data-clock-digital>{{ now()->timezone('Africa/Abidjan')->format('H:i') }}</span>
            <a href="{{ route('console.flux') }}" class="rounded-full bg-white/10 px-4 py-2 text-[0.875rem] font-medium hover:bg-white/20">{{ __('Quitter') }}</a>
        </div>
    </header>

    <div class="grid min-h-0 flex-1 grid-cols-[1fr_24rem] gap-6">
        <section class="grid min-h-0 grid-rows-[1.4fr_1fr] gap-6">
            @if ($hero)
                <a href="{{ route('console.validation.show', $hero) }}" class="relative overflow-hidden rounded-[2rem] bg-[#141416]">
                    @if ($heroMedia && $heroMedia->variants->firstWhere('kind', 'thumbnail'))
                        <img src="{{ route('console.medias.show', [$heroMedia, 'thumbnail']) }}" alt="" class="absolute inset-0 size-full object-cover opacity-80">
                    @endif
                    <div class="absolute inset-0 bg-black/40"></div>
                    <div class="absolute inset-x-0 bottom-0 p-8">
                        <span class="inline-flex items-center gap-2 rounded-full {{ $stateColor[$hero->state->getValue()] ?? 'bg-white/20' }} px-3 py-1 text-[0.875rem] font-semibold">{{ $hero->state->label() }}</span>
                        <h1 class="mt-4 text-[clamp(2rem,3.4vw,3.5rem)] leading-[1.05] font-bold tracking-[-0.03em]">{{ $hero->title ?: __('Sans titre') }}</h1>
                        <p class="mt-2 text-[1.25rem] text-white/75">{{ collect([$hero->category?->name, $hero->commune?->name, $hero->received_at?->diffForHumans()])->filter()->join(' · ') }}</p>
                    </div>
                </a>
            @else
                <div class="grid place-items-center rounded-[2rem] bg-[#141416] text-[1.5rem] text-white/50">{{ __('En attente des premiers relais') }}</div>
            @endif

            <div class="grid min-h-0 grid-cols-4 gap-4">
                @foreach ($this->latest->skip(1)->take(8) as $relais)
                    @php $media = $relais->medias->first(); @endphp
                    <a href="{{ route('console.validation.show', $relais) }}" class="relative overflow-hidden rounded-[1.4rem] bg-[#141416]" wire:key="nr-{{ $relais->uuid }}">
                        @if ($media && $media->variants->firstWhere('kind', 'thumbnail'))
                            <img src="{{ route('console.medias.show', [$media, 'thumbnail']) }}" alt="" class="absolute inset-0 size-full object-cover opacity-70">
                        @endif
                        <div class="absolute inset-0 bg-black/55"></div>
                        <div class="absolute inset-x-0 bottom-0 p-4">
                            <span class="size-2 rounded-full {{ $stateColor[$relais->state->getValue()] ?? 'bg-white/40' }} inline-block"></span>
                            <p class="mt-1 line-clamp-2 text-[1rem] leading-snug font-semibold">{{ $relais->title ?: __('Sans titre') }}</p>
                            <p class="text-[0.8125rem] text-white/60">{{ $relais->commune?->name }} · {{ $relais->received_at?->diffForHumans(short: true) }}</p>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>

        <aside class="flex min-h-0 flex-col gap-6">
            <div class="grid grid-cols-2 gap-4">
                @foreach ([__('Reçus aujourd’hui') => $this->today['received'], __('En attente') => $this->today['pending'], __('Validés') => $this->today['validated'], __('Diffusés') => $this->today['broadcast']] as $label => $value)
                    <div class="rounded-[1.4rem] bg-[#141416] p-5">
                        <p class="text-[3rem] leading-none font-bold tabular-nums">{{ $value }}</p>
                        <p class="mt-2 text-[0.9375rem] text-white/60">{{ $label }}</p>
                    </div>
                @endforeach
            </div>

            <div class="min-h-0 flex-1 overflow-hidden rounded-[1.6rem] bg-[#141416] p-5">
                <p class="flex items-center gap-2 text-[0.875rem] font-semibold tracking-[0.12em] text-[#ff453a] uppercase"><span class="size-2 animate-pulse rounded-full bg-[#ff453a]"></span>{{ __('Urgents en attente') }}</p>
                <ul class="mt-4 space-y-3">
                    @forelse ($this->urgent as $relais)
                        <li class="rounded-[1rem] bg-white/[0.06] p-4" wire:key="nr-u-{{ $relais->uuid }}">
                            <p class="text-[1.0625rem] font-semibold">{{ $relais->title ?: __('Sans titre') }}</p>
                            <p class="text-[0.875rem] text-white/60">{{ $relais->commune?->name }} · {{ __('reçu :when', ['when' => $relais->received_at?->diffForHumans()]) }}</p>
                        </li>
                    @empty
                        <li class="text-[1rem] text-white/50">{{ __('Aucun urgent en attente.') }}</li>
                    @endforelse
                </ul>
            </div>
        </aside>
    </div>
</div>
