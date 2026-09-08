@props(['label','value','tone' => '', 'href' => null])
@php($tag = $href ? 'a' : 'div')
<{{ $tag }} @if($href) href="{{ $href }}" @endif class="card stat-card {{ $tone }}">
    <div><div class="stat-label">{{ $label }}</div><div class="stat-value">{{ $value }}</div></div>
    <div class="stat-icon">@isset($icon){{ $icon }}@else<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 3h14v18H5zM8 7h8M8 11h8M8 15h5"/></svg>@endisset</div>
</{{ $tag }}>

