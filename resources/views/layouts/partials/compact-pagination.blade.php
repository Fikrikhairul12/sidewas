@props(['paginator'])

@if ($paginator->hasPages())
    @php
        $currentPage = $paginator->currentPage();
        $lastPage = $paginator->lastPage();
        $pageStart = max(1, min($currentPage - 2, $lastPage - 4));
        $pageEnd = min($lastPage, $pageStart + 4);
    @endphp

    <nav class="flex items-center justify-end" aria-label="Pagination">
        <div class="inline-flex overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            @if ($paginator->onFirstPage())
                <span class="inline-flex cursor-not-allowed items-center px-4 py-3 text-sm font-semibold text-slate-300">
                    &lsaquo;
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}"
                    class="inline-flex items-center px-4 py-3 text-sm font-semibold text-slate-500 hover:bg-slate-50">
                    &lsaquo;
                </a>
            @endif

            @if ($pageStart > 1)
                <span
                    class="inline-flex items-center border-l border-slate-200 px-4 py-3 text-sm font-semibold text-slate-400">
                    ...
                </span>
            @endif

            @for ($page = $pageStart; $page <= $pageEnd; $page++)
                @if ($page === $currentPage)
                    <span
                        class="inline-flex items-center border-l border-slate-200 bg-slate-100 px-4 py-3 text-sm font-semibold text-slate-800">
                        {{ $page }}
                    </span>
                @else
                    <a href="{{ $paginator->url($page) }}"
                        class="inline-flex items-center border-l border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        {{ $page }}
                    </a>
                @endif
            @endfor

            @if ($pageEnd < $lastPage)
                <span
                    class="inline-flex items-center border-l border-slate-200 px-4 py-3 text-sm font-semibold text-slate-400">
                    ...
                </span>
            @endif

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}"
                    class="inline-flex items-center border-l border-slate-200 px-4 py-3 text-sm font-semibold text-slate-500 hover:bg-slate-50">
                    &rsaquo;
                </a>
            @else
                <span
                    class="inline-flex cursor-not-allowed items-center border-l border-slate-200 px-4 py-3 text-sm font-semibold text-slate-300">
                    &rsaquo;
                </span>
            @endif
        </div>
    </nav>
@endif
