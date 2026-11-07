<x-layouts.auth :title="__('Nouveau mot de passe')">
    <form method="POST" action="{{ route('password.update') }}" class="space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <x-ui.input name="email" type="email" :label="__('Adresse e-mail')" :value="old('email', $request->email)" autocomplete="username" required />
        <x-ui.input name="password" type="password" :label="__('Nouveau mot de passe')" :hint="__('10 caractères minimum.')" autocomplete="new-password" required />
        <x-ui.input name="password_confirmation" type="password" :label="__('Confirmation')" autocomplete="new-password" required />
        <x-ui.button type="submit" size="lg" class="w-full">{{ __('Enregistrer') }}</x-ui.button>
    </form>
</x-layouts.auth>
