<x-layouts.auth :title="__('Mot de passe oublié')" :subtitle="__('Nous vous envoyons un lien de réinitialisation par e-mail.')">
    <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
        @csrf
        <x-ui.input name="email" type="email" :label="__('Adresse e-mail')" autocomplete="username" required autofocus />
        <x-ui.button type="submit" size="lg" class="w-full">{{ __('Envoyer le lien') }}</x-ui.button>
        <p class="text-center text-sm"><a href="{{ route('login') }}" class="text-relais-600 hover:underline">{{ __('Retour à la connexion') }}</a></p>
    </form>
</x-layouts.auth>
