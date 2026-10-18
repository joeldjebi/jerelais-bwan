<x-layouts.auth :title="__('Connexion')" :subtitle="__('Console REFLET TV et back-office des coordonnateurs.')">
    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf
        <x-ui.input name="email" type="email" :label="__('Adresse e-mail')" autocomplete="username" required autofocus />
        <x-ui.input name="password" type="password" :label="__('Mot de passe')" autocomplete="current-password" required />

        <div class="flex items-center justify-between text-sm">
            <label class="inline-flex items-center gap-2 text-graphite">
                <input type="checkbox" name="remember" class="size-4 rounded border-black/20 text-relais-500 focus:ring-relais-500/40">
                {{ __('Rester connecté') }}
            </label>
            <a href="{{ route('password.request') }}" class="text-relais-600 hover:underline">{{ __('Mot de passe oublié ?') }}</a>
        </div>

        <x-ui.button type="submit" size="lg" class="w-full">{{ __('Se connecter') }}</x-ui.button>
    </form>
</x-layouts.auth>
