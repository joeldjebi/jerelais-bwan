{{-- Carte de contenu (liste groupée façon iOS / macOS). --}}
@props(['title' => null, 'description' => null, 'padded' => true, 'label' => null, 'icon' => null, 'tint' => 'gray'])

<section {{ $attributes->merge(['class' => 'overflow-hidden rounded-[1.25rem] bg-surface shadow-[0_0_0_0.5px_rgb(0_0_0/0.06),0_2px_12px_-4px_rgb(0_0_0/0.06)] dark:shadow-[0_0_0_0.5px_rgb(255_255_255/0.08)]']) }} data-enter>
    @if ($title || isset($actions))
        <header class="flex items-center justify-between gap-4 px-5 pt-5 pb-3">
            <div class="flex min-w-0 items-center gap-3">
                @if ($icon)
                    <x-console.tile :icon="$icon" :tint="$tint" />
                @endif
                <div class="min-w-0">
                    @if ($title)
                        <h2 class="text-[1.0625rem] font-semibold tracking-[-0.01em]">{{ $title }}</h2>
                    @endif
                    @if ($description)
                        <p class="text-[0.8125rem] text-mute">{{ $description }}</p>
                    @endif
                </div>
            </div>
            @isset($actions)
                <div class="flex shrink-0 items-center gap-2">{{ $actions }}</div>
            @endisset
        </header>
    @endif
    <div @class(['p-5 pt-2' => $padded && ($title || isset($actions)), 'p-5' => $padded && ! ($title || isset($actions))])>
        {{ $slot }}
    </div>
</section>
