<div class="mx-auto max-w-3xl space-y-6" wire:poll.15s.keep-alive>
    @include('livewire.relais.partials.nav', ['active' => 'messages'])

    <header data-enter>
        <h1 class="text-[clamp(2rem,4.5vw,2.75rem)] leading-tight font-semibold tracking-[-0.035em]">{{ __('Messages') }}</h1>
        <p class="mt-2 text-[1.0625rem] text-graphite">{{ __('Échangez avec le coordonnateur de votre commune.') }}</p>
    </header>

    @if ($this->conversations->count() > 1)
        <div class="flex gap-2 overflow-x-auto" data-enter>
            @foreach ($this->conversations as $conversation)
                <button type="button" wire:click="select('{{ $conversation->uuid }}')" @class(['press shrink-0 rounded-full px-4 py-2 text-[0.8125rem] font-medium ring-1', 'bg-onyx text-white ring-onyx' => $selected === $conversation->uuid, 'bg-white ring-black/10' => $selected !== $conversation->uuid])>
                    {{ $conversation->relaisItem?->title ?? __('Conversation générale') }}
                </button>
            @endforeach
        </div>
    @endif

    @if ($this->current)
        <section class="rounded-[1.75rem] bg-white shadow-[0_1px_2px_rgb(0_0_0/0.04),0_24px_48px_-28px_rgb(0_0_0/0.18)]" data-enter>
            <ol class="max-h-[28rem] space-y-3 overflow-y-auto p-6" x-init="$el.scrollTop = $el.scrollHeight">
                @forelse ($this->current->messages as $message)
                    <li @class(['flex', 'justify-end' => ! $message->from_staff]) wire:key="rm-{{ $message->uuid }}">
                        <div @class(['max-w-[80%] rounded-[1.2rem] px-4 py-2.5 text-[0.9375rem] leading-relaxed', 'bg-relais-500 text-white' => ! $message->from_staff, 'bg-mist' => $message->from_staff])>
                            <p class="whitespace-pre-line">{{ $message->body }}</p>
                            <p @class(['mt-1 text-[0.6875rem]', 'text-white/75' => ! $message->from_staff, 'text-graphite' => $message->from_staff])>{{ $message->from_staff ? __('Votre coordonnateur') : __('Vous') }} · {{ $message->created_at->timezone('Africa/Abidjan')->format('d/m H:i') }}</p>
                        </div>
                    </li>
                @empty
                    <li class="py-8 text-center text-graphite">{{ __('Pas encore de message. Écrivez à votre coordonnateur !') }}</li>
                @endforelse
            </ol>
            <form wire:submit="sendMessage" class="flex items-end gap-2 border-t border-black/[0.06] p-3">
                <textarea wire:model="body" rows="2" class="block flex-1 rounded-xl border-0 bg-mist p-3 text-[0.9375rem] focus:bg-white focus:ring-2 focus:ring-relais-500/50 focus:outline-none" placeholder="{{ __('Votre message…') }}"></textarea>
                <x-ui.button type="submit">{{ __('Envoyer') }}</x-ui.button>
            </form>
            @error('body') <p class="px-4 pb-3 text-xs font-medium text-status-rejected">{{ $message }}</p> @enderror
        </section>
    @endif
</div>
