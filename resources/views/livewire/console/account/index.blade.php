<div class="space-y-8">
    <x-console.page-header :eyebrow="__('Compte')" :title="__('Mon compte')" :subtitle="__('Vos informations et la sécurité de votre accès à la console.')" />

    <div class="grid gap-6 lg:grid-cols-[1fr_20rem]">
        <div class="space-y-6">
            <form wire:submit="saveProfile">
                <x-console.panel :title="__('Profil')" :description="__('Votre nom apparaît dans le journal d’audit et auprès de votre équipe.')">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-console.input :label="__('Nom complet')" name="name" wire:model="name" autocomplete="name" />
                        <div>
                            <p class="text-[0.8125rem] font-medium">{{ __('Adresse e-mail') }}</p>
                            <p class="mt-1.5 flex h-11 items-center rounded-xl bg-ink/[0.025] px-3.5 text-[0.9375rem] text-mute">{{ $this->user->email }}</p>
                            <p class="mt-1.5 text-xs text-mute">{{ __('Modifiable par un administrateur.') }}</p>
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end">
                        <x-ui.button type="submit" size="sm" wire:loading.attr="disabled" wire:target="saveProfile">{{ __('Enregistrer') }}</x-ui.button>
                    </div>
                </x-console.panel>
            </form>

            <form wire:submit="savePassword">
                <x-console.panel :title="__('Mot de passe')" :description="__('10 caractères minimum. Évitez de réutiliser un mot de passe d’un autre service.')">
                    <div class="space-y-5">
                        <x-console.input :label="__('Mot de passe actuel')" name="currentPassword" type="password" wire:model="currentPassword" autocomplete="current-password" />
                        <div class="grid gap-5 sm:grid-cols-2">
                            <x-console.input :label="__('Nouveau mot de passe')" name="password" type="password" wire:model="password" autocomplete="new-password" />
                            <x-console.input :label="__('Confirmation')" name="passwordConfirmation" type="password" wire:model="passwordConfirmation" autocomplete="new-password" />
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end">
                        <x-ui.button type="submit" size="sm" wire:loading.attr="disabled" wire:target="savePassword">{{ __('Changer le mot de passe') }}</x-ui.button>
                    </div>
                </x-console.panel>
            </form>
        </div>

        <div class="space-y-6">
            <x-console.panel>
                <div class="flex flex-col items-center text-center">
                    <span class="grid size-16 place-items-center rounded-full bg-navy-700 text-xl font-semibold text-white">
                        {{ Str::of($this->user->name ?? $this->user->email)->explode(' ')->map(fn ($part) => Str::upper(Str::substr($part, 0, 1)))->take(2)->implode('') }}
                    </span>
                    <p class="mt-4 font-semibold">{{ $this->user->name }}</p>
                    <p class="text-[0.8125rem] text-mute">{{ $this->roleLabel }}</p>
                </div>
            </x-console.panel>

            <x-console.panel :title="__('Sécurité')">
                <ul class="space-y-4 text-[0.875rem]">
                    <li class="flex items-center justify-between gap-3">
                        <span>{{ __('Double authentification') }}</span>
                        @if ($this->user->hasConfirmedTwoFactor())
                            <x-console.badge tone="success" dot>{{ __('Active') }}</x-console.badge>
                        @else
                            <x-console.badge tone="warning" dot>{{ __('À activer') }}</x-console.badge>
                        @endif
                    </li>
                    <li class="flex items-center justify-between gap-3">
                        <span>{{ __('Dernière connexion') }}</span>
                        <span class="text-mute">{{ $this->user->last_login_at?->diffForHumans() ?? '—' }}</span>
                    </li>
                </ul>
                <x-ui.button :href="route('console.security')" variant="secondary" size="sm" class="mt-5 w-full" icon="shield">{{ __('Gérer la sécurité') }}</x-ui.button>
            </x-console.panel>
        </div>
    </div>
</div>
