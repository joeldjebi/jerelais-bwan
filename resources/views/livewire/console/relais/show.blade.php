@php
    use App\Domain\Access\Enums\Permission;
    use App\Domain\Relais\Enums\RelaisStatus;

    $p = $profile;
    $statusTone = ['active' => 'success', 'suspended' => 'warning', 'struck_off' => 'danger'];
    $canManage = auth()->user()->can(Permission::RelaisProfilesSuspend->value);
    $isAdmin = auth()->user()->can(Permission::TerritoireManage->value);
    $field = 'mt-1.5 block w-full rounded-[0.8rem] border-0 bg-black/[0.055] p-3.5 text-[0.9375rem] focus:bg-surface focus:shadow-[0_0_0_3px_rgb(10_132_255/0.3)] focus:outline-none dark:bg-white/[0.08]';
@endphp

<div class="space-y-8">
    <a href="{{ route('console.relais') }}" wire:navigate class="inline-flex items-center gap-1.5 text-[0.875rem] font-medium text-tile-blue hover:opacity-80" data-fade>
        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="m15 6-6 6 6 6"/></svg>
        {{ __('Relais') }}
    </a>

    <header class="flex flex-wrap items-center gap-5" data-enter>
        <span class="grid size-20 place-items-center rounded-full bg-relais-500 text-[1.75rem] font-semibold text-white">{{ Str::upper(Str::substr($p->display_name, 0, 1)) }}</span>
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2.5">
                <h1 class="text-[clamp(1.75rem,3.5vw,2.5rem)] leading-tight font-bold tracking-[-0.03em]">{{ $p->candidature?->fullName() ?: $p->display_name }}</h1>
                <x-console.badge :tone="$statusTone[$p->status->value]">{{ $p->status->label() }}</x-console.badge>
            </div>
            <p class="mt-1 text-[0.9375rem] text-mute"><span class="font-mono">{{ $p->number }}</span> · {{ collect([$p->commune?->name, $p->localite?->name])->filter()->join(' · ') }} · {{ __('Relais depuis le :date', ['date' => $p->activated_at->translatedFormat('j F Y')]) }}</p>
        </div>
        @if ($canManage)
            <div class="flex flex-wrap gap-2">
                @if ($p->status === RelaisStatus::Active)
                    <x-ui.button variant="secondary" wire:click="open('suspended')">{{ __('Suspendre') }}</x-ui.button>
                    <x-ui.button variant="ghost" class="text-status-rejected!" wire:click="open('struck_off')">{{ __('Radier') }}</x-ui.button>
                @elseif ($p->status === RelaisStatus::Suspended || $isAdmin)
                    <x-ui.button variant="success" wire:click="open('active')">{{ __('Réintégrer') }}</x-ui.button>
                @endif
                @if ($isAdmin)
                    <x-ui.button variant="secondary" icon="map-pin" wire:click="open('transfer')">{{ __('Transférer') }}</x-ui.button>
                @endif
            </div>
        @endif
    </header>

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-5" data-bento>
        <x-console.stat :label="__('Envoyés')" value="" :count="$this->stats['sent']" icon="inbox" tint="blue" />
        <x-console.stat :label="__('Validés')" value="" :count="$this->stats['validated']" icon="check" tint="green" />
        <x-console.stat :label="__('Rejetés')" value="" :count="$this->stats['rejected']" icon="x" tint="pink" />
        <x-console.stat :label="__('Diffusés')" value="" :count="$this->stats['broadcast']" icon="signal" tint="brand" />
        <x-console.stat :label="__('Fiabilité')" :value="$this->stats['reliability'] < 0 ? '—' : $this->stats['reliability'].' %'" icon="shield" :tint="$this->stats['reliability'] >= 70 ? 'green' : 'orange'" :hint="__('Indicateur interne')" />
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        @if ($this->canSeePersonal)
            <x-console.group :title="__('Informations personnelles')" :footer="__('Chaque consultation est inscrite au journal d’audit (§9.4).')">
                @if (! $revealed)
                    <div class="flex flex-col items-center gap-3 px-6 py-8 text-center">
                        <x-console.tile icon="eye-off" tint="pink" />
                        <p class="max-w-sm text-[0.875rem] text-mute">{{ __('Nom, naissance, téléphone, e-mail, pièce d’identité et coordonnées de paiement du Relais.') }}</p>
                        <x-ui.button size="sm" variant="secondary" wire:click="revealPersonal">{{ __('Afficher') }}</x-ui.button>
                    </div>
                @else
                    @php
                        ['candidature' => $c, 'account' => $account, 'identity' => $idv, 'payout' => $payout, 'gains' => $gains] = $this->personal;
                        $phone = $account?->phoneNumber();
                        $fcfa = fn (int $amount): string => number_format($amount, 0, ',', ' ').' FCFA';
                        $rows = [
                            __('Nom') => $c?->last_name,
                            __('Prénoms') => $c?->first_names,
                            __('Sexe') => $c?->gender?->label(),
                            __('Naissance') => $c?->birth_date ? $c->birth_date->translatedFormat('j F Y').' ('.__(':age ans', ['age' => (int) $c->birth_date->diffInYears(now())]).')' : null,
                            __('Profession') => $c?->occupation?->label(),
                            __('E-mail') => $c?->email ?: $account?->email,
                            __('Compte créé') => $account?->created_at?->timezone('Africa/Abidjan')->translatedFormat('j F Y'),
                            __('Dernière connexion') => $account?->last_login_at?->timezone('Africa/Abidjan')->translatedFormat('j F Y, H:i'),
                        ];
                    @endphp
                    <dl class="divide-y divide-line text-[0.9375rem]">
                        <div class="flex justify-between gap-4 px-4 py-2.5">
                            <dt class="text-mute">{{ __('Téléphone') }}</dt>
                            <dd class="text-right font-medium">@if ($phone)<a href="tel:{{ $phone->e164 }}" class="text-tile-blue hover:opacity-80">{{ $phone->e164 }}</a>@else — @endif</dd>
                        </div>
                        @foreach ($rows as $label => $value)
                            <div class="flex justify-between gap-4 px-4 py-2.5"><dt class="text-mute">{{ $label }}</dt><dd class="text-right font-medium">{{ $value ?: '—' }}</dd></div>
                        @endforeach
                        <div class="flex items-center justify-between gap-4 px-4 py-2.5">
                            <dt class="text-mute">{{ __('Identité') }}</dt>
                            <dd class="text-right font-medium">
                                @if ($idv && auth()->user()->can(Permission::UsersManage->value))
                                    <a href="{{ route('console.identity.show', $idv) }}" wire:navigate class="text-tile-blue hover:opacity-80">{{ $idv->status->label() }}@if ($idv->documentType) · {{ $idv->documentType->name }}@endif</a>
                                @elseif ($idv)
                                    {{ $idv->status->label() }}@if ($idv->documentType) · {{ $idv->documentType->name }}@endif
                                @else
                                    {{ __('Non soumise') }}
                                @endif
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4 px-4 py-2.5">
                            <dt class="text-mute">{{ __('Paiement') }}</dt>
                            <dd class="text-right font-medium">{{ $payout ? $payout->method->label().' · '.$payout->phone.' · '.$payout->holder_name : __('Non renseigné') }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 px-4 py-2.5">
                            <dt class="text-mute">{{ __('Portefeuille') }}</dt>
                            <dd class="text-right font-medium">{{ __(':available disponibles · :withdrawn retirés', ['available' => $fcfa($gains['available']), 'withdrawn' => $fcfa($gains['withdrawn'])]) }}</dd>
                        </div>
                    </dl>
                @endif
            </x-console.group>
        @endif

        <x-console.group :title="__('Notifications push')">
            <div class="flex items-center gap-3.5 px-4 py-4">
                <x-console.tile icon="bell" :tint="$this->pushCount > 0 ? 'green' : 'gray'" />
                <div class="min-w-0 flex-1">
                    <p class="text-[0.9375rem] font-semibold">{{ $this->pushCount > 0 ? trans_choice('Activées sur :count appareil|Activées sur :count appareils', $this->pushCount) : __('Non activées') }}</p>
                    <p class="text-[0.8125rem] text-mute">
                        {{ $this->pushCount > 0
                            ? __('L’application a enregistré son jeton de notifications : le Relais reçoit les notifications push.')
                            : __('Aucun jeton de notifications enregistré : le Relais ne reçoit que les SMS de secours et les annonces dans l’application.') }}
                    </p>
                </div>
                <x-console.badge :tone="$this->pushCount > 0 ? 'success' : 'neutral'">{{ $this->pushCount > 0 ? __('Push activé') : __('Pas de push') }}</x-console.badge>
            </div>
            @if ($this->devices->isNotEmpty())
                <ul class="border-t border-line">
                    @foreach ($this->devices as $device)
                        <li wire:key="push-{{ $device->uuid }}" class="flex items-center justify-between gap-3 px-4 py-2.5 text-[0.875rem]">
                            <span class="min-w-0 truncate">{{ $device->name }} <span class="text-mute">· {{ $device->platform->value }}{{ $device->app_version ? ' · v'.$device->app_version : '' }}</span></span>
                            <span @class(['shrink-0 font-medium', 'text-status-validated' => $device->fcm_token !== null, 'text-mute' => $device->fcm_token === null])>{{ $device->fcm_token !== null ? __('Jeton enregistré') : __('Pas de jeton') }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-console.group>
    </div>

    <x-console.group :title="__('Historique des relais')">
        @if ($this->history->isEmpty())
            <x-console.empty icon="inbox" tint="blue" :title="__('Aucun envoi')" :text="__('Les relais envoyés par ce Relais apparaîtront ici.')" />
        @else
            <ul>
                @foreach ($this->history as $relais)
                    <x-console.relais-row :relais="$relais" />
                @endforeach
            </ul>
        @endif
    </x-console.group>

    @can(\App\Domain\Access\Enums\Permission::RelaisProfilesSuspend->value)
        <x-console.group :title="__('Appareils connectés')" :footer="__('Téléphone perdu ou volé : déconnectez-le, l’application devra se reconnecter par code SMS (MOB-31).')">
            @if ($this->devices->isEmpty())
                <x-console.empty icon="shield" tint="gray" :title="__('Aucun appareil connecté')" :text="__('Le Relais n’est connecté sur aucun téléphone.')" />
            @else
                <ul>
                    @foreach ($this->devices as $device)
                        <li wire:key="device-{{ $device->uuid }}" class="flex items-center gap-3.5 px-4 py-3">
                            <x-console.tile icon="signal" tint="blue" />
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-[0.9375rem] font-medium">{{ $device->name }}</p>
                                <p class="truncate text-[0.75rem] text-mute">{{ $device->platform->value }}{{ $device->app_version ? ' · v'.$device->app_version : '' }} · {{ $device->last_seen_at ? __('vu :date', ['date' => $device->last_seen_at->diffForHumans()]) : __('jamais vu') }}</p>
                            </div>
                            <button type="button" wire:click="revokeDevice('{{ $device->uuid }}')" wire:confirm="{{ __('Déconnecter cet appareil ?') }}" class="text-[0.8125rem] font-semibold text-status-rejected hover:opacity-80">{{ __('Déconnecter') }}</button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-console.group>
    @endcan

    <x-console.slide-over model="acting"
        :title="match ($action) { 'suspended' => __('Suspendre le Relais'), 'struck_off' => __('Radier le Relais'), 'active' => __('Réintégrer le Relais'), 'transfer' => __('Transférer de commune'), default => '' }"
        :description="$action === 'transfer' ? __('Le Relais rejoint la file du coordonnateur de sa nouvelle commune.') : __('Le motif est inscrit au journal d’audit ; l’administration est informée si la décision vient d’un coordonnateur.')">
        <form id="relais-action" wire:submit="apply" class="space-y-4">
            @if ($action === 'transfer')
                <x-console.select :label="__('Nouvelle commune')" name="targetCommune" wire:model="targetCommune">
                    <option value="">{{ __('Choisir') }}</option>
                    @foreach ($this->communes as $commune)<option value="{{ $commune->uuid }}">{{ $commune->name }}</option>@endforeach
                </x-console.select>
            @endif
            <div>
                <label for="relais-reason" class="block text-[0.8125rem] font-medium">{{ __('Motif') }}</label>
                <textarea id="relais-reason" wire:model="reason" rows="3" class="{{ $field }}"></textarea>
                @error('reason') <p class="mt-1 text-xs font-medium text-status-rejected">{{ $message }}</p> @enderror
            </div>
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Annuler') }}</x-ui.button>
            <x-ui.button type="submit" form="relais-action" :variant="in_array($action, ['suspended', 'struck_off'], true) ? 'danger' : 'primary'">{{ __('Confirmer') }}</x-ui.button>
        </x-slot:footer>
    </x-console.slide-over>
</div>
