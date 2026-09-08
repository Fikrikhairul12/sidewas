@props(['visit'])
<a href="{{ route('kunjungan.visits.show', $visit) }}" class="visit-mini">
    <div><h4>{{ $visit->title }}</h4><p>{{ $visit->destination_list->first()?->nama_unit_kerja }}{{ $visit->destination_list->count() > 1 ? ' +'.($visit->destination_list->count() - 1).' lokasi' : '' }} · {{ $visit->start_at->translatedFormat('d M Y') }}</p></div>
    <x-status-badge :status="$visit->status" />
</a>

