@props(['title', 'subtitle' => null, 'eyebrow' => null])

<header {{ $attributes->merge(['class' => 'flex flex-wrap items-end justify-between gap-x-6 gap-y-4']) }}>
    <div class="min-w-0">
        @if ($eyebrow)
            <p class="text-[0.8125rem] font-medium text-mute">{{ $eyebrow }}</p>
        @endif
        <h1 class="mt-1 text-[2rem] leading-tight font-semibold tracking-[-0.03em] sm:text-[2.25rem]">{{ $title }}</h1>
        @if ($subtitle)
            <p class="mt-1.5 max-w-2xl text-[0.9375rem] leading-relaxed text-mute">{{ $subtitle }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="flex shrink-0 flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</header>
