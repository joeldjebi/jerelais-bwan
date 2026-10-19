@props(['label' => null, 'name', 'hint' => null])

<div>
    @if ($label)
        <label for="{{ $name }}" class="block text-[0.8125rem] font-medium">{{ $label }}</label>
    @endif
    <div @class(['relative', 'mt-1.5' => $label])>
        <select
            id="{{ $name }}"
            {{ $attributes->merge(['class' => 'block h-11 w-full appearance-none rounded-xl border-0 bg-ink/[0.04] pr-10 pl-3.5 text-[0.9375rem] ring-1 ring-transparent transition-[box-shadow,background-color] duration-300 focus:bg-surface focus:ring-2 focus:ring-relais-500/45 focus:outline-none']) }}
        >
            {{ $slot }}
        </select>
        <svg class="pointer-events-none absolute top-1/2 right-3.5 size-4 -translate-y-1/2 text-mute" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m7 10 5 5 5-5"/></svg>
    </div>
    @if ($hint)
        <p class="mt-1.5 text-xs text-mute">{{ $hint }}</p>
    @endif
    @error($name)
        <p class="mt-1.5 text-xs font-medium text-status-rejected">{{ $message }}</p>
    @enderror
</div>
