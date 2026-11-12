@php
    $titles = [
        'inscription' => ['phone' => __('Créez votre compte'), 'code' => __('Saisissez le code reçu'), 'password' => __('Choisissez votre mot de passe')],
        'oubli' => ['phone' => __('Mot de passe oublié'), 'code' => __('Saisissez le code reçu'), 'password' => __('Nouveau mot de passe')],
    ];
    $inputClass = 'h-13 w-full rounded-2xl border-0 bg-mist px-4 text-[1.0625rem] ring-1 ring-transparent transition-[box-shadow,background-color] duration-300 placeholder:text-graphite/50 focus:bg-white focus:ring-2 focus:ring-relais-500/50 focus:outline-none';
@endphp

<div class="mx-auto grid max-w-[1080px] items-center gap-12 lg:grid-cols-[1fr_26rem] lg:gap-20">
    {{-- Accroche --}}
    <section class="hidden lg:block">
        <p class="text-[0.9375rem] font-medium text-relais-600" data-enter>{{ __('Devenir Relais') }}</p>
        <h1 class="mt-3 text-[clamp(2.75rem,5vw,4rem)] leading-[1.02] font-semibold tracking-[-0.04em]" data-enter>{{ __('Votre quartier, à l’antenne.') }}</h1>
        <p class="mt-5 max-w-md text-lg leading-relaxed text-graphite" data-enter>{{ __('Une minute suffit : votre numéro, un code par SMS et un mot de passe. Vous pouvez envoyer vos premiers relais tout de suite.') }}</p>

        <ol class="mt-10 space-y-4" data-enter>
            @foreach ([
                ['phone', __('Votre numéro, confirmé par SMS')],
                ['shield', __('Un mot de passe pour vous connecter')],
                ['camera', __('Vos premiers relais, dès maintenant')],
                ['users', __('Votre profil plus tard, pour recevoir vos commissions')],
            ] as [$icon, $label])
                <li class="flex items-center gap-4">
                    <span class="grid size-10 shrink-0 place-items-center rounded-full bg-white shadow-[0_1px_2px_rgb(0_0_0/0.05)]">
                        <x-ui.icon :name="$icon" class="size-[1.15rem] text-relais-500" />
                    </span>
                    <span class="text-[1.0625rem]">{{ $label }}</span>
                </li>
            @endforeach
        </ol>
    </section>

    {{-- Carte --}}
    <section class="rounded-[1.75rem] bg-white p-7 shadow-[0_1px_2px_rgb(0_0_0/0.04),0_24px_48px_-24px_rgb(0_0_0/0.18)] sm:p-9" data-enter>
        {{-- Onglets --}}
        <div class="mb-7 grid grid-cols-2 rounded-full bg-mist p-1 text-[0.875rem] font-medium" role="tablist">
            <button type="button" role="tab" wire:click="switchMode('inscription')" @class(['press rounded-full py-2 transition-colors', 'bg-white shadow-[0_1px_2px_rgb(0_0_0/0.08)]' => $mode === 'inscription', 'text-graphite' => $mode !== 'inscription']) aria-selected="{{ $mode === 'inscription' ? 'true' : 'false' }}">{{ __('S’inscrire') }}</button>
            <button type="button" role="tab" wire:click="switchMode('connexion')" @class(['press rounded-full py-2 transition-colors', 'bg-white shadow-[0_1px_2px_rgb(0_0_0/0.08)]' => $mode !== 'inscription', 'text-graphite' => $mode === 'inscription']) aria-selected="{{ $mode !== 'inscription' ? 'true' : 'false' }}">{{ __('Se connecter') }}</button>
        </div>

        @if ($notice)
            <p class="mb-6 flex items-start gap-2 rounded-2xl bg-tile-green/10 p-3.5 text-[0.875rem] font-medium text-tile-green" role="status">
                <x-ui.icon name="check" class="mt-0.5 size-4 shrink-0" stroke="3" />{{ $notice }}
            </p>
        @endif

        @if ($mode === 'connexion')
            {{-- Connexion --}}
            <form wire:submit="login" class="space-y-5" wire:key="form-login">
                <div>
                    <h2 class="text-[1.75rem] leading-tight font-semibold tracking-[-0.03em]">{{ __('Bon retour parmi nous') }}</h2>
                    <p class="mt-2 text-[0.9375rem] leading-relaxed text-graphite">{{ __('Connectez-vous avec votre numéro et votre mot de passe.') }}</p>
                </div>
                <div>
                    <label for="login-phone" class="block text-[0.8125rem] font-medium">{{ __('Numéro de téléphone') }}</label>
                    <input id="login-phone" type="tel" inputmode="tel" autocomplete="tel-national" wire:model="phone" placeholder="07 07 07 07 07" class="mt-1.5 {{ $inputClass }}">
                    @error('phone') <p class="mt-2 text-[0.8125rem] font-medium text-status-rejected">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="login-password" class="block text-[0.8125rem] font-medium">{{ __('Mot de passe') }}</label>
                    <input id="login-password" type="password" autocomplete="current-password" wire:model="password" class="mt-1.5 {{ $inputClass }}">
                </div>
                <x-ui.button type="submit" size="lg" class="w-full" icon-trailing="arrow-right" wire:loading.attr="disabled" wire:target="login">
                    <span wire:loading.remove wire:target="login">{{ __('Se connecter') }}</span>
                    <span wire:loading wire:target="login">{{ __('Connexion…') }}</span>
                </x-ui.button>
                <p class="text-center text-[0.875rem]">
                    <button type="button" wire:click="switchMode('oubli')" class="font-medium text-relais-600 hover:underline">{{ __('Mot de passe oublié ?') }}</button>
                </p>
            </form>
        @elseif ($step === 'phone')
            {{-- Inscription ou oubli : numéro --}}
            <form wire:submit="sendCode" class="space-y-6" wire:key="form-phone-{{ $mode }}">
                <div>
                    <span class="grid size-12 place-items-center rounded-2xl bg-relais-500 text-white shadow-[0_10px_24px_-10px_rgb(242_140_40/0.8)]">
                        <x-ui.icon name="phone" class="size-6" />
                    </span>
                    <h2 class="mt-5 text-[1.75rem] leading-tight font-semibold tracking-[-0.03em]">{{ $titles[$mode]['phone'] }}</h2>
                    <p class="mt-2 text-[0.9375rem] leading-relaxed text-graphite">
                        {{ $mode === 'oubli' ? __('Vous recevrez un code par SMS pour choisir un nouveau mot de passe.') : __('Vous recevrez un code par SMS pour confirmer votre numéro.') }}
                    </p>
                </div>

                <div>
                    <label for="phone" class="block text-[0.8125rem] font-medium">{{ __('Numéro de téléphone') }}</label>
                    <div class="mt-1.5 flex h-13 items-center rounded-2xl bg-mist ring-1 ring-transparent transition-[box-shadow,background-color] duration-300 focus-within:bg-white focus-within:ring-2 focus-within:ring-relais-500/50">
                        <span class="pl-4 text-[1.0625rem] font-medium text-graphite">+225</span>
                        <input id="phone" type="tel" inputmode="tel" autocomplete="tel-national" wire:model="phone" placeholder="07 07 07 07 07" autofocus
                            class="h-full min-w-0 flex-1 border-0 bg-transparent px-3 text-[1.0625rem] tracking-wide placeholder:text-graphite/50 focus:ring-0 focus:outline-none"
                            @error('phone') aria-invalid="true" aria-describedby="phone-error" @enderror>
                    </div>
                    @error('phone') <p id="phone-error" class="mt-2 text-[0.8125rem] font-medium text-status-rejected">{{ $message }}</p> @enderror
                </div>

                <x-ui.button type="submit" size="lg" class="w-full" icon-trailing="arrow-right" wire:loading.attr="disabled" wire:target="sendCode">
                    <span wire:loading.remove wire:target="sendCode">{{ __('Recevoir mon code') }}</span>
                    <span wire:loading wire:target="sendCode">{{ __('Envoi…') }}</span>
                </x-ui.button>

                @if ($mode === 'oubli')
                    <p class="text-center text-[0.875rem]"><button type="button" wire:click="switchMode('connexion')" class="font-medium text-relais-600 hover:underline">{{ __('Retour à la connexion') }}</button></p>
                @else
                    <p class="text-center text-[0.75rem] leading-relaxed text-graphite">{{ __('Votre numéro reste confidentiel : il n’est jamais transmis à la rédaction ni publié.') }}</p>
                @endif
            </form>
        @elseif ($step === 'code')
            {{-- Code SMS --}}
            <form wire:submit="verify" class="space-y-6" wire:key="form-code-{{ $mode }}"
                x-data="{ wait: 60, timer: null, start() { this.wait = 60; clearInterval(this.timer); this.timer = setInterval(() => this.wait > 0 ? this.wait-- : clearInterval(this.timer), 1000) } }"
                x-init="start()" x-on:otp-sent.window="start()">
                <div>
                    <span class="grid size-12 place-items-center rounded-2xl bg-tile-green text-white shadow-[0_10px_24px_-10px_rgb(52_199_89/0.8)]">
                        <x-ui.icon name="shield" class="size-6" />
                    </span>
                    <h2 class="mt-5 text-[1.75rem] leading-tight font-semibold tracking-[-0.03em]">{{ $titles[$mode]['code'] }}</h2>
                    <p class="mt-2 text-[0.9375rem] leading-relaxed text-graphite">
                        {{ __('Envoyé au :phone. Il expire dans 5 minutes.', ['phone' => $maskedPhone]) }}
                        <button type="button" wire:click="changeNumber" class="font-medium text-relais-600 hover:underline">{{ __('Modifier') }}</button>
                    </p>
                </div>

                <div>
                    <label for="code" class="sr-only">{{ __('Code de vérification') }}</label>
                    <input id="code" wire:model="code" inputmode="numeric" autocomplete="one-time-code" maxlength="{{ $codeLength }}" placeholder="{{ str_repeat('•', $codeLength) }}" autofocus
                        x-on:input="$el.value = $el.value.replace(/\D/g, '').slice(0, {{ $codeLength }}); if ($el.value.length === {{ $codeLength }}) { $wire.set('code', $el.value, false); $wire.verify() }"
                        class="block h-16 w-full rounded-2xl border-0 bg-mist text-center font-mono text-[2rem] tracking-[0.5em] ring-1 ring-transparent transition-[box-shadow,background-color] duration-300 placeholder:text-graphite/40 focus:bg-white focus:ring-2 focus:ring-relais-500/50 focus:outline-none"
                        @error('code') aria-invalid="true" aria-describedby="code-error" @enderror>
                    @error('code') <p id="code-error" class="mt-2 text-center text-[0.8125rem] font-medium text-status-rejected">{{ $message }}</p> @enderror
                </div>

                <x-ui.button type="submit" size="lg" class="w-full" wire:loading.attr="disabled" wire:target="verify">
                    <span wire:loading.remove wire:target="verify">{{ __('Continuer') }}</span>
                    <span wire:loading wire:target="verify">{{ __('Vérification…') }}</span>
                </x-ui.button>

                <p class="text-center text-[0.8125rem] text-graphite">
                    <span x-show="wait > 0">{{ __('Renvoyer le code dans') }} <span x-text="wait" class="tabular-nums"></span> s</span>
                    <button type="button" x-show="wait === 0" x-cloak wire:click="sendCode" class="font-medium text-relais-600 hover:underline">{{ __('Renvoyer le code') }}</button>
                </p>
            </form>
        @else
            {{-- Mot de passe --}}
            <form wire:submit="savePassword" class="space-y-5" wire:key="form-password-{{ $mode }}">
                <div>
                    <span class="grid size-12 place-items-center rounded-2xl bg-onyx text-white">
                        <x-ui.icon name="shield" class="size-6" />
                    </span>
                    <h2 class="mt-5 text-[1.75rem] leading-tight font-semibold tracking-[-0.03em]">{{ $titles[$mode]['password'] }}</h2>
                    <p class="mt-2 text-[0.9375rem] leading-relaxed text-graphite">{{ __('Au moins 8 caractères. Vous l’utiliserez pour vous connecter, sur le site comme dans l’application.') }}</p>
                </div>
                <div>
                    <label for="new-password" class="block text-[0.8125rem] font-medium">{{ __('Mot de passe') }}</label>
                    <input id="new-password" type="password" autocomplete="new-password" wire:model="password" autofocus class="mt-1.5 {{ $inputClass }}">
                    @error('password') <p class="mt-2 text-[0.8125rem] font-medium text-status-rejected">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="new-password-confirmation" class="block text-[0.8125rem] font-medium">{{ __('Confirmez le mot de passe') }}</label>
                    <input id="new-password-confirmation" type="password" autocomplete="new-password" wire:model="passwordConfirmation" class="mt-1.5 {{ $inputClass }}">
                </div>
                <x-ui.button type="submit" size="lg" class="w-full" wire:loading.attr="disabled" wire:target="savePassword">
                    <span wire:loading.remove wire:target="savePassword">{{ $mode === 'oubli' ? __('Enregistrer et me connecter') : __('Créer mon compte') }}</span>
                    <span wire:loading wire:target="savePassword">{{ __('Enregistrement…') }}</span>
                </x-ui.button>
            </form>
        @endif
    </section>
</div>
