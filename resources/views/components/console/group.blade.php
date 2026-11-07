{{-- Groupe de lignes façon Réglages iOS / macOS : intitulé au-dessus, carte arrondie, séparateurs fins. --}}
@props(['title' => null, 'footer' => null])

<section {{ $attributes->merge(['class' => 'space-y-2']) }} data-enter>
    @if ($title || isset($actions))
        <div class="flex items-end justify-between gap-4 px-4">
            @if ($title)
                <h2 class="text-[0.8125rem] font-semibold text-mute">{{ $title }}</h2>
            @endif
            @isset($actions)
                <div class="flex items-center gap-2">{{ $actions }}</div>
            @endisset
        </div>
    @endif
    <div class="overflow-hidden rounded-[1.1rem] bg-surface shadow-[0_0_0_0.5px_rgb(0_0_0/0.06),0_2px_12px_-4px_rgb(0_0_0/0.06)] dark:shadow-[0_0_0_0.5px_rgb(255_255_255/0.08)] [&>ul>li+li]:border-t [&>ul>li+li]:border-line [&>div>ul>li+li]:border-t [&>div>ul>li+li]:border-line">
        {{ $slot }}
    </div>
    @if ($footer)
        <p class="px-4 text-[0.8125rem] leading-relaxed text-mute">{{ $footer }}</p>
    @endif
</section>
