@props([
    'title' => null,
    'description' => null,
    'forceLight' => false,
    'defaultTheme' => 'system',
    'scripts' => ['resources/js/app.js'],
])

<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="color-scheme" content="{{ $forceLight ? 'light' : 'light dark' }}">
<meta name="theme-color" content="#ffffff">

<title>{{ $title ? $title.' · JE RELAIS' : 'JE RELAIS · '.__('Les observateurs citoyens de REFLET TV') }}</title>
<meta name="description" content="{{ $description ?? __('Rejoignez le réseau des Relais de REFLET TV : filmez, photographiez, enregistrez ce qui se passe près de chez vous et faites-le passer à l’antenne.') }}">
<meta property="og:site_name" content="JE RELAIS">
<meta property="og:title" content="{{ $title ?? 'JE RELAIS' }}">
<meta property="og:description" content="{{ $description ?? __('La plateforme des observateurs citoyens de REFLET TV.') }}">
<meta property="og:type" content="website">

{{-- Thème appliqué avant le premier rendu (pas de flash) ; la classe « js » active les animations. --}}
<script nonce="{{ Vite::cspNonce() }}">
    (() => {
        const root = document.documentElement;
        root.classList.add('js');
        @if ($forceLight)
            root.classList.remove('dark');
        @else
            let theme = null;
            try { theme = localStorage.getItem('jerelais.theme'); } catch {}
            theme = theme || '{{ $defaultTheme }}';
            root.dataset.defaultTheme = '{{ $defaultTheme }}';
            root.classList.toggle('dark', theme === 'system' ? matchMedia('(prefers-color-scheme: dark)').matches : theme === 'dark');
        @endif
    })();
</script>

@fonts
@vite(['resources/css/app.css', ...$scripts])
