<div class="space-y-8">
    <x-console.page-header :eyebrow="__('Administration')" :title="__('Données personnelles')" :subtitle="__('Demandes d’accès, de rectification et de suppression (§9.4). Chaque action est inscrite au journal d’audit.')" />

    <x-console.group :title="__('Retrouver un compte')">
        <form wire:submit="search" class="flex flex-wrap items-end gap-3 p-4">
            <div class="min-w-64 flex-1"><x-console.input :label="__('Téléphone ou e-mail')" name="query" wire:model="query" placeholder="07 07 07 07 07" /></div>
            <x-ui.button type="submit" icon="search">{{ __('Rechercher') }}</x-ui.button>
        </form>
    </x-console.group>

    @if ($query !== '' && $this->found === null)
        <p class="text-[0.9375rem] text-mute">{{ __('Aucun compte ne correspond.') }}</p>
    @elseif ($user = $this->found)
        <x-console.group :title="__('Compte')">
            <ul class="text-[0.9375rem]">
                <li class="flex gap-3 px-4 py-2.5"><span class="w-32 text-mute">{{ __('Nom') }}</span><span>{{ $user->name ?? '—' }}</span></li>
                <li class="flex gap-3 px-4 py-2.5"><span class="w-32 text-mute">{{ __('Rôle') }}</span><span>{{ $user->getRoleNames()->join(', ') }}</span></li>
                <li class="flex gap-3 px-4 py-2.5"><span class="w-32 text-mute">{{ __('Statut') }}</span><span>{{ $user->status->value }}</span></li>
                <li class="flex gap-3 px-4 py-2.5"><span class="w-32 text-mute">{{ __('Créé le') }}</span><span>{{ $user->created_at?->translatedFormat('j F Y') }}</span></li>
            </ul>
            <div class="flex flex-wrap gap-2 border-t border-line p-3">
                <x-ui.button variant="secondary" :href="route('console.privacy.export', $user)" icon="arrow-up-right">{{ __('Exporter les données (JSON)') }}</x-ui.button>
                @if ($user->status !== $deleted && ! $user->isStaff())
                    <x-ui.button variant="danger" wire:click="$set('confirming', true)">{{ __('Supprimer le compte') }}</x-ui.button>
                @endif
            </div>
        </x-console.group>

        <x-console.slide-over model="confirming" :title="__('Supprimer le compte')" :description="__('Identité, pièces, téléphone, positions et messages sont effacés ; les traces de diffusion et le journal d’audit restent, anonymisés.')">
            <form id="privacy-form" wire:submit="pseudonymize">
                <label for="privacy-reason" class="block text-[0.8125rem] font-medium">{{ __('Motif (référence de la demande)') }}</label>
                <textarea id="privacy-reason" wire:model="reason" rows="3" class="mt-1.5 block w-full rounded-[0.8rem] border-0 bg-black/[0.055] p-3.5 text-[0.9375rem] focus:outline-none dark:bg-white/[0.08]"></textarea>
                @error('reason') <p class="mt-1 text-xs font-medium text-status-rejected">{{ $message }}</p> @enderror
            </form>
            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Annuler') }}</x-ui.button>
                <x-ui.button type="submit" form="privacy-form" variant="danger">{{ __('Supprimer définitivement') }}</x-ui.button>
            </x-slot:footer>
        </x-console.slide-over>
    @endif
</div>
