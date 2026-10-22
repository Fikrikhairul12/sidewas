@foreach ($round['responses'] as $entry)
    @include('layouts.partials.workflow-report-entry')
@endforeach
@if ($round['current_assignments'])
    <div class="muted">Penugasan unit saat ini; putaran masih berjalan.</div>
@endif
@if ($round['unknown_assignments'])
    <div class="muted">Penugasan unit pada putaran lama tidak tercatat. Unit yang belum mengisi tidak dapat dipastikan.</div>
@endif
@foreach ($round['missing'] as $unit)
    <div class="entry-block"><strong>{{ $unit['code'] ? $unit['code'].' - ' : '' }}{{ $unit['name'] }}</strong><div>{{ $round['unidentified_responses'] ? 'Belum ada tanggapan teridentifikasi dari unit ini. Data lama tanpa unit belum dapat dicocokkan.' : 'Belum memberikan tanggapan pada putaran ini.' }}</div></div>
@endforeach
@if ($round['responses']->isEmpty() && $round['missing']->isEmpty())
    <div>Belum ada tanggapan unit dalam cakupan laporan.</div>
@endif
@if ($round['missing_at_review']->isNotEmpty())
    <div>Belum ada tanggapan teridentifikasi saat penutupan: {{ $round['missing_at_review']->pluck('name')->implode(', ') }}.</div>
@endif
@if ($field !== 'tanggapan_tl')
    @include('layouts.partials.workflow-report-late-responses')
@endif
