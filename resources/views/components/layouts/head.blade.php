@props(['title' => null, 'description' => null])

<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="color-scheme" content="light dark">
<meta name="theme-color" content="#fbfbfd" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#000000" media="(prefers-color-scheme: dark)">

<title>{{ $title ? $title.' · JE RELAIS' : 'JE RELAIS · '.__('Les observateurs citoyens de REFLET TV') }}</title>
<meta name="description" content="{{ $description ?? __('Rejoignez le réseau des Relais de REFLET TV : filmez, photographiez, enregistrez ce qui se passe près de chez vous et faites-le passer à l’antenne.') }}">
<meta property="og:site_name" content="JE RELAIS">
<meta property="og:title" content="{{ $title ?? 'JE RELAIS' }}">
<meta property="og:description" content="{{ $description ?? __('La plateforme des observateurs citoyens de REFLET TV.') }}">
<meta property="og:type" content="website">

{{-- Applique le thème avant le premier rendu pour éviter un flash clair/sombre. --}}
<script nonce="{{ Vite::cspNonce() }}">
    (() => {
        let theme = null;
        try { theme = localStorage.getItem('jerelais.theme'); } catch {}
        const dark = theme ? theme === 'dark' : matchMedia('(prefers-color-scheme: dark)').matches;
        document.documentElement.classList.toggle('dark', dark);
        document.documentElement.classList.add('js');
    })();
</script>

@fonts
@vite(['resources/css/app.css', 'resources/js/app.js'])
