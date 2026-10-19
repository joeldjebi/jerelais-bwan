<div class="mx-auto max-w-2xl space-y-6">
    <header data-enter>
        <h1 class="text-[clamp(2rem,4.5vw,2.75rem)] leading-tight font-semibold tracking-[-0.035em]">{{ __('Mes données') }}</h1>
        <p class="mt-2 text-[1.0625rem] text-graphite">{{ __('Vous pouvez à tout moment consulter les données que JE RELAIS conserve sur vous, ou supprimer votre compte.') }}</p>
    </header>

    <section class="rounded-[1.75rem] bg-white p-7 shadow-[0_1px_2px_rgb(0_0_0/0.04)]" data-enter>
        <h2 class="text-[1.25rem] font-semibold">{{ __('Télécharger mes données') }}</h2>
        <p class="mt-1 text-graphite">{{ __('Identité, candidature, consentements, relais envoyés, messages : un fichier lisible par un ordinateur.') }}</p>
        <x-ui.button class="mt-5" variant="secondary" :href="route('relais.data.download')" icon="arrow-up-right">{{ __('Télécharger (JSON)') }}</x-ui.button>
        <p class="mt-3 text-[0.8125rem] text-graphite">{{ __('Une erreur ? Vous pouvez la faire rectifier via la page Contact.') }}</p>
    </section>

    <section class="rounded-[1.75rem] bg-white p-7 shadow-[0_1px_2px_rgb(0_0_0/0.04)]" data-enter>
        <h2 class="text-[1.25rem] font-semibold text-status-rejected">{{ __('Supprimer mon compte') }}</h2>
        <p class="mt-1 leading-relaxed text-graphite">{{ __('Votre identité, vos pièces, votre téléphone, vos positions et vos messages seront effacés. Les relais déjà diffusés restent dans les archives de la chaîne, sans votre nom. Cette action est définitive.') }}</p>
        <form wire:submit="delete" class="mt-5 space-y-3">
            <label for="confirm" class="block text-[0.8125rem] font-medium">{{ __('Tapez SUPPRIMER pour confirmer') }}</label>
            <input id="confirm" wire:model="confirm" autocomplete="off" class="block h-12 w-full rounded-xl border-0 bg-mist px-4 focus:bg-white focus:ring-2 focus:ring-status-rejected/40 focus:outline-none">
            @error('confirm') <p class="text-xs font-medium text-status-rejected">{{ $message }}</p> @enderror
            <x-ui.button type="submit" variant="danger">{{ __('Supprimer définitivement mon compte') }}</x-ui.button>
        </form>
    </section>
</div>
