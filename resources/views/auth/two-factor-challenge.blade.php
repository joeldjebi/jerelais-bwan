<x-layouts.auth :title="__('Double authentification')" :subtitle="__('Saisissez le code à 6 chiffres affiché par votre application d’authentification.')">
    <form method="POST" action="{{ route('two-factor.login') }}" class="space-y-5">
        @csrf
        <x-ui.input name="code" :label="__('Code de vérification')" inputmode="numeric" autocomplete="one-time-code" maxlength="6" autofocus />

        <details class="group text-sm">
            <summary class="cursor-pointer list-none text-relais-600 hover:underline">{{ __('Utiliser un code de secours') }}</summary>
            <div class="mt-4">
                <x-ui.input name="recovery_code" :label="__('Code de secours')" autocomplete="off" />
            </div>
        </details>

        <x-ui.button type="submit" size="lg" class="w-full">{{ __('Vérifier') }}</x-ui.button>
    </form>
</x-layouts.auth>
