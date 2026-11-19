@php
    use App\Domain\Gains\Enums\PaymentMethod;
    use App\Livewire\Console\Gains\Index as GainsPage;

    $fcfa = fn (int $amount): string => number_format($amount, 0, ',', ' ').' FCFA';
    $date = fn ($value) => $value?->timezone('Africa/Abidjan')->translatedFormat('j M Y, H:i');
    $field = 'mt-1.5 block w-full rounded-[0.8rem] border-0 bg-black/[0.055] p-3.5 text-[0.9375rem] focus:bg-surface focus:shadow-[0_0_0_3px_rgb(10_132_255/0.3)] focus:outline-none dark:bg-white/[0.08]';
@endphp

<div class="space-y-8">
    <x-console.page-header
        :eyebrow="__('Administration')"
        :title="__('Paiements')"
        :subtitle="__('Commissions dues aux Relais pour leurs relais validés et diffusés. Versez la somme par Mobile Money, puis enregistrez le paiement avec sa référence : le Relais est prévenu.')"
    >
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="layers" :href="route('console.settings').'#groupe-gains'" wire:navigate>{{ __('Montants') }}</x-ui.button>
        </x-slot:actions>
    </x-console.page-header>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3" data-bento>
        <x-console.stat :label="__('À payer')" :value="$fcfa($this->stats['due_total'])" icon="bell" tint="orange" />
        <x-console.stat :label="__('Relais à payer')" value="" :count="$this->stats['due_relais']" icon="users" tint="blue" />
        <x-console.stat :label="__('Payé ce mois-ci')" :value="$fcfa($this->stats['paid_month'])" icon="check" tint="green" />
    </div>

    <div class="flex justify-center">
        <x-console.segmented property="tab" :current="$tab" :options="['a-payer' => __('À payer'), 'historique' => __('Historique')]" />
    </div>

    @if ($tab === 'historique')
        <x-console.group :title="trans_choice(':count opération|:count opérations', $this->history->total())">
            @if ($this->history->isEmpty())
                <x-console.empty icon="check" tint="green" :title="__('Aucun paiement pour le moment')" :text="__('Les paiements enregistrés et les commissions annulées apparaîtront ici.')" />
            @else
                <ul data-stagger>
                    @foreach ($this->history as $commission)
                        <li wire:key="hist-{{ $commission->uuid }}" class="flex flex-wrap items-center gap-x-4 gap-y-1 px-4 py-3">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-[0.9375rem] font-semibold">{{ $commission->user->name ?: __('Relais') }} · {{ $fcfa($commission->amount) }}</p>
                                <p class="truncate text-[0.8125rem] text-mute">
                                    {{ $commission->kind->label() }}@if ($commission->relaisItem) — {{ $commission->relaisItem->title ?: __('Sans titre') }}@endif
                                </p>
                                <p class="text-[0.8125rem] text-mute">
                                    @if ($commission->status->value === 'paid')
                                        {{ __('Payé le :date par :method, réf. :reference', ['date' => $date($commission->paid_at), 'method' => $commission->payment_method?->label(), 'reference' => $commission->payment_reference]) }}@if ($commission->payer) · {{ $commission->payer->name }}@endif
                                    @else
                                        {{ __('Annulé le :date : :reason', ['date' => $date($commission->cancelled_at), 'reason' => $commission->cancel_reason]) }}
                                    @endif
                                </p>
                            </div>
                            <x-console.badge :tone="$commission->status->value === 'paid' ? 'success' : 'neutral'">{{ $commission->status->label() }}</x-console.badge>
                        </li>
                    @endforeach
                </ul>
                @if ($this->history->hasPages())
                    <div class="flex items-center justify-end gap-1.5 border-t border-line px-4 py-3">
                        <x-ui.button size="sm" variant="secondary" wire:click="previousPage('historique')" :disabled="$this->history->onFirstPage()">{{ __('Précédent') }}</x-ui.button>
                        <x-ui.button size="sm" variant="secondary" wire:click="nextPage('historique')" :disabled="! $this->history->hasMorePages()">{{ __('Suivant') }}</x-ui.button>
                    </div>
                @endif
            @endif
        </x-console.group>
    @else
        <x-console.group :title="trans_choice(':count Relais à payer|:count Relais à payer', $this->due->total())" :footer="__('Le paiement exige une identité vérifiée (Vérifications d’identité). Toutes les commissions dues du Relais sont soldées en une fois.')">
            @if ($this->due->isEmpty())
                <x-console.empty icon="check" tint="green" :title="__('Rien à payer')" :text="__('Les commissions apparaissent ici quand un relais est validé ou diffusé, selon les montants fixés dans les Paramètres.')" />
            @else
                <ul data-stagger>
                    @foreach ($this->due as $relais)
                        @php $verified = GainsPage::isVerified($relais); @endphp
                        <li wire:key="due-{{ $relais->uuid }}" class="flex flex-wrap items-center gap-x-4 gap-y-2 px-4 py-3">
                            <div class="min-w-0 flex-1">
                                <p class="flex items-center gap-2 truncate text-[0.9375rem] font-semibold">
                                    {{ $relais->relaisProfile?->display_name ?? $relais->name ?: __('Relais') }}
                                    @if ($relais->relaisProfile?->number)<span class="font-normal text-mute">{{ $relais->relaisProfile->number }}</span>@endif
                                </p>
                                <p class="truncate text-[0.8125rem] text-mute">
                                    {{ collect([$relais->phoneNumber()?->e164, $relais->relaisProfile?->commune?->name, trans_choice(':count commission|:count commissions', $relais->due_count)])->filter()->join(' · ') }}
                                </p>
                            </div>
                            <x-console.badge :tone="$verified ? 'success' : 'warning'">{{ $verified ? __('Identité vérifiée') : __('Identité non vérifiée') }}</x-console.badge>
                            <span class="w-28 text-right text-[1rem] font-semibold tabular-nums">{{ $fcfa((int) $relais->due_total) }}</span>
                            <x-ui.button size="sm" :variant="$verified ? 'primary' : 'secondary'" wire:click="openPay('{{ $relais->uuid }}')">{{ $verified ? __('Payer') : __('Voir') }}</x-ui.button>
                        </li>
                    @endforeach
                </ul>
                @if ($this->due->hasPages())
                    <div class="flex items-center justify-end gap-1.5 border-t border-line px-4 py-3">
                        <x-ui.button size="sm" variant="secondary" wire:click="previousPage('dus')" :disabled="$this->due->onFirstPage()">{{ __('Précédent') }}</x-ui.button>
                        <x-ui.button size="sm" variant="secondary" wire:click="nextPage('dus')" :disabled="! $this->due->hasMorePages()">{{ __('Suivant') }}</x-ui.button>
                    </div>
                @endif
            @endif
        </x-console.group>
    @endif

    <x-console.slide-over model="paying" :title="__('Payer le Relais')" :description="__('Versez d’abord la somme par Mobile Money, puis enregistrez ici la référence de la transaction.')">
        @if ($this->payee)
            @php $verified = GainsPage::isVerified($this->payee); $total = (int) $this->payeeCommissions->sum('amount'); @endphp
            <div class="space-y-4">
                <div class="rounded-[1rem] bg-black/[0.035] p-4 dark:bg-white/[0.05]">
                    <p class="text-[0.8125rem] text-mute">{{ $this->payee->name ?: __('Relais') }}</p>
                    <p class="text-[1.75rem] font-bold tracking-[-0.02em] tabular-nums">{{ $fcfa($total) }}</p>
                    <ul class="mt-2 divide-y divide-line text-[0.8125rem]">
                        @foreach ($this->payeeCommissions as $commission)
                            <li wire:key="pc-{{ $commission->uuid }}" class="flex items-center gap-3 py-1.5">
                                <span class="min-w-0 flex-1 truncate">{{ $commission->kind->label() }} — {{ $commission->relaisItem?->title ?: __('Sans titre') }}</span>
                                <span class="tabular-nums">{{ $fcfa($commission->amount) }}</span>
                                <button type="button" wire:click="askCancel('{{ $commission->uuid }}')" class="text-status-rejected hover:opacity-80" aria-label="{{ __('Annuler cette commission') }}">{{ __('Annuler') }}</button>
                            </li>
                        @endforeach
                    </ul>
                </div>

                @if (! $verified)
                    <p class="rounded-[0.8rem] bg-status-pending/12 px-3 py-2 text-[0.875rem]">{{ __('Identité non vérifiée : le paiement est bloqué tant que la vérification d’identité n’est pas validée.') }}</p>
                @else
                    <form id="pay-form" wire:submit="pay" class="space-y-4">
                        <x-console.select :label="__('Moyen de paiement')" name="method" wire:model="method">
                            @foreach (PaymentMethod::cases() as $case)<option value="{{ $case->value }}">{{ $case->label() }}</option>@endforeach
                        </x-console.select>
                        <x-console.input :label="__('Numéro crédité')" name="phone" wire:model="phone" placeholder="+225…" />
                        <x-console.input :label="__('Référence de la transaction')" name="reference" wire:model="reference" :placeholder="__('Ex. : identifiant Wave ou Orange Money')" />
                    </form>
                @endif
            </div>
        @endif
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Fermer') }}</x-ui.button>
            @if ($this->payee && GainsPage::isVerified($this->payee))
                <x-ui.button type="submit" form="pay-form" wire:loading.attr="disabled" wire:target="pay">{{ __('Enregistrer le paiement') }}</x-ui.button>
            @endif
        </x-slot:footer>
    </x-console.slide-over>

    <x-console.slide-over model="cancelling" :title="__('Annuler la commission')" :description="__('Le motif est inscrit au journal d’audit. Une commission payée ne peut pas être annulée.')">
        <form id="cancel-form" wire:submit="cancel">
            <label for="cancel-reason" class="block text-[0.8125rem] font-medium">{{ __('Motif') }}</label>
            <textarea id="cancel-reason" wire:model="cancelReason" rows="3" class="{{ $field }}"></textarea>
            @error('cancelReason') <p class="mt-1 text-xs font-medium text-status-rejected">{{ $message }}</p> @enderror
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Retour') }}</x-ui.button>
            <x-ui.button type="submit" form="cancel-form" variant="danger">{{ __('Annuler la commission') }}</x-ui.button>
        </x-slot:footer>
    </x-console.slide-over>
</div>
