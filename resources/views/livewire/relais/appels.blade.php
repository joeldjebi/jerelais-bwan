<div class="mx-auto max-w-3xl space-y-6">
    @include('livewire.relais.partials.nav', ['active' => 'appels'])

    <header data-enter>
        <h1 class="text-[clamp(2rem,4.5vw,2.75rem)] leading-tight font-semibold tracking-[-0.035em]">{{ __('Appels à relais') }}</h1>
        <p class="mt-2 text-[1.0625rem] text-graphite">{{ __('La rédaction cherche des images près de chez vous. Vos réponses sont examinées en priorité.') }}</p>
    </header>

    @forelse ($this->appels as $appel)
        <article class="rounded-[1.75rem] bg-white p-6 shadow-[0_1px_2px_rgb(0_0_0/0.04),0_24px_48px_-28px_rgb(0_0_0/0.18)]" wire:key="ap-{{ $appel->uuid }}" data-enter>
            <p class="text-[0.8125rem] font-semibold text-tile-purple">{{ __('Jusqu’au :date', ['date' => $appel->deadline_at->timezone('Africa/Abidjan')->translatedFormat('l j F à H:i')]) }}</p>
            <h2 class="mt-1 text-[1.375rem] font-semibold tracking-[-0.02em]">{{ $appel->title }}</h2>
            <p class="mt-2 leading-relaxed whitespace-pre-line text-graphite">{{ $appel->instructions }}</p>
            <x-ui.button class="mt-5" :href="route('relais.send', ['appel' => $appel->uuid])" icon="camera">{{ __('Répondre à cet appel') }}</x-ui.button>
        </article>
    @empty
        <div class="rounded-[1.75rem] bg-white p-10 text-center" data-enter>
            <p class="text-[1.125rem] font-semibold">{{ __('Aucun appel en cours dans votre zone') }}</p>
            <p class="mt-1 text-graphite">{{ __('Vous serez prévenu·e par SMS au prochain appel.') }}</p>
        </div>
    @endforelse
</div>
