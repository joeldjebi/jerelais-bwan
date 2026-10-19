@php
    use App\Domain\Access\Enums\Role;
    use App\Livewire\Console\Team\Index as TeamPage;

    $roleTint = [
        'admin' => 'pink', 'redacteur_en_chef' => 'orange', 'journaliste' => 'orange', 'lecture_seule' => 'gray',
        'superviseur' => 'purple', 'coordonnateur' => 'blue',
    ];
    $avatarBg = [
        'pink' => 'bg-tile-pink', 'orange' => 'bg-tile-orange', 'gray' => 'bg-tile-gray',
        'purple' => 'bg-tile-purple', 'blue' => 'bg-tile-blue',
    ];
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

    <x-console.group :title="trans_choice(':count membre|:count membres', $this->members->total())">
        <div class="flex flex-wrap items-center gap-3 border-b border-line p-3">
            <x-console.search wire:model.live.debounce.250ms="search" :placeholder="__('Nom ou adresse e-mail')" />
            <div class="w-56">
                <x-console.select name="role" wire:model.live="role" class="h-9! text-[0.875rem]!">
                    <option value="">{{ __('Tous les rôles') }}</option>
                    @foreach (TeamPage::staffRoles() as $staffRole)
                        <option value="{{ $staffRole->value }}">{{ $staffRole->label() }}</option>
                    @endforeach
                </x-console.select>
            </div>
        </div>

        @if ($this->members->isEmpty())
            <x-console.empty icon="users" tint="green" :title="__('Aucun membre trouvé')" :text="__('Ajoutez les coordonnateurs et la rédaction pour démarrer le pilote.')">
                <x-ui.button size="sm" wire:click="openCreate">{{ __('Ajouter un membre') }}</x-ui.button>
            </x-console.empty>
        @else
            <ul wire:loading.class="opacity-50" wire:target="search,role" class="transition-opacity duration-300" data-stagger>
                @foreach ($this->members as $member)
                    @php
                        $memberRole = $member->roles->first()?->name;
                        $scope = $member->communes->isNotEmpty() ? $member->communes->pluck('name')->join(', ') : ($member->regions->isNotEmpty() ? $member->regions->pluck('name')->join(', ') : __('National'));
                        $suspended = ! $member->status->canSignIn();
                        $initials = Str::of($member->name ?? $member->email)->explode(' ')->map(fn ($part) => Str::upper(Str::substr($part, 0, 1)))->take(2)->implode('');
                    @endphp
                    <li wire:key="member-{{ $member->uuid }}" class="flex items-center gap-3.5 px-4 py-3 transition-colors duration-200 hover:bg-black/[0.02] dark:hover:bg-white/[0.03]">
                        <span @class([
                            'relative grid size-10 shrink-0 place-items-center rounded-full text-[0.8125rem] font-semibold text-white',
                            $avatarBg[$roleTint[$memberRole] ?? 'gray'] => ! $suspended,
                            'bg-tile-gray opacity-60' => $suspended,
                        ])>
                            {{ $initials }}
                            @if ($member->hasConfirmedTwoFactor() && ! $suspended)
                                <span class="absolute -right-0.5 -bottom-0.5 grid size-4 place-items-center rounded-full bg-tile-green ring-2 ring-surface"><x-ui.icon name="check" class="size-2.5 text-white" stroke="3.5" /></span>
                            @endif
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-2 truncate text-[0.9375rem] font-semibold">
                                {{ $member->name }}
                                @if ($member->is(auth()->user()))<x-console.badge>{{ __('Vous') }}</x-console.badge>@endif
                            </p>
                            <p class="truncate text-[0.8125rem] text-mute">{{ $memberRole ? Role::from($memberRole)->label() : '—' }} · {{ $scope }}</p>
                        </div>
                        <div class="hidden text-right lg:block">
                            @if ($suspended)
                                <x-console.badge tone="danger">{{ __('Suspendu') }}</x-console.badge>
                            @elseif ($member->last_login_at === null)
                                <x-console.badge tone="info">{{ __('Invitation envoyée') }}</x-console.badge>
                            @elseif (! $member->hasConfirmedTwoFactor())
                                <x-console.badge tone="warning">{{ __('2FA à activer') }}</x-console.badge>
                            @endif
                            <p class="mt-0.5 text-[0.75rem] text-mute">{{ $member->last_login_at ? __('Vu :when', ['when' => $member->last_login_at->diffForHumans()]) : __('Jamais connecté·e') }}</p>
                        </div>
                        @unless ($member->is(auth()->user()))
                            <x-ui.button size="sm" :variant="$suspended ? 'secondary' : 'ghost'" wire:click="askStatusChange('{{ $member->uuid }}')" @class(['text-status-rejected!' => ! $suspended])>
                                {{ $suspended ? __('Réactiver') : __('Suspendre') }}
                            </x-ui.button>
                        @endunless
                    </li>
                @endforeach
            </ul>

            @if ($this->members->hasPages())
                <div class="flex items-center justify-between border-t border-line px-4 py-3 text-[0.8125rem] text-mute">
                    <span>{{ __(':from–:to sur :total', ['from' => $this->members->firstItem(), 'to' => $this->members->lastItem(), 'total' => $this->members->total()]) }}</span>
                    <div class="flex gap-1.5">
                        <x-ui.button size="sm" variant="secondary" wire:click="previousPage" :disabled="$this->members->onFirstPage()">{{ __('Précédent') }}</x-ui.button>
                        <x-ui.button size="sm" variant="secondary" wire:click="nextPage" :disabled="! $this->members->hasMorePages()">{{ __('Suivant') }}</x-ui.button>
                    </div>
                </div>
            @endif
        @endif
    </x-console.group>

    {{-- Ajout d'un membre --}}
    <x-console.slide-over model="creating" :title="__('Nouveau membre')" :description="__('Il recevra un e-mail pour définir son mot de passe.')">
        <form id="create-member" wire:submit="create" class="space-y-6">
            <x-console.group>
                <div class="space-y-4 p-4">
                    <x-console.input :label="__('Nom complet')" name="name" wire:model="name" autocomplete="off" />
                    <x-console.input :label="__('Adresse e-mail professionnelle')" name="email" type="email" wire:model="email" autocomplete="off" />
                </div>
            </x-console.group>

            <x-console.group :title="__('Rôle')">
                <ul>
                    @foreach (TeamPage::staffRoles() as $staffRole)
                        <li>
                            <label class="flex cursor-pointer items-center gap-3 px-4 py-2.5 transition-colors duration-200 hover:bg-black/[0.02] dark:hover:bg-white/[0.03]">
                                <input type="radio" wire:model.live="newRole" value="{{ $staffRole->value }}" class="sr-only">
                                <x-console.tile :icon="$staffRole === Role::Coordonnateur ? 'map-pin' : ($staffRole === Role::Admin ? 'shield' : 'users')" :tint="$roleTint[$staffRole->value]" size="sm" />
                                <span class="flex-1 text-[0.9375rem]">{{ $staffRole->label() }}</span>
                                @if ($newRole === $staffRole->value)
                                    <x-ui.icon name="check" class="size-4 text-tile-blue" stroke="3" />
                                @endif
                            </label>
                        </li>
                    @endforeach
                </ul>
            </x-console.group>
            @error('newRole') <p class="px-4 text-xs font-medium text-status-rejected">{{ $message }}</p> @enderror

            @if ($newRole === Role::Coordonnateur->value)
                <x-console.group :title="__('Communes coordonnées')" :footer="__('Le coordonnateur ne verra que les données de ces communes.')">
                    <div class="border-b border-line p-3">
                        <x-console.search wire:model.live.debounce.200ms="communeSearch" :placeholder="__('Filtrer les communes')" />
                    </div>
                    <ul class="max-h-60 overflow-y-auto">
                        @foreach ($this->communeOptions as $commune)
                            <li wire:key="opt-commune-{{ $commune->uuid }}">
                                <label class="flex cursor-pointer items-center gap-3 px-4 py-2.5 text-[0.9375rem] hover:bg-black/[0.02] dark:hover:bg-white/[0.03]">
                                    <input type="checkbox" wire:model="communeUuids" value="{{ $commune->uuid }}" class="ios-check">
                                    {{ $commune->name }}
                                </label>
                            </li>
                        @endforeach
                    </ul>
                </x-console.group>
                @error('communeUuids') <p class="px-4 text-xs font-medium text-status-rejected">{{ $message }}</p> @enderror
            @elseif ($newRole === Role::Superviseur->value)
                <x-console.group :title="__('Régions supervisées')">
                    <ul class="max-h-60 overflow-y-auto">
                        @foreach ($this->regionOptions as $regionOption)
                            <li wire:key="opt-region-{{ $regionOption->uuid }}">
                                <label class="flex cursor-pointer items-center gap-3 px-4 py-2.5 text-[0.9375rem] hover:bg-black/[0.02] dark:hover:bg-white/[0.03]">
                                    <input type="checkbox" wire:model="regionUuids" value="{{ $regionOption->uuid }}" class="ios-check">
                                    {{ $regionOption->name }}
                                </label>
                            </li>
                        @endforeach
                    </ul>
                </x-console.group>
                @error('regionUuids') <p class="px-4 text-xs font-medium text-status-rejected">{{ $message }}</p> @enderror
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
            ? __('Ses sessions et appareils seront déconnectés immédiatement. L’action est tracée.')
            : __('Le membre pourra de nouveau se connecter à la console.')"
        :confirm-label="$this->pending?->status?->canSignIn() ? __('Suspendre') : __('Réactiver')"
    />
</div>
