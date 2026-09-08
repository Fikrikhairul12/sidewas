<section class="card table-card">
    <div class="table-header"><h2>{{ $tableTitle ?? 'Data Kunjungan' }}</h2><p>Menampilkan {{ $visits->firstItem() ?? 0 }}–{{ $visits->lastItem() ?? 0 }} dari {{ $visits->total() }} data.</p></div>
    @if($visits->isNotEmpty())
        <div class="table-scroll"><table class="data-table visit-table"><thead><tr><th>Kunjungan</th><th>Unit Tujuan</th><th>Jadwal</th><th>PIC Unit & Peserta</th><th>Status</th><th>Laporan</th><th>Aksi</th></tr></thead><tbody>
        @foreach($visits as $visit)<tr>
            <td><div class="visit-identity"><strong>{{ $visit->visit_number }}</strong><div class="cell-title">{{ $visit->title }}</div><div class="cell-sub">{{ Str::limit($visit->purpose,64) }}</div></div></td>
            <td>
                <div class="cell-title">{{ $visit->destination_list->first()?->nama_unit_kerja }}</div>
                <div class="cell-sub">
                    {{ $visit->destination_list->first()?->kab_kota }}
                    @if($visit->destination_list->count() > 1)
                        · +{{ $visit->destination_list->count() - 1 }} lokasi lain
                    @endif
                </div>
            </td>
            <td><div class="cell-title">{{ $visit->start_at->translatedFormat('d M Y') }}</div>@unless($visit->start_at->isSameDay($visit->end_at))<div class="cell-sub">s.d. {{ $visit->end_at->translatedFormat('d M Y') }}</div>@endunless</td>
            <td><div class="cell-title">{{ $visit->picUnitKerja?->kode_unit }} - {{ $visit->picUnitKerja?->nama_unit }}</div><div class="cell-sub">{{ $visit->participants_count }} peserta</div></td>
            <td><x-status-badge :status="$visit->status" :overdue="$visit->is_overdue"/></td>
            <td>@if($visit->currentReport)<span class="badge badge-success">PDF v{{ $visit->currentReport->version }}</span>@else<span class="cell-sub">Belum tersedia</span>@endif</td>
            <td><div class="actions"><a class="btn btn-light btn-sm" href="{{ route('kunjungan.visits.show',$visit) }}">Detail</a>@can('update',$visit)<a class="btn btn-primary btn-sm" href="{{ route('kunjungan.visits.edit',$visit) }}">Edit</a>@endcan</div></td>
        </tr>@endforeach
        </tbody></table></div><div class="pagination-wrap">{{ $visits->links() }}</div>
    @else<x-empty-state />@endif
</section>

