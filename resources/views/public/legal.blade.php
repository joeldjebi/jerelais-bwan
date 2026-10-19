@php
    use App\Domain\Legal\Enums\LegalDocumentType;
@endphp

<x-layouts.public :title="$document->title">
    <article class="bg-white px-5 pt-32 pb-28 sm:pt-40">
        <div class="mx-auto max-w-[720px]">
            <p class="text-[0.9375rem] font-medium text-relais-600" data-anim>{{ __('JE RELAIS') }}</p>
            <h1 class="mt-3 text-[clamp(2.5rem,6vw,4rem)] leading-[1.02] font-semibold tracking-[-0.04em]" data-anim>{{ $document->title }}</h1>
            <p class="mt-4 text-[0.9375rem] text-graphite" data-anim>
                {{ __('Version :version · publiée le :date', ['version' => $document->version, 'date' => $document->published_at->translatedFormat('j F Y')]) }}
            </p>

            <div class="legal-prose mt-12 text-[1.0625rem]" data-anim>{{ $document->html() }}</div>

            <nav class="mt-16 flex flex-wrap gap-3 border-t border-black/10 pt-8" aria-label="{{ __('Autres textes') }}">
                @foreach (LegalDocumentType::cases() as $type)
                    @continue($type === $document->type)
                    <a href="{{ route('legal.show', $type) }}" class="press rounded-full bg-mist px-4 py-2 text-[0.875rem] font-medium transition-colors duration-300 hover:bg-black/[0.07]">{{ $type->label() }}</a>
                @endforeach
                <a href="{{ route('relais.join') }}" class="press rounded-full bg-relais-500 px-4 py-2 text-[0.875rem] font-medium text-white transition-colors duration-300 hover:bg-relais-600">{{ __('Devenir Relais') }}</a>
            </nav>
        </div>
    </article>
</x-layouts.public>
