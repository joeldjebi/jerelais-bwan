{{-- Interrupteur iOS lié à une propriété Livewire booléenne (wire:model). --}}
@props(['label', 'description' => null, 'name'])

<label class="flex cursor-pointer items-center justify-between gap-6">
    <span>
        <span class="block text-[0.9375rem]">{{ $label }}</span>
        @if ($description)
            <span class="mt-0.5 block text-[0.8125rem] text-mute">{{ $description }}</span>
        @endif
    </span>
    <span class="relative inline-flex shrink-0">
        <input type="checkbox" id="{{ $name }}" {{ $attributes->merge(['class' => 'peer sr-only']) }}>
        <span class="h-[1.9rem] w-[3.1rem] rounded-full bg-black/[0.12] transition-colors duration-300 peer-checked:bg-tile-green peer-focus-visible:shadow-[0_0_0_3px_rgb(10_132_255/0.35)] dark:bg-white/[0.16]"></span>
        <span class="absolute top-[0.125rem] left-[0.125rem] size-[1.65rem] rounded-full bg-white shadow-[0_3px_8px_rgb(0_0_0/0.15),0_0_0_0.5px_rgb(0_0_0/0.04)] transition-transform duration-500 ease-spring peer-checked:translate-x-[1.2rem]"></span>
    </span>
</label>
