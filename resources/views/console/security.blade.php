{{-- Activation de la double authentification (obligatoire pour l'équipe — ADM-24). --}}
<x-layouts.auth :title="__('Sécurité du compte')" :subtitle="__('La double authentification est obligatoire pour accéder à la console REFLET TV.')">
    @if ($user->hasConfirmedTwoFactor())
        <div class="text-center">
            <span class="mx-auto grid size-14 place-items-center rounded-full bg-status-validated/12 text-status-validated">
                <x-ui.icon name="shield" class="size-7" />
            </span>
            <p class="mt-4 text-lg font-semibold">{{ __('Double authentification active') }}</p>
        </div>

        @if ($showRecoveryCodes)
            <div class="mt-6 rounded-2xl bg-mist p-5">
                <p class="text-sm font-semibold">{{ __('Codes de secours') }}</p>
                <p class="mt-1 text-xs text-graphite">{{ __('Conservez-les en lieu sûr : chacun permet une connexion si vous perdez votre téléphone. Ils ne seront plus affichés.') }}</p>
                <ul class="mt-4 grid grid-cols-2 gap-2 font-mono text-sm">
                    @foreach ($user->recoveryCodes() as $code)
                        <li class="rounded-lg bg-white px-3 py-2 text-center">{{ $code }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <x-ui.button :href="route('console.home')" size="lg" class="mt-6 w-full" icon-trailing="arrow-right">{{ __('Accéder à la console') }}</x-ui.button>
    @elseif ($pendingConfirmation)
        <ol class="space-y-6">
            <li>
                <p class="text-sm font-semibold">{{ __('1. Scannez ce QR code') }}</p>
                <p class="mt-1 text-xs text-graphite">{{ __('Avec Google Authenticator, Microsoft Authenticator ou l’app Mots de passe de l’iPhone.') }}</p>
                <div class="mt-4 flex justify-center rounded-2xl bg-white p-4 ring-1 ring-black/5 [&_svg]:size-44">
                    {!! $user->twoFactorQrCodeSvg() !!}
                </div>
                <p class="mt-3 text-center font-mono text-xs break-all text-graphite">{{ decrypt($user->two_factor_secret) }}</p>
            </li>
            <li>
                <p class="text-sm font-semibold">{{ __('2. Saisissez le code affiché') }}</p>
                <form method="POST" action="{{ route('two-factor.confirm') }}" class="mt-3 space-y-4">
                    @csrf
                    <x-ui.input name="code" :label="__('Code à 6 chiffres')" inputmode="numeric" autocomplete="one-time-code" maxlength="6" autofocus />
                    <x-ui.button type="submit" size="lg" class="w-full">{{ __('Activer') }}</x-ui.button>
                </form>
            </li>
        </ol>
    @else
        <p class="text-[0.9375rem] leading-relaxed text-graphite">{{ __('Chaque connexion demandera, en plus de votre mot de passe, un code généré par une application sur votre téléphone.') }}</p>
        <form method="POST" action="{{ route('two-factor.enable') }}" class="mt-6">
            @csrf
            <x-ui.button type="submit" size="lg" class="w-full" icon="shield">{{ __('Activer la double authentification') }}</x-ui.button>
        </form>
    @endif

    <form method="POST" action="{{ route('logout') }}" class="mt-4 text-center">
        @csrf
        <button type="submit" class="text-sm text-graphite hover:text-onyx hover:underline">{{ __('Se déconnecter') }}</button>
    </form>
</x-layouts.auth>
