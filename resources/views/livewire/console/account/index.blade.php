@php
    $initials = Str::of($this->user->name ?? $this->user->email)->explode(' ')->map(fn ($part) => Str::upper(Str::substr($part, 0, 1)))->take(2)->implode('');
@endphp

<div class="space-y-8">
    <x-console.page-header :eyebrow="__('Compte')" :title="__('Mon compte')" :subtitle="__('Vos informations et la sécurité de votre accès à la console.')" />

    <div class="mx-auto max-w-2xl space-y-8">
        {{-- Carte d'identité --}}
        <x-console.widget class="flex flex-col items-center py-8 text-center">
            <span class="grid size-20 place-items-center rounded-full bg-relais-500 text-[1.75rem] font-semibold text-white shadow-[0_14px_30px_-12px_rgb(242_140_40/0.7)]">{{ $initials }}</span>
            <p class="mt-4 text-[1.5rem] font-bold tracking-[-0.02em]">{{ $this->user->name }}</p>
            <p class="text-[0.9375rem] text-mute">{{ $this->roleLabel }} · {{ $this->user->email }}</p>
        </x-console.widget>

        <form wire:submit="saveProfile">
            <x-console.group :title="__('Profil')" :footer="__('Votre nom apparaît dans le journal d’audit et auprès de votre équipe. L’adresse e-mail est modifiable par un administrateur.')">
                <x-slot:actions>
                    <button type="submit" wire:loading.attr="disabled" wire:target="saveProfile" class="text-[0.875rem] font-semibold text-tile-blue hover:opacity-80 disabled:opacity-40">{{ __('Enregistrer') }}</button>
                </x-slot:actions>
                <ul>
                    <li class="flex items-center gap-3.5 px-4 py-2.5">
                        <x-console.tile icon="users" tint="teal" />
                        <label for="name" class="w-32 text-[0.9375rem]">{{ __('Nom complet') }}</label>
                        <input id="name" wire:model="name" autocomplete="name" class="h-9 min-w-0 flex-1 rounded-[0.6rem] border-0 bg-transparent px-2 text-right text-[0.9375rem] focus:bg-black/[0.04] focus:outline-none dark:focus:bg-white/[0.06]">
                    </li>
                    <li class="flex items-center gap-3.5 px-4 py-3">
                        <x-console.tile icon="bell" tint="blue" />
                        <span class="w-32 text-[0.9375rem]">{{ __('E-mail') }}</span>
                        <span class="min-w-0 flex-1 truncate px-2 text-right text-[0.9375rem] text-mute">{{ $this->user->email }}</span>
                    </li>
                </ul>
            </x-console.group>
            @error('name') <p class="mt-2 px-4 text-xs font-medium text-status-rejected">{{ $message }}</p> @enderror
        </form>

        <form wire:submit="savePassword">
            <x-console.group :title="__('Mot de passe')" :footer="__('10 caractères minimum. Évitez de réutiliser un mot de passe d’un autre service.')">
                <x-slot:actions>
                    <button type="submit" wire:loading.attr="disabled" wire:target="savePassword" class="text-[0.875rem] font-semibold text-tile-blue hover:opacity-80 disabled:opacity-40">{{ __('Changer le mot de passe') }}</button>
                </x-slot:actions>
                <ul>
                    @foreach ([
                        ['currentPassword', __('Actuel'), 'current-password'],
                        ['password', __('Nouveau'), 'new-password'],
                        ['passwordConfirmation', __('Confirmation'), 'new-password'],
                    ] as [$field, $label, $autocomplete])
                        <li class="flex items-center gap-3.5 px-4 py-2.5">
                            <x-console.tile icon="shield" tint="gray" />
                            <label for="{{ $field }}" class="w-32 text-[0.9375rem]">{{ $label }}</label>
                            <input id="{{ $field }}" type="password" wire:model="{{ $field }}" autocomplete="{{ $autocomplete }}" placeholder="••••••••••"
                                class="h-9 min-w-0 flex-1 rounded-[0.6rem] border-0 bg-transparent px-2 text-right text-[0.9375rem] placeholder:text-mute/60 focus:bg-black/[0.04] focus:outline-none dark:focus:bg-white/[0.06]">
                        </li>
                    @endforeach
                </ul>
            </x-console.group>
            @foreach (['currentPassword', 'password'] as $field)
                @error($field) <p class="mt-2 px-4 text-xs font-medium text-status-rejected">{{ $message }}</p> @enderror
            @endforeach
        </form>

        <x-console.group :title="__('Sécurité')">
            <ul>
                <li class="flex items-center gap-3.5 px-4 py-3">
                    <x-console.tile icon="shield" tint="green" />
                    <span class="flex-1 text-[0.9375rem]">{{ __('Double authentification') }}</span>
                    @if ($this->user->hasConfirmedTwoFactor())
                        <x-console.badge tone="success">{{ __('Active') }}</x-console.badge>
                    @else
                        <x-console.badge tone="warning">{{ __('À activer') }}</x-console.badge>
                    @endif
                </li>
                <li class="flex items-center gap-3.5 px-4 py-3">
                    <x-console.tile icon="clock" tint="purple" />
                    <span class="flex-1 text-[0.9375rem]">{{ __('Dernière connexion') }}</span>
                    <span class="text-[0.9375rem] text-mute">{{ $this->user->last_login_at?->diffForHumans() ?? '—' }}</span>
                </li>
                <li>
                    <a href="{{ route('console.security') }}" class="group flex items-center gap-3.5 px-4 py-3 transition-colors duration-200 hover:bg-black/[0.02] dark:hover:bg-white/[0.03]">
                        <x-console.tile icon="layers" tint="pink" />
                        <span class="flex-1 text-[0.9375rem]">{{ __('Gérer la sécurité') }}</span>
                        <svg class="size-4 text-mute/60 transition-transform duration-500 ease-spring group-hover:translate-x-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
                    </a>
                </li>
            </ul>
        </x-console.group>
    </div>
</div>
