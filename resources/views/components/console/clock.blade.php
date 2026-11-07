{{-- Horloge analogique (heure d'Abidjan), aiguilles animées par resources/js/console.js. --}}
<div {{ $attributes->merge(['class' => 'flex items-center gap-5']) }} data-analog-clock>
    <svg viewBox="0 0 100 100" class="size-28 shrink-0" aria-hidden="true">
        <circle cx="50" cy="50" r="48" fill="rgb(255 255 255 / 0.06)" />
        @foreach (range(0, 11) as $hour)
            <line x1="50" y1="{{ $hour % 3 === 0 ? 7 : 9 }}" x2="50" y2="13" stroke="rgb(255 255 255 / {{ $hour % 3 === 0 ? '0.9' : '0.35' }})" stroke-width="{{ $hour % 3 === 0 ? 2.4 : 1.4 }}" stroke-linecap="round" transform="rotate({{ $hour * 30 }} 50 50)" />
        @endforeach
        <line x1="50" y1="50" x2="50" y2="27" stroke="white" stroke-width="4" stroke-linecap="round" data-hand="hour" />
        <line x1="50" y1="50" x2="50" y2="17" stroke="white" stroke-width="3" stroke-linecap="round" data-hand="minute" />
        <line x1="50" y1="58" x2="50" y2="12" stroke="#ff9f0a" stroke-width="1.4" stroke-linecap="round" data-hand="second" />
        <circle cx="50" cy="50" r="2.6" fill="#ff9f0a" />
    </svg>
    <div>
        <p class="text-[0.8125rem] font-semibold text-white/60">{{ __('Abidjan') }}</p>
        <p class="mt-1 text-[2.25rem] leading-none font-bold tracking-[-0.03em] tabular-nums" data-clock-digital>--:--</p>
        <p class="mt-1.5 text-[0.8125rem] text-white/60">{{ ucfirst(now()->translatedFormat('l j F')) }}</p>
    </div>
</div>
