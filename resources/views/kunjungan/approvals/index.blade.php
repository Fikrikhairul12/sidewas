<x-layouts.app title="Persetujuan Kunjungan">
    <x-page-header
        title="Persetujuan Kunjungan"
        description="Periksa rencana dan laporan kunjungan, lalu berikan keputusan sesuai hasil verifikasi."
    />

    <div class="stats-grid stats-grid-4">
        <x-stat-card label="Menunggu Persetujuan" :value="$stats['pending']" tone="warning" />
        <x-stat-card label="Laporan Menunggu Persetujuan" :value="$stats['reports_pending']" tone="info" />
        <x-stat-card label="Disetujui Bulan Ini" :value="$stats['approved']" tone="success" />
        <x-stat-card label="Ditolak Bulan Ini" :value="$stats['rejected']" tone="danger" />
    </div>

    <section class="card table-card">
        <div class="table-header">
            <div>
                <h2>Antrean Rencana Kunjungan</h2>
                <p>Pengajuan diurutkan dari waktu pengiriman paling awal.</p>
            </div>
        </div>

        <div class="table-scroll">
            <table class="data-table approval-table">
                <thead>
                    <tr>
                        <th>Pengajuan</th>
                        <th>Unit Tujuan</th>
                        <th>Jadwal</th>
                        <th>PIC Unit & Peserta</th>
                        <th>Putaran</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($visits as $visit)
                        <tr>
                            <td>
                                <div class="visit-identity">
                                    <strong>{{ $visit->visit_number }}</strong>
                                    <div class="cell-title">{{ $visit->title }}</div>
                                    <div class="cell-sub">Dikirim {{ $visit->submitted_at->diffForHumans() }}</div>
                                </div>
                            </td>
                            <td>
                                <strong>{{ $visit->destination_list->first()?->nama_unit_kerja }}</strong>
                                <div class="cell-sub">
                                    {{ $visit->destination_list->first()?->kab_kota }}
                                    @if($visit->destination_list->count() > 1)
                                        · +{{ $visit->destination_list->count() - 1 }} lokasi lain
                                    @endif
                                </div>
                            </td>
                            <td>
                                <strong>{{ $visit->start_at->translatedFormat('d M Y') }}</strong>
                                @unless($visit->start_at->isSameDay($visit->end_at))<div class="cell-sub">s.d. {{ $visit->end_at->translatedFormat('d M Y') }}</div>@endunless
                            </td>
                            <td>
                                <strong>{{ $visit->picUnitKerja?->kode_unit }} - {{ $visit->picUnitKerja?->nama_unit }}</strong>
                                <div class="cell-sub">{{ $visit->participants_count }} peserta</div>
                            </td>
                            <td><span class="badge badge-warning">Putaran {{ $visit->approval_round }}</span></td>
                            <td>
                                <div class="approval-actions">
                                    <a class="btn btn-light btn-sm" href="{{ route('kunjungan.visits.show', $visit) }}">Detail</a>
                                    <form method="POST" action="{{ route('kunjungan.approvals.approve', $visit) }}" data-confirm="Setujui {{ $visit->visit_number }}?">
                                        @csrf
                                        <button class="btn btn-success btn-sm">Setujui</button>
                                    </form>
                                    <button class="btn btn-danger btn-sm" type="button" data-modal-open="reject-{{ $visit->id }}">Tolak</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-empty-state title="Tidak ada pengajuan yang menunggu." description="Semua pengajuan pegawai telah diperiksa." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $visits->links() }}
    </section>

    <section class="card table-card" style="margin-top:20px">
        <div class="table-header">
            <div>
                <h2>Antrean Laporan Kunjungan</h2>
                <p>Laporan yang diunggah peserta dan menunggu keputusan moderator.</p>
            </div>
        </div>

        <div class="table-scroll">
            <table class="data-table approval-table">
                <thead>
                    <tr>
                        <th>Laporan</th>
                        <th>Kunjungan</th>
                        <th>Unit Tujuan</th>
                        <th>Waktu Upload</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reports as $report)
                        <tr>
                            <td>
                                <div class="visit-identity">
                                    <strong>Versi {{ $report->version }}</strong>
                                    <div class="cell-title">{{ $report->original_filename }}</div>
                                    <div class="cell-sub">{{ number_format($report->file_size / 1024, 1) }} KB</div>
                                </div>
                            </td>
                            <td>
                                <strong>{{ $report->visit->visit_number }}</strong>
                                <div class="cell-sub">{{ $report->visit->title }}</div>
                            </td>
                            <td>
                                <strong>{{ $report->visit->destination_list->first()?->nama_unit_kerja }}</strong>
                                <div class="cell-sub">{{ $report->visit->destination_list->first()?->kab_kota }}</div>
                            </td>
                            <td>
                                <strong>{{ $report->uploaded_at->translatedFormat('d M Y') }}</strong>
                                <div class="cell-sub">{{ $report->uploaded_at->format('H:i') }} WIB</div>
                            </td>
                            <td><span class="badge badge-{{ $report->status->color() }}">{{ $report->status->label() }}</span></td>
                            <td>
                                <div class="approval-actions">
                                    <a target="_blank" class="btn btn-light btn-sm" href="{{ route('kunjungan.reports.show', [$report->visit, $report]) }}">Lihat PDF</a>
                                    <form method="POST" action="{{ route('kunjungan.reports.approve', [$report->visit, $report]) }}" data-confirm="Setujui laporan {{ $report->visit->visit_number }}?">
                                        @csrf
                                        <button class="btn btn-success btn-sm">Setujui</button>
                                    </form>
                                    <button class="btn btn-danger btn-sm" type="button" data-modal-open="reject-report-{{ $report->id }}">Tolak</button>
                                    <a class="btn btn-light btn-sm" href="{{ route('kunjungan.visits.show', $report->visit) }}">Detail</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-empty-state title="Tidak ada laporan yang menunggu." description="Semua laporan kunjungan telah diperiksa." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $reports->links() }}
    </section>

    @push('modals')
        @foreach($visits as $visit)
            <div class="modal-backdrop" id="reject-{{ $visit->id }}">
                <div class="modal">
                    <h3>Tolak {{ $visit->visit_number }}</h3>
                    <p>{{ $visit->title }}</p>

                    <form method="POST" action="{{ route('kunjungan.approvals.reject', $visit) }}">
                        @csrf
                        <div class="field">
                            <label>Alasan Penolakan *</label>
                            <textarea class="textarea" name="notes" minlength="5" maxlength="2000" required></textarea>
                        </div>
                        <div class="modal-actions">
                            <button class="btn btn-light" type="button" data-modal-close>Batal</button>
                            <button class="btn btn-danger">Tolak Pengajuan</button>
                        </div>
                    </form>
                </div>
            </div>
        @endforeach
        @foreach($reports as $report)
            <div class="modal-backdrop" id="reject-report-{{ $report->id }}">
                <div class="modal">
                    <h3>Tolak Laporan {{ $report->visit->visit_number }}</h3>
                    <p>Alasan penolakan akan ditampilkan kepada peserta sebagai dasar perbaikan laporan.</p>

                    <form method="POST" action="{{ route('kunjungan.reports.reject', [$report->visit, $report]) }}">
                        @csrf
                        <div class="field">
                            <label>Alasan Penolakan *</label>
                            <textarea class="textarea" name="notes" minlength="5" maxlength="2000" required></textarea>
                        </div>
                        <div class="modal-actions">
                            <button class="btn btn-light" type="button" data-modal-close>Batal</button>
                            <button class="btn btn-danger">Tolak Laporan</button>
                        </div>
                    </form>
                </div>
            </div>
        @endforeach
    @endpush
</x-layouts.app>
