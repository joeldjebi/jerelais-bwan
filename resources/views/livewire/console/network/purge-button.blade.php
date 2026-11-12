<div>
    @if ($visible)
        <x-ui.button variant="secondary" size="sm" class="text-status-rejected!" wire:click="$set('open', true)">{{ __('Tout vider') }}</x-ui.button>

        <x-console.slide-over model="open" :title="__('Tout vider')" :description="__('Réservé aux administrateurs. Action définitive.')">
            <form id="purge-form" wire:submit="purge" class="space-y-4">
                <p class="text-[0.9375rem] leading-relaxed">
                    {{ __('Supprime tous les Relais et tous les candidats, avec leurs relais, médias, conversations, candidatures, pièces d’identité et appareils. Les comptes de l’équipe ne sont pas touchés. Les numéros pourront se réinscrire.') }}
                </p>
                <p class="text-[0.8125rem] text-mute">{{ __('L’opération est inscrite au journal d’audit.') }}</p>
                <div>
                    <label for="purge-confirmation" class="block text-[0.8125rem] font-medium">{{ __('Tapez :word pour confirmer', ['word' => \App\Domain\Privacy\Actions\PurgeNetworkAccounts::CONFIRMATION]) }}</label>
                    <input id="purge-confirmation" type="text" wire:model="confirmation" autocomplete="off" class="mt-1.5 block h-10 w-full rounded-[0.8rem] border-0 bg-black/[0.055] px-3.5 text-[0.9375rem] focus:bg-surface focus:shadow-[0_0_0_3px_rgb(10_132_255/0.3)] focus:outline-none dark:bg-white/[0.08]">
                    @error('confirmation') <p class="mt-1.5 text-xs font-medium text-status-rejected">{{ $message }}</p> @enderror
                </div>
            </form>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Annuler') }}</x-ui.button>
                <x-ui.button type="submit" form="purge-form" variant="danger" wire:loading.attr="disabled" wire:target="purge">{{ __('Tout supprimer') }}</x-ui.button>
            </x-slot:footer>
        </x-console.slide-over>
    @endif
</div>
