@php
    use App\Domain\Notification\Enums\AnnouncementAudience;
    use App\Domain\Notification\Enums\AnnouncementScreen;

    $field = 'mt-1.5 block w-full rounded-[0.8rem] border-0 bg-black/[0.055] p-3.5 text-[0.9375rem] focus:bg-surface focus:shadow-[0_0_0_3px_rgb(10_132_255/0.3)] focus:outline-none dark:bg-white/[0.08]';
    $date = fn ($value) => $value?->timezone('Africa/Abidjan')->translatedFormat('j M Y, H:i');
    $statusTone = ['scheduled' => 'info', 'sending' => 'accent', 'sent' => 'success', 'cancelled' => 'neutral'];
    $estimate = $this->estimate;
    $smsOnly = max(0, $estimate['total'] - $estimate['push']);
@endphp

<div class="space-y-8">
    <x-console.page-header
        :eyebrow="__('Réseau')"
        :title="__('Notifications push')"
        :subtitle="__('Envoyez une annonce aux Relais : notification push sur leur téléphone, SMS de secours si besoin, et message gardé dans l’application.')"
    />

    @unless ($this->pushReady)
        <div class="flex items-start gap-3 rounded-[1rem] bg-status-pending/12 px-4 py-3 text-[0.875rem]" role="status">
            <x-ui.icon name="bell" class="mt-0.5 size-4 shrink-0" />
            <p>{{ __('Les notifications push ne sont pas encore actives (clé Firebase absente du serveur). Les annonces restent visibles dans l’application ; activez le SMS de secours pour joindre les Relais dès maintenant.') }}</p>
        </div>
    @endunless

    <div class="grid gap-6 lg:grid-cols-[1fr_20rem]">
        <form wire:submit="review" class="space-y-6">
            <x-console.group :title="__('Message')">
                <div class="space-y-4 p-4">
                    <div>
                        <div class="flex items-end justify-between">
                            <label for="ann-title" class="block text-[0.8125rem] font-medium">{{ __('Titre') }}</label>
                            <span class="text-[0.75rem] text-mute tabular-nums" x-text="$wire.title.length + ' / 80'"></span>
                        </div>
                        <input id="ann-title" type="text" maxlength="80" wire:model.live.debounce.300ms="title" class="{{ $field }}" placeholder="{{ __('Ex. : Inondations à Abidjan') }}">
                        @error('title') <p class="mt-1 text-xs font-medium text-status-rejected">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <div class="flex items-end justify-between">
                            <label for="ann-body" class="block text-[0.8125rem] font-medium">{{ __('Message') }}</label>
                            <span class="text-[0.75rem] text-mute tabular-nums" x-text="$wire.body.length + ' / 500'"></span>
                        </div>
                        <textarea id="ann-body" rows="4" maxlength="500" wire:model.live.debounce.300ms="body" class="{{ $field }}" placeholder="{{ __('Ex. : Filmez les quartiers touchés, en restant à l’abri. Merci !') }}"></textarea>
                        @error('body') <p class="mt-1 text-xs font-medium text-status-rejected">{{ $message }}</p> @enderror
                    </div>
                    <x-console.select :label="__('Écran ouvert quand le Relais touche la notification')" name="screen" wire:model="screen">
                        @foreach (AnnouncementScreen::cases() as $case)<option value="{{ $case->value }}">{{ $case->label() }}</option>@endforeach
                    </x-console.select>
                </div>
            </x-console.group>

            <x-console.group :title="__('Destinataires')">
                <div class="space-y-4 p-4">
                    <x-console.select :label="__('Envoyer à')" name="audience" wire:model.live="audience">
                        @foreach (AnnouncementAudience::cases() as $case)<option value="{{ $case->value }}">{{ $case->label() }}</option>@endforeach
                    </x-console.select>
                    @error('audience') <p class="text-xs font-medium text-status-rejected">{{ $message }}</p> @enderror

                    @if ($audience === 'regions')
                        <fieldset>
                            <legend class="text-[0.8125rem] font-medium">{{ __('Régions') }}</legend>
                            <div class="mt-2 grid max-h-64 grid-cols-1 gap-1 overflow-y-auto sm:grid-cols-2">
                                @foreach ($this->regions as $region)
                                    <label wire:key="region-{{ $region->uuid }}" class="flex items-center gap-2.5 rounded-[0.6rem] px-2 py-1.5 text-[0.9375rem] hover:bg-black/[0.03]">
                                        <input type="checkbox" value="{{ $region->uuid }}" wire:model.live="regionIds" class="ios-check">{{ $region->name }}
                                    </label>
                                @endforeach
                            </div>
                            @error('regionIds') <p class="mt-1 text-xs font-medium text-status-rejected">{{ $message }}</p> @enderror
                        </fieldset>
                    @elseif ($audience === 'communes')
                        <fieldset>
                            <legend class="text-[0.8125rem] font-medium">{{ __('Communes') }} @if (count($communeIds))<span class="text-mute">· {{ trans_choice(':count choisie|:count choisies', count($communeIds)) }}</span>@endif</legend>
                            <input type="search" wire:model.live.debounce.250ms="communeSearch" placeholder="{{ __('Rechercher une commune') }}" class="{{ $field }}">
                            <div class="mt-2 grid max-h-64 grid-cols-1 gap-1 overflow-y-auto sm:grid-cols-2">
                                @foreach ($this->communes as $commune)
                                    <label wire:key="commune-{{ $commune->uuid }}" class="flex items-center gap-2.5 rounded-[0.6rem] px-2 py-1.5 text-[0.9375rem] hover:bg-black/[0.03]">
                                        <input type="checkbox" value="{{ $commune->uuid }}" wire:model.live="communeIds" class="ios-check">{{ $commune->name }}
                                    </label>
                                @endforeach
                            </div>
                            @error('communeIds') <p class="mt-1 text-xs font-medium text-status-rejected">{{ $message }}</p> @enderror
                        </fieldset>
                    @elseif ($audience === 'relais')
                        <div>
                            @if ($relais = $this->selectedRelais)
                                <div class="flex items-center justify-between gap-3 rounded-[0.8rem] bg-black/[0.035] px-3.5 py-2.5 dark:bg-white/[0.05]">
                                    <span class="min-w-0 truncate text-[0.9375rem]"><span class="font-semibold">{{ $relais->display_name }}</span> <span class="text-mute">{{ collect([$relais->number, $relais->commune?->name])->filter()->join(' · ') }}</span></span>
                                    <button type="button" wire:click="$set('relaisUuid', '')" class="text-[0.875rem] font-medium text-tile-blue hover:opacity-80">{{ __('Changer') }}</button>
                                </div>
                            @else
                                <label for="ann-relais" class="block text-[0.8125rem] font-medium">{{ __('Relais') }}</label>
                                <input id="ann-relais" type="search" wire:model.live.debounce.250ms="relaisSearch" placeholder="{{ __('Nom, numéro de Relais ou téléphone') }}" class="{{ $field }}">
                                @if ($this->relaisMatches->isNotEmpty())
                                    <ul class="mt-2 divide-y divide-line overflow-hidden rounded-[0.8rem] ring-1 ring-line">
                                        @foreach ($this->relaisMatches as $match)
                                            <li wire:key="match-{{ $match->id }}">
                                                <button type="button" wire:click="chooseRelais('{{ $match->user->uuid }}')" class="flex w-full items-center justify-between gap-3 px-3.5 py-2.5 text-left text-[0.9375rem] hover:bg-black/[0.03]">
                                                    <span class="font-medium">{{ $match->display_name }}</span>
                                                    <span class="text-[0.8125rem] text-mute">{{ collect([$match->number, $match->commune?->name])->filter()->join(' · ') }}</span>
                                                </button>
                                            </li>
                                        @endforeach
                                    </ul>
                                @elseif (mb_strlen(trim($relaisSearch)) >= 2)
                                    <p class="mt-2 text-[0.8125rem] text-mute">{{ __('Aucun Relais actif ne correspond.') }}</p>
                                @endif
                                @error('relaisUuid') <p class="mt-1 text-xs font-medium text-status-rejected">{{ $message }}</p> @enderror
                            @endif
                        </div>
                    @endif

                    <div class="rounded-[0.9rem] bg-black/[0.035] p-3.5 text-[0.875rem] dark:bg-white/[0.05]" wire:loading.class="opacity-60">
                        <p class="text-[1.0625rem] font-semibold">{{ trans_choice(':count Relais|:count Relais', $estimate['total']) }}</p>
                        <p class="mt-0.5 text-mute">
                            {{ trans_choice(':count joignable par notification push|:count joignables par notification push', $estimate['push']) }}
                            · {{ trans_choice(':count sans application joignable|:count sans application joignable', $smsOnly) }}
                        </p>
                    </div>

                    <x-console.toggle :label="__('SMS de secours')" name="withSms" wire:model.live="withSms"
                        :description="trans_choice('Pour le Relais sans application joignable : :count SMS au plus.|Pour les Relais sans application joignable : :count SMS au plus (facturés).', $smsOnly)" />
                </div>
            </x-console.group>

            <x-console.group :title="__('Envoi')">
                <div class="space-y-4 p-4">
                    <x-console.segmented property="when" :current="$when" :options="['now' => __('Tout de suite'), 'later' => __('Programmer')]" />
                    @if ($when === 'later')
                        <x-console.input :label="__('Date et heure d’envoi (heure d’Abidjan)')" type="datetime-local" name="scheduledAt" wire:model="scheduledAt" />
                    @endif
                    <div class="flex justify-end">
                        <x-ui.button type="submit" icon="bell" wire:loading.attr="disabled" wire:target="review">{{ $when === 'later' ? __('Programmer l’annonce') : __('Envoyer l’annonce') }}</x-ui.button>
                    </div>
                </div>
            </x-console.group>
        </form>

        {{-- Aperçu façon écran verrouillé --}}
        <div class="lg:sticky lg:top-6 lg:self-start">
            <p class="mb-2 px-1 text-[0.8125rem] font-semibold text-mute">{{ __('Aperçu') }}</p>
            <div class="rounded-[2rem] bg-[#1c1c1e] p-4 pt-10 shadow-[0_20px_60px_-25px_rgb(0_0_0/0.5)]">
                <p class="text-center text-[2.5rem] leading-none font-light text-white/90 tabular-nums">{{ now()->timezone('Africa/Abidjan')->format('H:i') }}</p>
                <div class="mt-8 rounded-[1.1rem] bg-white/80 p-3 backdrop-blur-xl dark:bg-white/70">
                    <div class="flex items-center gap-2 text-[0.6875rem] text-black/55">
                        <span class="grid size-5 place-items-center rounded-[0.35rem] bg-relais-500 text-white"><x-ui.icon name="signal" class="size-3" /></span>
                        <span class="font-semibold tracking-wide uppercase">JE RELAIS</span>
                        <span class="ml-auto">{{ __('maintenant') }}</span>
                    </div>
                    <p class="mt-1.5 text-[0.875rem] leading-snug font-semibold text-black">{{ $title !== '' ? $title : __('Titre de l’annonce') }}</p>
                    <p class="mt-0.5 line-clamp-4 text-[0.8125rem] leading-snug text-black/80">{{ $body !== '' ? $body : __('Votre message apparaîtra ici.') }}</p>
                </div>
            </div>
        </div>
    </div>

    <x-console.group :title="__('Annonces envoyées')">
        @if ($this->history->isEmpty())
            <x-console.empty icon="bell" tint="orange" :title="__('Aucune annonce pour le moment')" :text="__('Les annonces envoyées ou programmées apparaîtront ici avec leurs résultats.')" />
        @else
            <ul data-stagger>
                @foreach ($this->history as $announcement)
                    <li wire:key="ann-{{ $announcement->uuid }}" class="flex flex-wrap items-start gap-x-4 gap-y-2 px-4 py-3">
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-2 text-[0.9375rem] font-semibold">
                                <span class="truncate">{{ $announcement->title }}</span>
                                <x-console.badge :tone="$statusTone[$announcement->status->value] ?? 'neutral'">{{ $announcement->status->label() }}</x-console.badge>
                            </p>
                            <p class="line-clamp-1 text-[0.8125rem] text-mute">{{ $announcement->body }}</p>
                            <p class="mt-1 text-[0.8125rem] text-mute">
                                {{ $announcement->audience->label() }}
                                · {{ $announcement->status->value === 'scheduled' ? __('prévue le :date', ['date' => $date($announcement->scheduled_at)]) : $date($announcement->sent_at ?? $announcement->created_at) }}
                                @if ($announcement->author) · {{ $announcement->author->name }}@endif
                            </p>
                            @if (in_array($announcement->status->value, ['sending', 'sent'], true))
                                <p class="mt-1 text-[0.8125rem]">
                                    {{ trans_choice(':count destinataire|:count destinataires', $announcement->recipients_count) }}
                                    · <span class="text-status-validated">{{ __(':count push', ['count' => $announcement->push_count]) }}</span>
                                    · {{ __(':count SMS', ['count' => $announcement->sms_count]) }}
                                    @if ($announcement->unreached_count > 0) · <span class="text-mute">{{ __(':count dans l’application seulement', ['count' => $announcement->unreached_count]) }}</span>@endif
                                    · {{ trans_choice(':count lu|:count lus', $announcement->read_count) }}
                                </p>
                            @endif
                        </div>
                        @if ($announcement->status->value === 'scheduled')
                            <x-ui.button size="sm" variant="ghost" class="text-status-rejected!" wire:click="cancel('{{ $announcement->uuid }}')" wire:confirm="{{ __('Annuler cette annonce programmée ?') }}">{{ __('Annuler') }}</x-ui.button>
                        @endif
                    </li>
                @endforeach
            </ul>
            @if ($this->history->hasPages())
                <div class="flex items-center justify-end gap-1.5 border-t border-line px-4 py-3">
                    <x-ui.button size="sm" variant="secondary" wire:click="previousPage" :disabled="$this->history->onFirstPage()">{{ __('Précédent') }}</x-ui.button>
                    <x-ui.button size="sm" variant="secondary" wire:click="nextPage" :disabled="! $this->history->hasMorePages()">{{ __('Suivant') }}</x-ui.button>
                </div>
            @endif
        @endif
    </x-console.group>

    <x-console.confirm
        model="confirming"
        action="send"
        tone="primary"
        :title="$when === 'later' ? __('Programmer l’annonce ?') : __('Envoyer l’annonce ?')"
        :text="trans_choice('Elle partira vers :count Relais.|Elle partira vers :count Relais.', $estimate['total']).($withSms ? ' '.trans_choice('Jusqu’à :count SMS de secours.|Jusqu’à :count SMS de secours.', $smsOnly) : '')"
        :confirm-label="$when === 'later' ? __('Programmer') : __('Envoyer')"
    />
</div>
