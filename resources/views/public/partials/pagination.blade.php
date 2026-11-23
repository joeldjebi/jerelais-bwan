{{--
    Pagination du site public : Précédent / numéros / Suivant, les filtres de l'adresse sont conservés.
    Variable : $paginator (LengthAwarePaginator, avec withQueryString()).
--}}
@if ($paginator->hasPages())
    @php
        $current = $paginator->currentPage();
        $last = $paginator->lastPage();
        $pages = collect([1, $last, ...range(max(1, $current - 1), min($last, $current + 1))])->unique()->sort()->values();
        $link = fn (int $page): string => $paginator->url($page).'#diffusions';
        $pill = 'inline-flex h-11 min-w-11 items-center justify-center rounded-full px-4 text-[0.9375rem] font-medium transition';
    @endphp

    <nav class="mt-12 flex flex-wrap items-center justify-center gap-2" aria-label="{{ __('Pages des diffusions') }}">
        @if ($paginator->onFirstPage())
            <span class="{{ $pill }} text-graphite/50" aria-disabled="true">{{ __('Précédent') }}</span>
        @else
            <a href="{{ $link($current - 1) }}" rel="prev" class="{{ $pill }} bg-white text-onyx ring-1 ring-black/10 hover:ring-black/25">{{ __('Précédent') }}</a>
        @endif

        <span class="hidden items-center gap-1.5 sm:flex">
            @foreach ($pages as $index => $page)
                @if ($index > 0 && $page - $pages[$index - 1] > 1)
                    <span class="px-1 text-graphite" aria-hidden="true">…</span>
                @endif
                @if ($page === $current)
                    <span class="{{ $pill }} bg-onyx text-white" aria-current="page">{{ $page }}</span>
                @else
                    <a href="{{ $link($page) }}" class="{{ $pill }} text-onyx hover:bg-white" aria-label="{{ __('Page :page', ['page' => $page]) }}">{{ $page }}</a>
                @endif
            @endforeach
        </span>
        <span class="px-2 text-[0.9375rem] text-graphite sm:hidden">{{ __(':page / :last', ['page' => $current, 'last' => $last]) }}</span>

        @if ($paginator->hasMorePages())
            <a href="{{ $link($current + 1) }}" rel="next" class="{{ $pill }} bg-white text-onyx ring-1 ring-black/10 hover:ring-black/25">{{ __('Suivant') }}</a>
        @else
            <span class="{{ $pill }} text-graphite/50" aria-disabled="true">{{ __('Suivant') }}</span>
        @endif
    </nav>
@endif
