@php use App\Domain\Editorial\Support\CreditLine; @endphp

<x-layouts.public :title="__('Vu sur REFLET TV')" :description="__('Les relais des observateurs citoyens passés à l’antenne de REFLET TV.')">
    <section class="bg-white px-5 pt-32 pb-16 sm:pt-40">
        <div class="mx-auto max-w-[1024px]">
            <p class="text-[0.9375rem] font-medium text-relais-600" data-anim>{{ __('JE RELAIS') }}</p>
            <h1 class="mt-3 text-[clamp(2.75rem,7vw,5rem)] leading-[1] font-semibold tracking-[-0.045em]" data-anim>{{ __('Vu sur REFLET TV.') }}</h1>
            <p class="mt-5 max-w-xl text-xl text-graphite" data-anim>{{ __('Ce que les Relais ont vu, et que la chaîne a montré.') }}</p>
        </div>
    </section>

    <section class="bg-mist px-5 py-20">
        <div class="mx-auto grid max-w-[1024px] gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($items as $relais)
                <article class="overflow-hidden rounded-[1.75rem] bg-white" data-anim>
                    <div class="relative aspect-[4/3] bg-onyx">
                        @if ($relais->showcase_with_image)
                            <img src="{{ route('showcase.image', $relais) }}" alt="" class="size-full object-cover" loading="lazy">
                        @else
                            <div class="grid size-full place-items-center"><x-ui.icon name="signal" class="size-10 text-white/30" /></div>
                        @endif
                        <span class="absolute top-4 left-4 rounded-full bg-white/90 px-3 py-1 text-[0.75rem] font-semibold text-onyx">{{ $relais->commune?->name }}</span>
                    </div>
                    <div class="p-6">
                        <p class="text-[0.8125rem] font-medium text-relais-600">{{ $relais->broadcast_programme }} · {{ $relais->broadcast_at?->translatedFormat('j F Y') }}</p>
                        <h2 class="mt-2 text-[1.25rem] leading-snug font-semibold tracking-[-0.02em]">{{ $relais->showcase_title }}</h2>
                        @if ($relais->showcase_text)<p class="mt-2 leading-relaxed text-graphite">{{ $relais->showcase_text }}</p>@endif
                        <p class="mt-4 text-[0.8125rem] text-graphite">{{ CreditLine::for($relais) }}</p>
                        @if ($relais->broadcast_url)
                            <a href="{{ $relais->broadcast_url }}" target="_blank" rel="noopener" class="mt-3 inline-flex items-center gap-1 text-[0.9375rem] font-medium text-relais-600 hover:underline">{{ __('Voir la diffusion') }}<x-ui.icon name="arrow-up-right" class="size-4" /></a>
                        @endif
                    </div>
                </article>
            @empty
                <p class="col-span-full py-16 text-center text-xl text-graphite" data-anim>{{ __('Les premiers relais diffusés apparaîtront bientôt ici.') }}</p>
            @endforelse
        </div>
        <div class="mx-auto mt-10 max-w-[1024px]">{{ $items->links() }}</div>
    </section>
</x-layouts.public>
