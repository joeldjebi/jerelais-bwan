@php
    $field = 'block w-full rounded-[0.8rem] border-0 bg-black/[0.055] p-3.5 text-[0.9375rem] focus:bg-surface focus:shadow-[0_0_0_3px_rgb(10_132_255/0.3)] focus:outline-none dark:bg-white/[0.08]';
    $current = $this->current;
@endphp

<div class="space-y-8" wire:poll.10s.keep-alive>
    <x-console.page-header :eyebrow="__('Réseau')" :title="__('Messagerie')" :subtitle="__('Échangez avec les Relais de votre périmètre, à propos d’un relais ou en général.')">
        <x-slot:actions>
            <x-ui.button icon="bell" wire:click="$set('broadcasting', true)">{{ __('Message à tous les Relais') }}</x-ui.button>
        </x-slot:actions>
    </x-console.page-header>

    <div class="grid min-h-[34rem] overflow-hidden rounded-[1.6rem] bg-surface shadow-[0_0_0_0.5px_rgb(0_0_0/0.06),0_2px_12px_-4px_rgb(0_0_0/0.06)] lg:grid-cols-[20rem_1fr]" data-enter>
        <ul class="max-h-[40rem] overflow-y-auto border-r border-line">
            @forelse ($this->conversations as $conversation)
                @php $last = $conversation->messages->first(); $unread = $last && ! $last->from_staff && ($conversation->staff_read_at === null || $conversation->staff_read_at->lt($conversation->last_message_at)); @endphp
                <li wire:key="conv-{{ $conversation->uuid }}">
                    <button type="button" wire:click="select('{{ $conversation->uuid }}')" @class(['flex w-full items-start gap-3 px-4 py-3 text-left transition-colors', 'bg-tile-blue/10' => $selected === $conversation->uuid, 'hover:bg-black/[0.03]' => $selected !== $conversation->uuid])>
                        <span class="grid size-10 shrink-0 place-items-center rounded-full bg-relais-500 text-[0.8125rem] font-semibold text-white">{{ Str::upper(Str::substr($conversation->relaisProfile->display_name, 0, 1)) }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="flex items-center justify-between gap-2">
                                <span @class(['truncate text-[0.9375rem]', 'font-bold' => $unread, 'font-semibold' => ! $unread])>{{ $conversation->relaisProfile->display_name }}</span>
                                <span class="shrink-0 text-[0.75rem] text-mute">{{ $conversation->last_message_at?->diffForHumans(short: true) }}</span>
                            </span>
                            <span class="block truncate text-[0.75rem] text-mute">{{ $conversation->relaisItem?->title ?? __('Conversation générale') }} · {{ $conversation->commune?->name }}</span>
                            <span @class(['mt-0.5 block truncate text-[0.8125rem]', 'text-ink' => $unread, 'text-mute' => ! $unread])>{{ $last?->from_staff ? __('Vous : ') : '' }}{{ $last?->body }}</span>
                        </span>
                        @if ($unread)<span class="mt-2 size-2.5 shrink-0 rounded-full bg-tile-blue"></span>@endif
                    </button>
                </li>
            @empty
                <li><x-console.empty icon="inbox" tint="blue" :title="__('Aucune conversation')" :text="__('Les messages des Relais apparaîtront ici.')" /></li>
            @endforelse
        </ul>

        <section class="flex min-h-0 flex-col">
            @if ($current)
                <header class="border-b border-line px-6 py-4">
                    <p class="text-[1.0625rem] font-semibold">{{ $current->relaisProfile->display_name }} <span class="text-[0.8125rem] font-normal text-mute">· {{ $current->relaisProfile->number }}</span></p>
                    <p class="text-[0.8125rem] text-mute">
                        @if ($current->relaisItem)
                            <a href="{{ route('console.validation.show', $current->relaisItem) }}" wire:navigate class="text-tile-blue hover:opacity-80">{{ $current->relaisItem->title ?: __('Relais') }}</a>
                        @else
                            {{ __('Conversation générale') }}
                        @endif
                        · {{ $current->commune?->name }}
                    </p>
                </header>
                <ol class="flex-1 space-y-3 overflow-y-auto px-6 py-5" x-init="$el.scrollTop = $el.scrollHeight">
                    @foreach ($current->messages as $message)
                        <li @class(['flex', 'justify-end' => $message->from_staff]) wire:key="msg-{{ $message->uuid }}">
                            <div @class(['max-w-[75%] rounded-[1.2rem] px-4 py-2.5 text-[0.9375rem] leading-relaxed', 'bg-tile-blue text-white' => $message->from_staff, 'bg-black/[0.06] dark:bg-white/[0.08]' => ! $message->from_staff])>
                                <p class="whitespace-pre-line">{{ $message->body }}</p>
                                <p @class(['mt-1 text-[0.6875rem]', 'text-white/70' => $message->from_staff, 'text-mute' => ! $message->from_staff])>
                                    {{ $message->from_staff ? $message->sender->name : $current->relaisProfile->display_name }} · {{ $message->created_at->timezone('Africa/Abidjan')->format('d/m H:i') }}@if ($message->broadcast_uuid) · {{ __('message groupé') }}@endif
                                </p>
                            </div>
                        </li>
                    @endforeach
                </ol>
                <form wire:submit="reply" class="flex items-end gap-2 border-t border-line p-3">
                    <textarea wire:model="body" rows="2" class="{{ $field }} flex-1" placeholder="{{ __('Votre message…') }}" x-on:keydown.meta.enter="$wire.reply()"></textarea>
                    <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="reply">{{ __('Envoyer') }}</x-ui.button>
                </form>
                @error('body') <p class="px-4 pb-3 text-xs font-medium text-status-rejected">{{ $message }}</p> @enderror
            @else
                <div class="grid flex-1 place-items-center p-10 text-center text-[0.9375rem] text-mute">{{ __('Choisissez une conversation.') }}</div>
            @endif
        </section>
    </div>

    <x-console.slide-over model="broadcasting" :title="__('Message à tous les Relais')" :description="__('Chaque Relais actif de la commune le reçoit dans sa conversation générale.')">
        <form id="broadcast-form" wire:submit="sendBroadcast" class="space-y-4">
            <x-console.select :label="__('Commune')" name="broadcastCommune" wire:model="broadcastCommune">
                <option value="">{{ __('Choisir') }}</option>
                @foreach ($this->communes as $commune)<option value="{{ $commune->uuid }}">{{ $commune->name }}</option>@endforeach
            </x-console.select>
            <div>
                <label for="broadcast-body" class="block text-[0.8125rem] font-medium">{{ __('Message') }}</label>
                <textarea id="broadcast-body" wire:model="broadcastBody" rows="4" class="{{ $field }} mt-1.5"></textarea>
                @error('broadcastBody') <p class="mt-1 text-xs font-medium text-status-rejected">{{ $message }}</p> @enderror
            </div>
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">{{ __('Annuler') }}</x-ui.button>
            <x-ui.button type="submit" form="broadcast-form">{{ __('Envoyer') }}</x-ui.button>
        </x-slot:footer>
    </x-console.slide-over>
</div>
