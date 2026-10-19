@props(['title' => null, 'description' => null, 'padded' => true])

<section {{ $attributes->merge(['class' => 'overflow-hidden rounded-[1.25rem] bg-surface ring-1 ring-line shadow-[0_1px_2px_rgb(0_0_0/0.03)]']) }}>
    @if ($title || isset($actions))
        <header class="flex items-center justify-between gap-4 border-b border-line px-6 py-4">
            <div class="min-w-0">
                @if ($title)
                    <h2 class="text-[0.9375rem] font-semibold tracking-tight">{{ $title }}</h2>
                @endif
                @if ($description)
                    <p class="mt-0.5 text-[0.8125rem] text-mute">{{ $description }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="flex shrink-0 items-center gap-2">{{ $actions }}</div>
            @endisset
        </header>
    @endif
    <div @class(['p-6' => $padded])>
        {{ $slot }}
    </div>
</section>
