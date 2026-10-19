{{-- Interrupteur façon iOS, lié à une propriété Livewire booléenne (wire:model). --}}
@props(['label', 'description' => null, 'name'])

<label class="flex cursor-pointer items-start justify-between gap-6">
    <span>
        <span class="block text-[0.875rem] font-medium">{{ $label }}</span>
        @if ($description)
            <span class="mt-0.5 block text-xs text-mute">{{ $description }}</span>
        @endif
    </span>
    <span class="relative mt-0.5 inline-flex shrink-0">
        <input type="checkbox" id="{{ $name }}" {{ $attributes->merge(['class' => 'peer sr-only']) }}>
        <span class="h-[1.6rem] w-[2.6rem] rounded-full bg-ink/15 transition-colors duration-300 peer-checked:bg-status-validated peer-focus-visible:ring-2 peer-focus-visible:ring-relais-500/50"></span>
        <span class="absolute top-[0.15rem] left-[0.15rem] size-[1.3rem] rounded-full bg-white shadow-[0_2px_6px_rgb(0_0_0/0.25)] transition-transform duration-500 ease-spring peer-checked:translate-x-[1rem]"></span>
    </span>
</label>
