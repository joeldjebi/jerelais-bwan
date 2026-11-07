@props(['title', 'subtitle' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <x-layouts.head :title="$title" :force-light="true" />
</head>
<body class="flex min-h-dvh flex-col bg-mist text-onyx">
    <header class="flex h-14 items-center justify-center">
        <a href="{{ route('home') }}" aria-label="JE RELAIS — {{ __('accueil') }}"><x-ui.logo /></a>
    </header>

    <main class="flex flex-1 items-center justify-center px-5 py-10">
        <div class="w-full max-w-[26rem]">
            <div class="text-center">
                <h1 class="text-[2rem] leading-tight font-semibold tracking-[-0.03em]">{{ $title }}</h1>
                @if ($subtitle)
                    <p class="mt-2 text-[0.9375rem] leading-relaxed text-graphite">{{ $subtitle }}</p>
                @endif
            </div>

            @if (session('status') && ! in_array(session('status'), ['two-factor-required'], true))
                <p class="mt-6 rounded-2xl bg-status-validated/10 px-4 py-3 text-sm text-status-validated" role="status">{{ __(session('status')) }}</p>
            @endif

            <div class="mt-8 rounded-[1.75rem] bg-white p-7 shadow-[0_1px_2px_rgb(0_0_0/0.04),0_20px_40px_-20px_rgb(0_0_0/0.15)] sm:p-8">
                {{ $slot }}
            </div>
        </div>
    </main>

    <footer class="pb-8 text-center text-xs text-graphite">
        {{ __('Console REFLET TV · accès réservé à l’équipe') }}
    </footer>
</body>
</html>
