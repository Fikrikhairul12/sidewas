@if ($paginator->hasPages())
    <nav class="pagination" role="navigation" aria-label="Navigasi halaman">
        <p>Halaman {{ $paginator->currentPage() }} dari {{ $paginator->lastPage() }}</p>
        <div class="pagination-links">
            @if ($paginator->onFirstPage())
                <span class="pagination-button disabled" aria-disabled="true">← Sebelumnya</span>
            @else
                <a class="pagination-button" href="{{ $paginator->previousPageUrl() }}" rel="prev">← Sebelumnya</a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))<span class="pagination-ellipsis">{{ $element }}</span>@endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="pagination-number active" aria-current="page">{{ $page }}</span>
                        @else
                            <a class="pagination-number" href="{{ $url }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a class="pagination-button" href="{{ $paginator->nextPageUrl() }}" rel="next">Berikutnya →</a>
            @else
                <span class="pagination-button disabled" aria-disabled="true">Berikutnya →</span>
            @endif
        </div>
    </nav>
@endif

