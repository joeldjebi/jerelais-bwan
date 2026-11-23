@php
    use App\Domain\Gains\Enums\PaymentMethod;
    use App\Domain\Gains\Support\Wallet;
    use App\Livewire\Console\Gains\Index as GainsPage;

    $fcfa = fn (int $amount): string => Wallet::fcfa($amount);
    $date = fn ($value) => $value?->timezone('Africa/Abidjan')->translatedFormat('j M Y, H:i');
    $field = 'mt-1.5 block w-full rounded-[0.8rem] border-0 bg-black/[0.055] p-3.5 text-[0.9375rem] focus:bg-surface focus:shadow-[0_0_0_3px_rgb(10_132_255/0.3)] focus:outline-none dark:bg-white/[0.08]';
    $statusTone = ['requested' => 'warning', 'paid' => 'success', 'rejected' => 'neutral'];
@endphp

<div class="space-y-8">
    <x-console.page-header
        :eyebrow="__('Administration')"
        :title="__('Paiements')"
        :subtitle="__('La rémunération de chaque relais validé est décidée dans « Rémunérations » et créditée sur le portefeuille du Relais. Le Relais demande un retrait depuis l’application : versez la somme par Mobile Money, puis enregistrez la référence de la transaction. Il est prévenu.')"
    >
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="cart" :href="route('console.rewards')" wire:navigate>{{ __('Rémunérations') }}</x-ui.button>
        </x-slot:actions>
    </x-console.page-header>

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4" data-bento>
        <x-console.stat :label="__('Retraits à payer')" :value="$fcfa($this->stats['pending_total'])" icon="bell" tint="orange" :hint="trans_choice(':count demande|:count demandes', $this->stats['pending_count'])" />
        <x-console.stat :label="__('Payé ce mois-ci')" :value="$fcfa($this->stats['paid_month'])" icon="check" tint="green" />
        <x-console.stat :label="__('Dans les portefeuilles')" :value="$fcfa($this->stats['balances_total'])" icon="cart" tint="blue" :hint="__('Gagné, pas encore retiré')" />
        <x-console.stat :label="__('Demandes en attente')" value="" :count="$this->stats['pending_count']" icon="clock" :tint="$this->stats['pending_count'] > 0 ? 'pink' : 'gray'" />
    </div>

    <div class="flex justify-center">
        <x-console.segmented property="tab" :current="$tab" :options="['demandes' => __('Demandes de retrait'), 'historique' => __('Historique'), 'soldes' => __('Portefeuilles'), 'annulees' => __('Gains annulés')]" />
    </div>

    @if ($tab === 'demandes')
        <x-console.group :title="trans_choice(':count demande de retrait|:count demandes de retrait', $this->requests->total())" :footer="__('La plus ancienne d’abord. Le Relais choisit son opérateur et son numéro dans l’application.')">
            @if ($this->requests->isEmpty())
                <x-console.empty icon="check" tint="green" :title="__('Aucune demande de retrait')" :text="__('Les demandes envoyées par les Relais depuis l’application apparaîtront ici.')" />
            @else
                <ul data-stagger>
                    @foreach ($this->requests as $payout)
                        <li wire:key="req-{{ $payout->uuid }}" class="flex flex-wrap items-center gap-x-4 gap-y-2 px-4 py-3">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-[0.9375rem] font-semibold">{{ $payout->user->name ?: __('Relais') }}</p>
                                <p class="truncate text-[0.8125rem] text-mute">{{ $payout->method->label() }} · {{ $payout->phone }} · {{ $payout->holder_name }}</p>
                                <p class="text-[0.8125rem] text-mute">{{ __('Demandé le :date', ['date' => $date($payout->requested_at)]) }}</p>
                            </div>
                            @unless (GainsPage::isVerified($payout->user))
                                <x-console.badge tone="warning">{{ __('Identité non vérifiée') }}</x-console.badge>
                            @endunless
                            <span class="w-28 text-right text-[1rem] font-semibold tabular-nums">{{ $fcfa($payout->amount) }}</span>
                            <x-ui.button size="sm" variant="ghost" class="text-status-rejected!" wire:click="openReject('{{ $payout->uuid }}')">{{ __('Refuser') }}</x-ui.button>
                            <x-ui.button size="sm" wire:click="openPay('{{ $payout->uuid }}')">{{ __('Payer') }}</x-ui.button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-console.group>
    @elseif ($tab === 'historique')
        <x-console.group :title="trans_choice(':count retrait traité|:count retraits traités', $this->history->total())">
            @if ($this->history->isEmpty())
                <x-console.empty icon="check" tint="green" :title="__('Aucun retrait traité')" :text="__('Les retraits payés ou refusés apparaîtront ici, avec leur référence ou leur motif.')" />
            @else
                <ul data-stagger>
                    @foreach ($this->history as $payout)
                        <li wire:key="hist-{{ $payout->uuid }}" class="flex flex-wrap items-center gap-x-4 gap-y-1 px-4 py-3">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-[0.9375rem] font-semibold">{{ $payout->user->name ?: __('Relais') }}</p>
                                <p class="text-[0.8125rem] text-mute">
                                    @if ($payout->status->value === 'paid')
                                        {{ __(':date · :method, réf. :reference', ['date' => $date($payout->paid_at), 'method' => $payout->method->label(), 'reference' => $payout->reference]) }}
                                    @else
                                        {{ __('Refusé le :date : :reason', ['date' => $date($payout->rejected_at), 'reason' => $payout->rejection_reason]) }}
                                    @endif
                                    @if ($payout->payer) · {{ __('par :name', ['name' => $payout->payer->name]) }}@endif
                                </p>
                            </div>
                            <x-console.badge :tone="$statusTone[$payout->status->value]">{{ $payout->status->label() }}</x-console.badge>
                            <span class="w-28 text-right text-[1rem] font-semibold tabular-nums">{{ $fcfa($payout->amount) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-console.group>
    @elseif ($tab === 'soldes')
        <x-console.group :title="trans_choice(':count portefeuille|:count portefeuilles', $this->balances->total())" :footer="__('Gagné : total des rémunérations accordées. Disponible : ce que le Relais peut encore retirer.')">
            @if ($this->balances->isEmpty())
                <x-console.empty icon="cart" tint="blue" :title="__('Aucun gain pour le moment')" :text="__('Les portefeuilles se remplissent quand un relais validé est rémunéré (page « Rémunérations »).')" />
            @else
                <ul data-stagger>
                    @foreach ($this->balances as $holder)
                        @php $available = max(0, (int) $holder->earned_total - (int) $holder->withdrawn_total); @endphp
                        <li wire:key="bal-{{ $holder->uuid }}" class="flex flex-wrap items-center gap-x-4 gap-y-2 px-4 py-3">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-[0.9375rem] font-semibold">{{ $holder->relaisProfile?->display_name ?? $holder->name }} <span class="font-normal text-mute">{{ $holder->relaisProfile?->number }}</span></p>
                                <p class="truncate text-[0.8125rem] text-mute">
                                    {{ $holder->payoutAccount ? $holder->payoutAccount->method->label().' · '.$holder->payoutAccount->phone : __('Coordonnées de paiement non renseignées') }}
                                    · {{ GainsPage::isVerified($holder) ? __('identité vérifiée') : __('identité non vérifiée') }}
                                </p>
                            </div>
                            <span class="text-right text-[0.8125rem] text-mute tabular-nums">{{ __('Gagné :amount', ['amount' => $fcfa((int) $holder->earned_total)]) }}</span>
                            <span class="w-32 text-right text-[1rem] font-semibold tabular-nums">{{ $fcfa($available) }}</span>
                            <x-ui.button size="sm" variant="secondary" wire:click="openHolder('{{ $holder->uuid }}')">{{ __('Détail') }}</x-ui.button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-console.group>
    @else
        <x-console.group :title="trans_choice(':count gain annulé|:count gains annulés', $this->cancelled->total())">
            @if ($this->cancelled->isEmpty())
                <x-console.empty icon="check" tint="gray" :title="__('Aucun gain annulé')" :text="__('Les gains annulés (relais rejeté, doublon…) apparaissent ici avec leur motif.')" />
            @else
                <ul data-stagger>
                    @foreach ($this->cancelled as $commission)
                        <li wire:key="cancel-{{ $commission->uuid }}" class="flex flex-wrap items-center gap-x-4 gap-y-1 px-4 py-3">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-[0.9375rem] font-semibold">{{ $commission->user->name ?: __('Relais') }} · {{ $fcfa($commission->amount) }}</p>
                                <p class="truncate text-[0.8125rem] text-mute">{{ $commission->relaisItem?->title ?: __('Sans titre') }}</p>
                                <p class="text-[0.8125rem] text-mute">{{ __('Annulé le :date : :reason', ['date' => $date($commission->cancelled_at), 'reason' => $commission->cancel_reason]) }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-console.group>
    @endif

    @php $list = match ($tab) { 'demandes' => $this->requests, 'historique' => $this->history, 'soldes' => $this->balances, default => $this->cancelled }; @endphp
    @if ($list->hasPages())
        <div class="flex items-center justify-end gap-1.5">
            <x-ui.button size="sm" variant="secondary" wire:click="previousPage('liste')" :disabled="$list->onFirstPage()">{{ __('Précédent') }}</x-ui.button>
            <x-ui.button size="sm" variant="secondary" wire:click="nextPage('liste')" :disabled="! $list->hasMorePages()">{{ __('Suivant') }}</x-ui.button>
        </div>
    @endif

    <x-console.slide-over model="paying" :title="__('Payer le retrait')" :description="__('Versez d’abord la somme par Mobile Money au numéro indiqué, puis enregistrez ici la référence de la transaction.')">
        @if ($payout = $this->payout)
            <div class="space-y-4">
                <div class="rounded-[1rem] bg-black/[0.035] p-4 dark:bg-white/[0.05]">
                    <p class="text-[0.8125rem] text-mute">{{ $payout->user->name }} · {{ __('titulaire : :name', ['name' => $payout->holder_name]) }}</p>
                    <p class="text-[1.75rem] font-bold tracking-[-0.02em] tabular-nums">{{ $fcfa($payout->amount) }}</p>
                </div>
                @unless (GainsPage::isVerified($payout->user))
                    <p class="rounded-[0.8rem] bg-status-pending/12 px-3 py-2 text-[0.875rem]">{{ __('Attention : l’identité de ce Relais n’est plus vérifiée.') }}</p>
                @endunless
                <form id="pay-form" wire:submit="pay" class="space-y-4">
                    <x-console.select :label="__('Moyen de paiement')" name="method" wire:model="method">
                        @foreach (PaymentMethod::cases() as $case)<option value="{{ $case->value }}">{{ $case->label() }}</option>@endforeach
                    </x-console.select>
                    <x-console.input :label="__('Numéro crédité')" name="phone" wire:model="phone" />
                    <x-console.input :label="__('Référence de la transaction')" name="reference" wire:model="reference" :placeholder="__('Ex. : identifiant Wave ou Orange Money')" />
                </form>
            </div>
        @endif
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Fermer') }}</x-ui.button>
            <x-ui.button type="submit" form="pay-form" wire:loading.attr="disabled" wire:target="pay">{{ __('Enregistrer le paiement') }}</x-ui.button>
        </x-slot:footer>
    </x-console.slide-over>

    <x-console.slide-over model="rejecting" :title="__('Refuser le retrait')" :description="__('La somme revient dans le portefeuille du Relais, qui est prévenu. Le motif est inscrit au journal d’audit.')">
        <form id="reject-form" wire:submit="reject">
            <label for="reject-reason" class="block text-[0.8125rem] font-medium">{{ __('Motif') }}</label>
            <textarea id="reject-reason" wire:model="rejectReason" rows="3" class="{{ $field }}" placeholder="{{ __('Ex. : numéro Mobile Money injoignable, merci de le corriger.') }}"></textarea>
            @error('rejectReason') <p class="mt-1 text-xs font-medium text-status-rejected">{{ $message }}</p> @enderror
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Retour') }}</x-ui.button>
            <x-ui.button type="submit" form="reject-form" variant="danger">{{ __('Refuser le retrait') }}</x-ui.button>
        </x-slot:footer>
    </x-console.slide-over>

    <x-console.slide-over model="viewing" :title="__('Portefeuille')" wide>
        @if ($holder = $this->holder)
            @php $wallet = $this->walletOf($holder); @endphp
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    @foreach ([__('Gagné') => $wallet['earned'], __('Disponible') => $wallet['available'], __('En cours') => $wallet['pending'], __('Retiré') => $wallet['withdrawn']] as $label => $amount)
                        <div class="rounded-[0.9rem] bg-black/[0.035] p-3 dark:bg-white/[0.05]">
                            <p class="text-[0.75rem] text-mute">{{ $label }}</p>
                            <p class="text-[1.0625rem] font-semibold tabular-nums">{{ $fcfa($amount) }}</p>
                        </div>
                    @endforeach
                </div>
                <ul class="divide-y divide-line rounded-[0.9rem] ring-1 ring-line">
                    @foreach ($this->holderCommissions as $commission)
                        <li wire:key="hc-{{ $commission->uuid }}" class="flex items-center gap-3 px-3.5 py-2.5 text-[0.875rem]">
                            <span class="min-w-0 flex-1 truncate">{{ $commission->relaisItem?->title ?: __('Sans titre') }} <span class="text-mute">· {{ $date($commission->created_at) }}</span></span>
                            <span @class(['tabular-nums', 'text-mute line-through' => $commission->status->value === 'cancelled'])>{{ $fcfa($commission->amount) }}</span>
                            @if ($commission->status->value === 'due')
                                <button type="button" wire:click="askCancel('{{ $commission->uuid }}')" class="text-status-rejected hover:opacity-80">{{ __('Annuler') }}</button>
                            @else
                                <span class="w-14 text-right text-[0.75rem] text-mute">{{ $commission->status->label() }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Fermer') }}</x-ui.button>
        </x-slot:footer>
    </x-console.slide-over>

    <x-console.slide-over model="cancelling" :title="__('Annuler le gain')" :description="__('Possible tant que la somme n’a pas été retirée. Le motif est inscrit au journal d’audit et le Relais est prévenu.')">
        <form id="cancel-form" wire:submit="cancel">
            <label for="cancel-reason" class="block text-[0.8125rem] font-medium">{{ __('Motif') }}</label>
            <textarea id="cancel-reason" wire:model="cancelReason" rows="3" class="{{ $field }}"></textarea>
            @error('cancelReason') <p class="mt-1 text-xs font-medium text-status-rejected">{{ $message }}</p> @enderror
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Retour') }}</x-ui.button>
            <x-ui.button type="submit" form="cancel-form" variant="danger">{{ __('Annuler le gain') }}</x-ui.button>
        </x-slot:footer>
    </x-console.slide-over>
</div>
