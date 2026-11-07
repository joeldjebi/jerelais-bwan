{{-- Mosaïque façon régie : chaque écran montre une étape d'un relais. Parallaxe au défilement. --}}
@php
    $tileClass = 'relative aspect-video overflow-hidden rounded-lg bg-onyx text-white sm:rounded-2xl';
    $labelClass = 'absolute top-2 left-2.5 text-[0.5rem] font-semibold tracking-[0.14em] text-white/55 uppercase sm:top-3 sm:left-4 sm:text-[0.625rem]';
    $timecodeClass = 'absolute top-2 right-2.5 hidden font-mono sm:block text-[0.4375rem] text-white/55 tabular-nums sm:top-3 sm:right-4 sm:text-[0.625rem]';
@endphp

<div class="absolute inset-x-0 top-[56svh] mx-auto grid w-[min(1180px,92vw)] grid-cols-2 gap-2.5 sm:top-[54svh] sm:grid-cols-3 sm:gap-4" data-wall aria-hidden="true">
    {{-- Caméra --}}
    <div class="{{ $tileClass }}" data-tile>
        <span class="{{ $labelClass }}">Cam 1 · Abobo</span>
        <span class="{{ $timecodeClass }}" data-timecode="3721">00:00:00:00</span>
        <div class="absolute inset-[18%_12%_16%]">
            <span class="absolute top-0 left-0 size-3 border-t border-l border-white/70 sm:size-5"></span>
            <span class="absolute top-0 right-0 size-3 border-t border-r border-white/70 sm:size-5"></span>
            <span class="absolute bottom-0 left-0 size-3 border-b border-l border-white/70 sm:size-5"></span>
            <span class="absolute right-0 bottom-0 size-3 border-r border-b border-white/70 sm:size-5"></span>
            <span class="absolute inset-x-0 top-1/2 h-px animate-scan bg-white/40"></span>
        </div>
        <span class="absolute bottom-2 left-2.5 inline-flex items-center gap-1 text-[0.4375rem] font-semibold sm:bottom-3 sm:left-4 sm:text-[0.6875rem]"><span class="size-1 animate-blink rounded-full bg-[#ff3b30] sm:size-1.5"></span>REC</span>
    </div>

    {{-- Écran central : un relais validé puis diffusé --}}
    <div class="{{ $tileClass }} order-first col-span-2 bg-white! text-onyx! ring-1 ring-black/10 sm:order-none sm:col-span-1" data-wall-center>
        <span class="absolute top-2 left-2.5 text-[0.5625rem] font-semibold tracking-[0.14em] text-graphite uppercase sm:top-3 sm:left-4 sm:text-[0.625rem]">{{ __('Relais') }} · Yopougon</span>
        <div class="absolute inset-x-[10%] top-1/2 -translate-y-1/2">
            <p class="font-display text-lg leading-tight font-semibold tracking-tight sm:text-xl">{{ __('Pluies diluviennes à Niangon') }}</p>
            <div class="mt-2 h-6 overflow-hidden sm:mt-3 sm:h-7">
                <div class="animate-cycle">
                    <p class="flex h-6 items-center gap-1.5 text-[0.8125rem] font-semibold text-[#5e5ce6] sm:h-7 sm:text-sm"><span class="size-1.5 rounded-full bg-current"></span>{{ __('En vérification') }}</p>
                    <p class="flex h-6 items-center gap-1.5 text-[0.8125rem] font-semibold text-status-validated sm:h-7 sm:text-sm"><x-ui.icon name="check" class="size-3.5 sm:size-4" stroke="2.5" />{{ __('Validé') }}</p>
                    <p class="flex h-6 items-center gap-1.5 text-[0.8125rem] font-semibold text-relais-600 sm:h-7 sm:text-sm"><x-ui.icon name="signal" class="size-3.5 sm:size-4" stroke="2" />{{ __('Diffusé sur REFLET TV') }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Note vocale --}}
    <div class="{{ $tileClass }}" data-tile>
        <span class="{{ $labelClass }}">{{ __('Note vocale') }} · Dioula</span>
        <span class="{{ $timecodeClass }}" data-timecode="9184">00:00:00:00</span>
        <div class="absolute inset-x-[12%] top-1/2 flex h-1/3 -translate-y-1/2 items-center justify-between">
            @foreach (range(0, 23) as $bar)
                <span class="h-full w-[2px] origin-center animate-wave rounded-full bg-white/85 sm:w-[3px]" style="animation-delay: -{{ ($bar * 173 % 100) / 100 }}s"></span>
            @endforeach
        </div>
        <span class="absolute bottom-2 left-2.5 text-[0.4375rem] font-semibold text-white/70 sm:bottom-3 sm:left-4 sm:text-[0.6875rem]">Korhogo</span>
    </div>

    {{-- GPS --}}
    <div class="{{ $tileClass }} hidden sm:block" data-tile>
        <span class="{{ $labelClass }}">GPS · Treichville</span>
        <span class="{{ $timecodeClass }}" data-timecode="15402">00:00:00:00</span>
        <svg class="absolute inset-0 size-full" aria-hidden="true"><defs><pattern id="gps-dots" width="10" height="10" patternUnits="userSpaceOnUse"><circle cx="1" cy="1" r="1" fill="rgb(255 255 255 / 0.16)" /></pattern></defs><rect width="100%" height="100%" fill="url(#gps-dots)" /></svg>
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2">
            <span class="absolute -inset-3 animate-ring rounded-full border border-relais-500 sm:-inset-5"></span>
            <x-ui.icon name="map-pin" class="size-4 text-relais-500 sm:size-6" stroke="2" />
        </div>
        <span class="absolute bottom-2 left-2.5 font-mono text-[0.4375rem] text-white/70 sm:bottom-3 sm:left-4 sm:text-[0.625rem]">5.2939° N · 4.0128° O</span>
    </div>

    {{-- Envoi reprenable --}}
    <div class="{{ $tileClass }} hidden sm:block" data-tile>
        <span class="{{ $labelClass }}">{{ __('Envoi') }} · Bouaké</span>
        <span class="{{ $timecodeClass }}" data-timecode="22630">00:00:00:00</span>
        <div class="absolute inset-x-[12%] top-1/2 -translate-y-1/2">
            <div class="h-1 overflow-hidden rounded-full bg-white/15 sm:h-1.5"><div class="h-full animate-resume rounded-full bg-relais-500"></div></div>
            <p class="mt-1.5 text-[0.4375rem] text-white/70 sm:mt-2.5 sm:text-[0.6875rem]">{{ __('Reprise automatique') }}</p>
        </div>
    </div>

    {{-- Validation --}}
    <div class="{{ $tileClass }} hidden sm:block" data-tile>
        <span class="{{ $labelClass }}">{{ __('Validation communale') }} · Cocody</span>
        <span class="{{ $timecodeClass }}" data-timecode="31277">00:00:00:00</span>
        <div class="absolute top-1/2 left-[12%] h-4 -translate-y-1/2 overflow-hidden sm:h-7">
            <div class="animate-cycle">
                <p class="flex h-4 items-center gap-1.5 text-[0.5625rem] font-semibold text-status-pending sm:h-7 sm:text-sm"><span class="size-1.5 rounded-full bg-current"></span>{{ __('En attente') }}</p>
                <p class="flex h-4 items-center gap-1.5 text-[0.5625rem] font-semibold text-[#8e8cff] sm:h-7 sm:text-sm"><span class="size-1.5 rounded-full bg-current"></span>{{ __('En vérification') }}</p>
                <p class="flex h-4 items-center gap-1.5 text-[0.5625rem] font-semibold text-status-validated sm:h-7 sm:text-sm"><span class="size-1.5 rounded-full bg-current"></span>{{ __('Validé') }}</p>
            </div>
        </div>
    </div>
</div>
