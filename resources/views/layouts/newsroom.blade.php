{{-- Vue « salle de rédaction » (ADM-05) : plein écran sombre, pour un grand écran. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark h-full" data-default-theme="dark">
<head>
    <x-layouts.head :title="$title ?? null" :scripts="['resources/js/console.js']" default-theme="dark" />
    @livewireStyles
</head>
<body class="h-full overflow-hidden bg-black text-white antialiased">
    <main data-console-main class="h-full">
        {{ $slot }}
    </main>
    @livewireScriptConfig
</body>
</html>
