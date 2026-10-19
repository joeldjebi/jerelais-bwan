{{-- Alertes du réseau (ADM-03) : urgents en attente, retards de validation, afflux sur une commune. --}}
@props(['alerts'])

@if ($alerts['urgent'] > 0 || $alerts['overdue'] > 0 || $alerts['surges'] !== [])
    <div {{ $attributes->merge(['class' => 'flex flex-wrap gap-2']) }} role="status">
        @if ($alerts['urgent'] > 0)
            <a href="{{ route('console.validation') }}" wire:navigate class="press inline-flex items-center gap-2 rounded-full bg-status-rejected px-3.5 py-1.5 text-[0.8125rem] font-semibold text-white shadow-[0_8px_20px_-10px_rgb(255_59_48/0.8)]">
                <span class="size-1.5 animate-pulse rounded-full bg-white"></span>
                {{ trans_choice(':count urgent en attente|:count urgents en attente', $alerts['urgent']) }}
            </a>
        @endif
        @if ($alerts['overdue'] > 0)
            <a href="{{ route('console.validation') }}" wire:navigate class="press inline-flex items-center gap-2 rounded-full bg-tile-orange px-3.5 py-1.5 text-[0.8125rem] font-semibold text-white">
                <x-ui.icon name="clock" class="size-3.5" />
                {{ trans_choice(':count relais en retard|:count relais en retard', $alerts['overdue']) }}
            </a>
        @endif
        @foreach ($alerts['surges'] as $surge)
            <span class="inline-flex items-center gap-2 rounded-full bg-tile-purple px-3.5 py-1.5 text-[0.8125rem] font-semibold text-white">
                <x-ui.icon name="chart" class="size-3.5" />
                {{ __('Afflux à :commune : :count relais en :window min', ['commune' => $surge['commune'], 'count' => $surge['count'], 'window' => $alerts['window']]) }}
            </span>
        @endforeach
    </div>
@endif
