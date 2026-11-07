{{-- Navigation de l'espace Relais (WEB-03). --}}
@php
    $links = [
        'send' => [route('relais.send'), __('Envoyer'), 'camera'],
        'items' => [route('relais.items'), __('Mes relais'), 'inbox'],
        'appels' => [route('relais.appels'), __('Appels'), 'bell'],
        'messages' => [route('relais.messages'), __('Messages'), 'users'],
        'badge' => [route('relais.candidature'), __('Mon badge'), 'shield'],
    ];
@endphp
<nav class="-mx-1 flex gap-1 overflow-x-auto pb-1" aria-label="{{ __('Espace Relais') }}" data-enter>
    @foreach ($links as $key => [$href, $text, $icon])
        <a href="{{ $href }}" wire:navigate @class([
            'press inline-flex shrink-0 items-center gap-2 rounded-full px-4 py-2 text-[0.875rem] font-medium transition-colors',
            'bg-onyx text-white' => $active === $key,
            'text-graphite hover:bg-black/[0.05] hover:text-onyx' => $active !== $key,
        ]) @if ($active === $key) aria-current="page" @endif>
            <x-ui.icon :name="$icon" class="size-4" />{{ $text }}
        </a>
    @endforeach
</nav>
