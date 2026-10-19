{{-- En-tête de page façon « grand titre » iOS / macOS. --}}
@props(['title', 'subtitle' => null, 'eyebrow' => null, 'index' => null])

<header {{ $attributes->merge(['class' => 'flex flex-wrap items-end justify-between gap-x-8 gap-y-4 pt-2 pb-2']) }}>
    <div class="min-w-0">
        @if ($eyebrow)
            <p class="text-[0.8125rem] font-semibold text-relais-600 dark:text-relais-400" data-fade>{{ $eyebrow }}</p>
        @endif
        <h1 class="mt-1 text-[2.125rem] leading-[1.1] font-bold tracking-[-0.025em] sm:text-[2.5rem]" data-split>{{ $title }}</h1>
        @if ($subtitle)
            <p class="mt-2 max-w-2xl text-[0.9375rem] leading-relaxed text-mute" data-fade>{{ $subtitle }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="flex shrink-0 flex-wrap items-center gap-2" data-fade>{{ $actions }}</div>
    @endisset
</header>
