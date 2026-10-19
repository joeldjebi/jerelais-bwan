@php
    $states = ['received', 'in_review', 'complement_requested', 'sensitive', 'validated', 'selected', 'broadcast', 'rejected'];
@endphp

<div class="space-y-6">
    <x-console.page-header
        :eyebrow="__('Pilotage')"
        :title="__('Carte des relais')"
        :subtitle="__('Les relais localisés de votre périmètre. Zoomez jusqu’au quartier ; les points proches sont regroupés.')"
    />

    <div class="flex flex-wrap items-center gap-3" data-fade>
        <x-console.segmented property="period" :current="$period" :options="['1' => __('24 h'), '7' => __('7 jours'), '30' => __('30 jours'), 'all' => __('Tout')]" />
        <div class="w-48">
            <x-console.select name="category" wire:model.live="category" class="h-9! text-[0.875rem]!">
                <option value="">{{ __('Toutes catégories') }}</option>
                @foreach ($this->categories as $item)<option value="{{ $item->uuid }}">{{ $item->name }}</option>@endforeach
            </x-console.select>
        </div>
        <div class="w-40">
            <x-console.select name="urgency" wire:model.live="urgency" class="h-9! text-[0.875rem]!">
                <option value="">{{ __('Toute urgence') }}</option>
                @foreach ($urgencies as $item)<option value="{{ $item->value }}">{{ $item->label() }}</option>@endforeach
            </x-console.select>
        </div>
        <div class="w-48">
            <x-console.select name="state" wire:model.live="state" class="h-9! text-[0.875rem]!">
                <option value="">{{ __('Tous les statuts') }}</option>
                @foreach ($states as $item)<option value="{{ $item }}">{{ __("relais_items.states.{$item}") }}</option>@endforeach
            </x-console.select>
        </div>
        <span class="ml-auto text-[0.8125rem] text-mute tabular-nums">{{ trans_choice(':count relais localisé|:count relais localisés', count($this->points)) }}</span>
    </div>

    <div wire:ignore x-data="relaisMap(@js($this->points))" x-on:map-points.window="update($event.detail.points)"
        class="relative h-[70vh] min-h-[28rem] overflow-hidden rounded-[1.6rem] bg-surface shadow-[0_0_0_0.5px_rgb(0_0_0/0.06),0_2px_12px_-4px_rgb(0_0_0/0.06)]" data-enter>
        <div x-ref="map" class="absolute inset-0"></div>
        <div class="pointer-events-none absolute bottom-4 left-4 z-[500] flex gap-2 rounded-full bg-surface/90 px-3 py-1.5 text-[0.75rem] shadow backdrop-blur">
            <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-[#ff3b30]"></span>{{ __('Urgent') }}</span>
            <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-[#ff9f0a]"></span>{{ __('Important') }}</span>
            <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-[#0a84ff]"></span>{{ __('Normal') }}</span>
        </div>
    </div>
</div>
