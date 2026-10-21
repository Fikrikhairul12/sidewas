@props(['content' => null, 'contentExpression' => null])

@php($contentAttributes = \App\Services\SnpButirContent::isRich($content) ? $attributes : $attributes->except(['data-report-rich']))
<div {{ $contentAttributes->class(['snp-rich-content']) }}
    @if ($contentExpression) x-html="snpButirHtml(({{ $contentExpression }}) || '-')" @endif>
    @unless ($contentExpression)
        {!! app(\App\Services\SnpButirContent::class)->html(filled($content) ? $content : '-') !!}
    @endunless
</div>
