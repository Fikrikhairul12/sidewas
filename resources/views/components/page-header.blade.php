@props(['eyebrow' => 'Kunjungan Kerja', 'title', 'description' => null])
<section class="card page-header-card">
    <div><div class="eyebrow">{{ $eyebrow }}</div><h1 class="page-title">{{ $title }}</h1>@if($description)<p class="page-description">{{ $description }}</p>@endif</div>
    @isset($actions)<div class="header-actions">{{ $actions }}</div>@endisset
</section>

