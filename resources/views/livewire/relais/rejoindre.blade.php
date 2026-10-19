<div class="mx-auto grid max-w-[1080px] items-center gap-12 lg:grid-cols-[1fr_26rem] lg:gap-20">
    {{-- Accroche --}}
    <section class="hidden lg:block">
        <p class="text-[0.9375rem] font-medium text-relais-600" data-enter>{{ __('Devenir Relais') }}</p>
        <h1 class="mt-3 text-[clamp(2.75rem,5vw,4rem)] leading-[1.02] font-semibold tracking-[-0.04em]" data-enter>{{ __('Votre quartier, à l’antenne.') }}</h1>
        <p class="mt-5 max-w-md text-lg leading-relaxed text-graphite" data-enter>{{ __('Quelques minutes suffisent pour déposer votre candidature. Le coordonnateur de votre commune l’examine ensuite.') }}</p>

        <ol class="mt-10 space-y-4" data-enter>
            @foreach ([
                ['phone', __('Votre numéro, confirmé par SMS')],
                ['users', __('Votre dossier : identité, commune, motivation')],
                ['flag', __('La charte du Relais et une courte formation')],
                ['check', __('La décision de votre coordonnateur')],
            ] as $index => [$icon, $label])
                <li class="flex items-center gap-4">
                    <span class="grid size-10 shrink-0 place-items-center rounded-full bg-white shadow-[0_1px_2px_rgb(0_0_0/0.05)]">
                        <x-ui.icon :name="$icon" class="size-[1.15rem] text-relais-500" />
                    </span>
                    <span class="text-[1.0625rem]">{{ $label }}</span>
                </li>
            @endforeach
        </ol>
    </section>

    {{-- Carte de connexion --}}
    <section class="rounded-[1.75rem] bg-white p-7 shadow-[0_1px_2px_rgb(0_0_0/0.04),0_24px_48px_-24px_rgb(0_0_0/0.18)] sm:p-9" data-enter>
        @if ($maskedPhone === null)
            <form wire:submit="sendCode" class="space-y-6" wire:key="phase-phone">
                <div>
                    <span class="grid size-12 place-items-center rounded-2xl bg-relais-500 text-white shadow-[0_10px_24px_-10px_rgb(242_140_40/0.8)]">
                        <x-ui.icon name="phone" class="size-6" />
                    </span>
                    <h2 class="mt-5 text-[1.75rem] leading-tight font-semibold tracking-[-0.03em]">{{ __('Commençons par votre numéro') }}</h2>
                    <p class="mt-2 text-[0.9375rem] leading-relaxed text-graphite">{{ __('Vous recevrez un code par SMS. Si vous avez déjà commencé, vous retrouverez votre dossier.') }}</p>
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

                <p class="text-center text-[0.75rem] leading-relaxed text-graphite">{{ __('Votre numéro reste confidentiel : il n’est jamais transmis à la rédaction ni publié.') }}</p>
            </form>
        @else
            <form wire:submit="verify" class="space-y-6" wire:key="phase-code"
                x-data="{ wait: 60, timer: null, start() { this.wait = 60; clearInterval(this.timer); this.timer = setInterval(() => this.wait > 0 ? this.wait-- : clearInterval(this.timer), 1000) } }"
                x-init="start()" x-on:otp-sent.window="start()">
                <div>
                    <span class="grid size-12 place-items-center rounded-2xl bg-tile-green text-white shadow-[0_10px_24px_-10px_rgb(52_199_89/0.8)]">
                        <x-ui.icon name="shield" class="size-6" />
                    </span>
                    <h2 class="mt-5 text-[1.75rem] leading-tight font-semibold tracking-[-0.03em]">{{ __('Saisissez le code reçu') }}</h2>
                    <p class="mt-2 text-[0.9375rem] leading-relaxed text-graphite">
                        {{ __('Envoyé au :phone. Il expire dans 5 minutes.', ['phone' => $maskedPhone]) }}
                        <button type="button" wire:click="changeNumber" class="font-medium text-relais-600 hover:underline">{{ __('Modifier') }}</button>
                    </p>
                </div>

                <div>
                    <label for="code" class="sr-only">{{ __('Code de vérification') }}</label>
                    <input id="code" wire:model="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="••••••" autofocus
                        x-on:input="$el.value = $el.value.replace(/\D/g, '').slice(0, 6); if ($el.value.length === 6) { $wire.set('code', $el.value, false); $wire.verify() }"
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
        @endif
    </section>
</div>
