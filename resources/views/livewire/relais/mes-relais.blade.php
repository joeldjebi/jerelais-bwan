@php
    $tone = [
        'received' => 'bg-tile-blue/12 text-[#0060df]', 'complement_requested' => 'bg-tile-orange/15 text-[#a05a00]',
        'validated' => 'bg-tile-green/14 text-[#248a3d]', 'selected' => 'bg-tile-green/14 text-[#248a3d]', 'broadcast' => 'bg-tile-purple/15 text-[#8944ab]',
        'rejected' => 'bg-status-rejected/12 text-[#d70015]', 'archived' => 'bg-black/[0.06] text-graphite',
    ];
@endphp

<div class="mx-auto max-w-3xl space-y-6" wire:poll.15s.keep-alive>
    @include('livewire.relais.partials.nav', ['active' => 'items'])

    <header class="flex flex-wrap items-end justify-between gap-4" data-enter>
        <div>
            <h1 class="text-[clamp(2rem,4.5vw,2.75rem)] leading-tight font-semibold tracking-[-0.035em]">{{ __('Mes relais') }}</h1>
            <p class="mt-2 text-[1.0625rem] text-graphite">{{ __('Le statut de vos envois, mis à jour automatiquement.') }}</p>
        </div>
        <x-ui.button :href="route('relais.send')" icon="camera">{{ __('Nouveau relais') }}</x-ui.button>
    </header>

    @if ($this->items->isEmpty())
        <div class="rounded-[1.75rem] bg-white p-10 text-center shadow-[0_1px_2px_rgb(0_0_0/0.04)]" data-enter>
            <p class="text-[1.125rem] font-semibold">{{ __('Aucun relais pour l’instant') }}</p>
            <p class="mt-1 text-graphite">{{ __('Vos envois apparaîtront ici avec leur statut.') }}</p>
        </div>
    @else
        <ul class="space-y-3">
            @foreach ($this->items as $relais)
                @php $status = $relais->state->publicName(); @endphp
                <li class="rounded-[1.5rem] bg-white p-5 shadow-[0_1px_2px_rgb(0_0_0/0.04),0_16px_32px_-24px_rgb(0_0_0/0.18)]" wire:key="mine-{{ $relais->uuid }}" data-enter>
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-[1.0625rem] font-semibold">{{ $relais->title ?: __('Sans titre') }}</p>
                            <p class="text-[0.8125rem] text-graphite">{{ collect([$relais->category?->name, $relais->commune?->name, $relais->submitted_at?->diffForHumans()])->filter()->join(' · ') }} · {{ trans_choice(':count média|:count médias', $relais->medias->count()) }}</p>
                        </div>
                        <span class="rounded-full px-3 py-1 text-[0.8125rem] font-semibold {{ $tone[$status] ?? 'bg-black/[0.06]' }}">{{ __("relais_items.states.{$status}") }}</span>
                    </div>

                    @if ($status === 'rejected' && ($relais->rejectionReason || $relais->decision_comment))
                        <p class="mt-3 rounded-xl bg-mist px-4 py-3 text-[0.875rem]"><span class="font-semibold">{{ $relais->rejectionReason?->label }}</span>@if ($relais->decision_comment) · {{ $relais->decision_comment }}@endif</p>
                    @endif
                    @if ($status === 'broadcast' && $relais->broadcast_programme)
                        <p class="mt-3 text-[0.875rem] text-[#8944ab]">{{ __('Diffusé dans « :programme » le :date.', ['programme' => $relais->broadcast_programme, 'date' => $relais->broadcast_at?->translatedFormat('j F à H:i')]) }}</p>
                    @endif

                    @if ($status === 'complement_requested')
                        <div class="mt-4 rounded-2xl bg-tile-orange/10 p-4">
                            <p class="text-[0.875rem]"><span class="font-semibold">{{ __('Votre coordonnateur demande :') }}</span> {{ $relais->decision_comment }}</p>
                            @if ($answering === $relais->uuid)
                                <div class="mt-3 space-y-3"
                                    x-data="webUploader({
                                        announceUrl: @js(route('relais.medias.store', $relais)),
                                        statusUrl: @js(route('relais.medias.show', '__MEDIA__')),
                                        completeUrl: @js(route('relais.medias.complete', '__MEDIA__')),
                                    })">
                                    <textarea wire:model="answer" rows="3" class="block w-full rounded-xl border-0 bg-white p-3 text-[0.9375rem] ring-1 ring-black/10 focus:ring-2 focus:ring-relais-500/50 focus:outline-none" placeholder="{{ __('Votre réponse…') }}"></textarea>
                                    <label class="inline-flex cursor-pointer items-center gap-2 text-[0.875rem] font-medium text-relais-600">
                                        <x-ui.icon name="camera" class="size-4" />{{ __('Ajouter un média') }}
                                        <input type="file" multiple class="sr-only" x-on:change="add($event)">
                                    </label>
                                    <template x-for="file in files" :key="file.id"><p class="text-[0.8125rem] text-graphite" x-text="`${file.name} · ${file.status === 'ready' || file.status === 'uploaded' || file.status === 'processing' ? 'envoyé' : file.progress + ' %'}`"></p></template>
                                    @error('answer') <p class="text-xs font-medium text-status-rejected">{{ $message }}</p> @enderror
                                    <x-ui.button size="sm" wire:click="respond('{{ $relais->uuid }}')" ::disabled="busy">{{ __('Envoyer ma réponse') }}</x-ui.button>
                                </div>
                            @else
                                <x-ui.button size="sm" class="mt-3" wire:click="$set('answering', '{{ $relais->uuid }}')">{{ __('Répondre') }}</x-ui.button>
                            @endif
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
        {{ $this->items->links() }}
    @endif
</div>
