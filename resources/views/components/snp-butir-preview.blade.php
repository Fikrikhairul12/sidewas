@props([
    'content' => '',
    'butirId' => '',
    'contentExpression' => null,
    'idExpression' => null,
    'itemsExpression' => '[]',
    'expanded' => false,
    'context' => 'Isi Butir SNP',
])

<div {{ $attributes->class(['snp-butir-preview', 'snp-butir-preview--expanded' => $expanded]) }}>
    @if ($expanded)
        <p class="snp-butir-preview__heading text-sm font-bold text-slate-600">Isi Butir SNP</p>
    @endif
    @if ($contentExpression)
        <p class="snp-butir-preview__text" x-text="({{ $contentExpression }}) || 'Belum ada isi butir.'"></p>
    @else
        <p class="snp-butir-preview__text">{{ $content ?: 'Belum ada isi butir.' }}</p>
    @endif
    <button type="button" class="snp-butir-read-button"
        x-on:click.stop="$dispatch('snp-read-butir', {
            id: {{ $idExpression ?: \Illuminate\Support\Js::from($butirId) }},
            content: {{ $contentExpression ?: \Illuminate\Support\Js::from($content) }},
            items: {{ $itemsExpression }},
            context: @js($context)
        })">
        <svg class="h-4 w-4 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 7v14m0-14C9 5 5 4 2 5v14c3-1 7 0 10 2m0-14c3-2 7-3 10-2v14c-3-1-7 0-10 2" />
        </svg>
        {{ $expanded ? 'Perbesar bacaan' : 'Baca isi lengkap' }}
        <span class="sr-only" @if ($idExpression) x-text="{{ $idExpression }}" @endif>{{ $butirId }}</span>
    </button>
</div>
