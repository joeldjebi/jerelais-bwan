@props(['label', 'value', 'icon' => null, 'trend' => null, 'tone' => 'default'])

<div {{ $attributes->merge(['class' => 'card p-5']) }}>
    <div class="flex items-center justify-between">
        <p class="text-[0.8125rem] font-medium text-mute">{{ $label }}</p>
        @if ($icon)
            <span @class([
                'grid size-8 place-items-center rounded-full',
                'bg-ink/5 text-ink-soft' => $tone === 'default',
                'bg-relais-500/12 text-relais-600' => $tone === 'accent',
                'bg-status-rejected/12 text-status-rejected' => $tone === 'alert',
            ])>
                <x-ui.icon :name="$icon" class="size-4" />
            </span>
        @endif
    </div>
    <p class="mt-3 font-display text-[2rem] leading-none font-semibold tracking-tight tabular-nums" data-count="{{ $value }}">{{ number_format((float) $value, 0, ',', ' ') }}</p>
    @if ($trend)
        <p class="mt-2 text-xs font-medium text-mute">{{ $trend }}</p>
    @endif
</div>
