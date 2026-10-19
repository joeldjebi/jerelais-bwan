<x-layouts.auth :title="__('Confirmez votre identité')" :subtitle="__('Pour continuer, saisissez à nouveau votre mot de passe.')">
    <form method="POST" action="{{ route('password.confirm') }}" class="space-y-5">
        @csrf
        <x-ui.input name="password" type="password" :label="__('Mot de passe')" autocomplete="current-password" required autofocus />
        <x-ui.button type="submit" size="lg" class="w-full">{{ __('Confirmer') }}</x-ui.button>
    </form>
</x-layouts.auth>
