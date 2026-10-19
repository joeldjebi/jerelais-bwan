@php
    $field = 'block h-12 w-full rounded-xl border-0 bg-mist px-4 text-[1rem] ring-1 ring-transparent transition-[box-shadow,background-color] duration-300 focus:bg-white focus:ring-2 focus:ring-relais-500/50 focus:outline-none';
    $subjects = ['question' => __('Une question sur JE RELAIS'), 'presse' => __('Presse'), 'partenariat' => __('Partenariat'), 'donnees' => __('Mes données personnelles'), 'autre' => __('Autre')];
@endphp

<section class="bg-mist px-5 pt-32 pb-28 sm:pt-40">
    <div class="mx-auto grid max-w-[1024px] gap-12 lg:grid-cols-[1fr_28rem]">
        <div>
            <h1 class="text-[clamp(2.75rem,7vw,4.5rem)] leading-[1] font-semibold tracking-[-0.045em]">{{ __('Contact.') }}</h1>
            <p class="mt-5 max-w-md text-xl text-graphite">{{ __('Une question, une demande de presse ou sur vos données ? L’équipe JE RELAIS vous répond.') }}</p>
            <p class="mt-8 text-graphite">{{ __('Pour signaler un événement, utilisez plutôt l’application ou votre espace Relais : c’est plus rapide.') }}</p>
        </div>
        <div class="rounded-[1.75rem] bg-white p-7 sm:p-8">
            @if ($sent)
                <div class="py-10 text-center">
                    <span class="mx-auto grid size-16 place-items-center rounded-full bg-tile-green text-white"><x-ui.icon name="check" class="size-8" stroke="2.5" /></span>
                    <p class="mt-5 text-[1.375rem] font-semibold">{{ __('Message envoyé.') }}</p>
                    <p class="mt-1 text-graphite">{{ __('Nous vous répondons par e-mail.') }}</p>
                </div>
            @else
                <form wire:submit="send" class="space-y-5">
                    <div><label for="c-name" class="block text-[0.8125rem] font-medium">{{ __('Nom') }}</label><input id="c-name" wire:model="name" class="{{ $field }} mt-1.5" autocomplete="name">@error('name')<p class="mt-1 text-xs font-medium text-status-rejected">{{ $message }}</p>@enderror</div>
                    <div><label for="c-email" class="block text-[0.8125rem] font-medium">{{ __('Adresse e-mail') }}</label><input id="c-email" type="email" wire:model="email" class="{{ $field }} mt-1.5" autocomplete="email">@error('email')<p class="mt-1 text-xs font-medium text-status-rejected">{{ $message }}</p>@enderror</div>
                    <div><label for="c-subject" class="block text-[0.8125rem] font-medium">{{ __('Sujet') }}</label>
                        <select id="c-subject" wire:model="subject" class="{{ $field }} mt-1.5">@foreach ($subjects as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                    <div><label for="c-message" class="block text-[0.8125rem] font-medium">{{ __('Message') }}</label><textarea id="c-message" wire:model="message" rows="5" class="{{ $field }} mt-1.5 h-auto py-3"></textarea>@error('message')<p class="mt-1 text-xs font-medium text-status-rejected">{{ $message }}</p>@enderror</div>
                    <div class="hidden" aria-hidden="true"><label>Site web<input wire:model="website" tabindex="-1" autocomplete="off"></label></div>
                    <x-ui.button type="submit" size="lg" class="w-full" wire:loading.attr="disabled">{{ __('Envoyer') }}</x-ui.button>
                </form>
            @endif
        </div>
    </div>
</section>
