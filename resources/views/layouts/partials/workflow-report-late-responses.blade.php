@if ($round['late']->isNotEmpty())
    <div class="entry-title late-note">Kelengkapan setelah Close (terpisah dari bahan reviu)</div>
    @foreach ($round['late'] as $entry)
        @include('layouts.partials.workflow-report-entry')
    @endforeach
@endif
