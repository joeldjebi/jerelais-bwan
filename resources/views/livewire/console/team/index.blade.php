@php
    use App\Domain\Access\Enums\Role;
@endphp

<div class="space-y-8">
    <x-console.page-header
        :eyebrow="__('Réseau')"
        :title="__('Équipe')"
        :subtitle="__('Rédaction, coordination et administration. Chaque membre active sa double authentification à sa première connexion.')"
    >
        <x-slot:actions>
            <x-ui.button icon="users" wire:click="openCreate">{{ __('Ajouter un membre') }}</x-ui.button>
        </x-slot:actions>
    </x-console.page-header>

    <x-console.panel :padded="false">
        <div class="flex flex-wrap items-center gap-3 border-b border-line p-4">
            <label class="relative min-w-[14rem] flex-1">
                <span class="sr-only">{{ __('Rechercher un membre') }}</span>
                <x-ui.icon name="search" class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-mute" />
                <input type="search" wire:model.live.debounce.250ms="search" placeholder="{{ __('Nom ou adresse e-mail…') }}"
                    class="h-10 w-full rounded-xl border-0 bg-ink/[0.04] pr-3 pl-10 text-[0.875rem] placeholder:text-mute focus:bg-surface focus:ring-2 focus:ring-relais-500/45 focus:outline-none">
            </label>
            <div class="w-56">
                <x-console.select name="role" wire:model.live="role" class="h-10! text-[0.875rem]!">
                    <option value="">{{ __('Tous les rôles') }}</option>
                    @foreach (\App\Livewire\Console\Team\Index::staffRoles() as $staffRole)
                        <option value="{{ $staffRole->value }}">{{ $staffRole->label() }}</option>
                    @endforeach
                </x-console.select>
            </div>
        </div>

        @if ($this->members->isEmpty())
            <x-console.empty icon="users" :title="__('Aucun membre trouvé')" :text="__('Ajoutez les coordonnateurs et la rédaction pour démarrer le pilote.')">
                <x-ui.button size="sm" wire:click="openCreate">{{ __('Ajouter un membre') }}</x-ui.button>
            </x-console.empty>
        @else
            <ul class="divide-y divide-line" wire:loading.class="opacity-60" wire:target="search,role">
                @foreach ($this->members as $member)
                    @php
                        $memberRole = $member->roles->first()?->name;
                        $scope = $member->communes->isNotEmpty() ? $member->communes->pluck('name')->join(', ') : ($member->regions->isNotEmpty() ? $member->regions->pluck('name')->join(', ') : __('National'));
                        $suspended = ! $member->status->canSignIn();
                    @endphp
                    <li wire:key="member-{{ $member->uuid }}" class="flex flex-wrap items-center gap-4 px-6 py-4 transition-colors duration-200 hover:bg-ink/[0.02] sm:flex-nowrap">
                        <span @class(['grid size-10 shrink-0 place-items-center rounded-full text-[0.8125rem] font-semibold', 'bg-navy-700 text-white' => ! $suspended, 'bg-ink/10 text-mute' => $suspended])>
                            {{ Str::of($member->name ?? $member->email)->explode(' ')->map(fn ($part) => Str::upper(Str::substr($part, 0, 1)))->take(2)->implode('') }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-2 truncate font-semibold">
                                {{ $member->name }}
                                @if ($member->is(auth()->user()))<x-console.badge>{{ __('Vous') }}</x-console.badge>@endif
                            </p>
                            <p class="truncate text-[0.8125rem] text-mute">{{ $member->email }}</p>
                        </div>
                        <div class="hidden w-48 min-w-0 md:block">
                            <p class="truncate text-[0.875rem] font-medium">{{ $memberRole ? Role::from($memberRole)->label() : '—' }}</p>
                            <p class="truncate text-xs text-mute">{{ $scope }}</p>
                        </div>
                        <div class="hidden w-40 lg:block">
                            @if ($suspended)
                                <x-console.badge tone="danger" dot>{{ __('Suspendu') }}</x-console.badge>
                            @elseif ($member->hasConfirmedTwoFactor())
                                <x-console.badge tone="success" dot>{{ __('Actif · 2FA') }}</x-console.badge>
                            @elseif ($member->last_login_at === null)
                                <x-console.badge tone="info" dot>{{ __('Invitation envoyée') }}</x-console.badge>
                            @else
                                <x-console.badge tone="warning" dot>{{ __('2FA à activer') }}</x-console.badge>
                            @endif
                            <p class="mt-1 text-xs text-mute">{{ $member->last_login_at ? __('Vu :when', ['when' => $member->last_login_at->diffForHumans()]) : __('Jamais connecté·e') }}</p>
                        </div>
                        @unless ($member->is(auth()->user()))
                            <x-ui.button size="sm" :variant="$suspended ? 'secondary' : 'ghost'" wire:click="askStatusChange('{{ $member->uuid }}')">
                                {{ $suspended ? __('Réactiver') : __('Suspendre') }}
                            </x-ui.button>
                        @endunless
                    </li>
                @endforeach
            </ul>

            @if ($this->members->hasPages())
                <div class="flex items-center justify-between border-t border-line px-6 py-3 text-[0.8125rem] text-mute">
                    <span>{{ __(':from–:to sur :total', ['from' => $this->members->firstItem(), 'to' => $this->members->lastItem(), 'total' => $this->members->total()]) }}</span>
                    <div class="flex gap-1">
                        <x-ui.button size="sm" variant="secondary" wire:click="previousPage" :disabled="$this->members->onFirstPage()">{{ __('Précédent') }}</x-ui.button>
                        <x-ui.button size="sm" variant="secondary" wire:click="nextPage" :disabled="! $this->members->hasMorePages()">{{ __('Suivant') }}</x-ui.button>
                    </div>
                </div>
            @endif
        @endif
    </x-console.panel>

    {{-- Ajout d'un membre --}}
    <x-console.slide-over model="creating" :title="__('Ajouter un membre')" :description="__('Le membre recevra un e-mail pour définir son mot de passe.')">
        <form id="create-member" wire:submit="create" class="space-y-5">
            <x-console.input :label="__('Nom complet')" name="name" wire:model="name" autocomplete="off" />
            <x-console.input :label="__('Adresse e-mail professionnelle')" name="email" type="email" wire:model="email" autocomplete="off" />

            <div>
                <p class="text-[0.8125rem] font-medium">{{ __('Rôle') }}</p>
                <div class="mt-2 grid grid-cols-2 gap-2">
                    @foreach (\App\Livewire\Console\Team\Index::staffRoles() as $staffRole)
                        <label @class([
                            'flex cursor-pointer items-center gap-2.5 rounded-xl px-3 py-2.5 text-[0.8125rem] font-medium ring-1 transition-[background-color,box-shadow] duration-300',
                            'bg-relais-500/[0.08] ring-relais-500/60' => $newRole === $staffRole->value,
                            'ring-line hover:bg-ink/[0.03]' => $newRole !== $staffRole->value,
                        ])>
                            <input type="radio" wire:model.live="newRole" value="{{ $staffRole->value }}" class="sr-only">
                            <span @class(['size-3.5 shrink-0 rounded-full ring-2 ring-inset', 'bg-relais-500 ring-relais-500' => $newRole === $staffRole->value, 'ring-line' => $newRole !== $staffRole->value])></span>
                            {{ $staffRole->label() }}
                        </label>
                    @endforeach
                </div>
                @error('newRole') <p class="mt-1.5 text-xs font-medium text-status-rejected">{{ $message }}</p> @enderror
            </div>

            @if ($newRole === Role::Coordonnateur->value)
                <div>
                    <p class="text-[0.8125rem] font-medium">{{ __('Communes coordonnées') }}</p>
                    <p class="text-xs text-mute">{{ __('Le coordonnateur ne verra que les données de ces communes.') }}</p>
                    <input type="search" wire:model.live.debounce.200ms="communeSearch" placeholder="{{ __('Filtrer les communes…') }}"
                        class="mt-2 h-10 w-full rounded-xl border-0 bg-ink/[0.04] px-3.5 text-[0.875rem] placeholder:text-mute focus:bg-surface focus:ring-2 focus:ring-relais-500/45 focus:outline-none">
                    <div class="mt-2 max-h-56 space-y-1 overflow-y-auto rounded-xl p-1 ring-1 ring-line">
                        @foreach ($this->communeOptions as $commune)
                            <label wire:key="opt-commune-{{ $commune->uuid }}" class="flex cursor-pointer items-center gap-3 rounded-lg px-3 py-2 text-[0.875rem] hover:bg-ink/[0.04]">
                                <input type="checkbox" wire:model="communeUuids" value="{{ $commune->uuid }}" class="size-4 rounded border-line text-relais-500 focus:ring-relais-500/40">
                                {{ $commune->name }}
                            </label>
                        @endforeach
                    </div>
                    @error('communeUuids') <p class="mt-1.5 text-xs font-medium text-status-rejected">{{ $message }}</p> @enderror
                </div>
            @elseif ($newRole === Role::Superviseur->value)
                <div>
                    <p class="text-[0.8125rem] font-medium">{{ __('Régions supervisées') }}</p>
                    <div class="mt-2 max-h-56 space-y-1 overflow-y-auto rounded-xl p-1 ring-1 ring-line">
                        @foreach ($this->regionOptions as $regionOption)
                            <label wire:key="opt-region-{{ $regionOption->uuid }}" class="flex cursor-pointer items-center gap-3 rounded-lg px-3 py-2 text-[0.875rem] hover:bg-ink/[0.04]">
                                <input type="checkbox" wire:model="regionUuids" value="{{ $regionOption->uuid }}" class="size-4 rounded border-line text-relais-500 focus:ring-relais-500/40">
                                {{ $regionOption->name }}
                            </label>
                        @endforeach
                    </div>
                    @error('regionUuids') <p class="mt-1.5 text-xs font-medium text-status-rejected">{{ $message }}</p> @enderror
                </div>
            @endif
        </form>

        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Annuler') }}</x-ui.button>
            <x-ui.button type="submit" form="create-member" wire:loading.attr="disabled" wire:target="create">
                <span wire:loading.remove wire:target="create">{{ __('Ajouter et inviter') }}</span>
                <span wire:loading wire:target="create">{{ __('Envoi…') }}</span>
            </x-ui.button>
        </x-slot:footer>
    </x-console.slide-over>

    <x-console.confirm
        model="confirmingStatus"
        action="confirmStatusChange"
        :tone="$this->pending?->status?->canSignIn() ? 'danger' : 'primary'"
        :title="$this->pending?->status?->canSignIn() ? __('Suspendre :name ?', ['name' => $this->pending->name]) : __('Réactiver :name ?', ['name' => $this->pending?->name])"
        :text="$this->pending?->status?->canSignIn()
            ? __('Toutes ses sessions et ses appareils seront déconnectés immédiatement. L’action est tracée dans le journal d’audit.')
            : __('Le membre pourra de nouveau se connecter à la console.')"
        :confirm-label="$this->pending?->status?->canSignIn() ? __('Suspendre') : __('Réactiver')"
    />
</div>
