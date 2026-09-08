<x-layouts.app :title="$visit->visit_number">
    <x-page-header :eyebrow="$visit->visit_number" :title="$visit->title" description="Detail jadwal, peserta, perkembangan status, dan dokumen laporan kunjungan.">
        <x-slot:actions>
            @can('update', $visit)
                <a class="btn btn-light" href="{{ route('kunjungan.visits.edit', $visit) }}">Edit Rencana</a>
            @endcan
            @if($visit->status === \App\Enums\VisitStatus::REJECTED)
                @can('update', $visit)
                    <form method="POST" action="{{ route('kunjungan.visits.resubmit', $visit) }}" data-confirm="Ajukan kembali rencana ini kepada moderator?">
                        @csrf
                        <button class="btn btn-primary">Ajukan Kembali</button>
                    </form>
                @endcan
            @endif
            @can('approve', $visit)
                <button class="btn btn-success" type="button" data-modal-open="approve-modal">Setujui</button>
                <button class="btn btn-danger" type="button" data-modal-open="reject-modal">Tolak</button>
            @endcan
            @can('finish', $visit)
                <form method="POST" action="{{ route('kunjungan.visits.finish', $visit) }}" data-confirm="Pastikan kunjungan benar-benar telah selesai dilaksanakan.">
                    @csrf
                    <button class="btn btn-success">Tandai Kunjungan Selesai</button>
                </form>
            @endcan
            @can('cancel', $visit)
                <button class="btn btn-danger" type="button" data-modal-open="cancel-modal">Batalkan</button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="detail-grid">
        <div class="stack">
            <section class="card detail-card">
                <div class="section-heading">
                    <div><h2>Informasi Kunjungan</h2><p>Jadwal pelaksanaan dan tujuan unit kerja.</p></div>
                    <x-status-badge :status="$visit->status" :overdue="$visit->is_overdue" />
                </div>
                <div class="info-grid">
                    <div class="info-row"><small>Nomor Kunjungan</small><strong>{{ $visit->visit_number }}</strong></div>
                    <div class="info-row"><small>Putaran Persetujuan</small><strong>Putaran {{ $visit->approval_round }}</strong></div>
                    <div class="info-row"><small>Nomor Surat</small><strong>{{ $visit->letter_number ?: 'Belum dicantumkan' }}</strong></div>
                    <div class="info-row"><small>Jumlah Lokasi</small><strong>{{ $visit->destination_list->count() }} unit kerja</strong></div>
                    <div class="info-row"><small>Tanggal Mulai</small><strong>{{ $visit->start_at->translatedFormat('l, d F Y') }}</strong></div>
                    <div class="info-row"><small>Tanggal Selesai</small><strong>{{ $visit->end_at->translatedFormat('l, d F Y') }}</strong></div>
                    <div class="info-row"><small>PIC Unit Kerja</small><strong>{{ $visit->picUnitKerja?->kode_unit }} - {{ $visit->picUnitKerja?->nama_unit }}</strong></div>
                    <div class="info-row"><small>Direktorat PIC</small><span>{{ $visit->picUnitKerja?->direktorat?->nama_direktorat ?: '—' }}</span></div>
                    <div class="info-row field-full"><small>Keperluan</small><span>{{ $visit->purpose }}</span></div>
                    @if($visit->cancelled_reason)
                        <div class="info-row field-full"><small>Alasan Pembatalan</small><span>{{ $visit->cancelled_reason }}</span></div>
                    @endif
                </div>
                <div style="margin-top:22px">
                    <small style="display:block;color:#7b899b;font-size:11px;margin-bottom:9px">Lokasi tujuan ({{ $visit->destination_list->count() }} unit kerja)</small>
                    <div class="destination-detail-list">
                        @foreach($visit->destination_list as $destination)
                            <div class="destination-detail-item"><span>{{ $destination->kode_unit }}</span><div><strong>{{ $destination->nama_unit_kerja }}</strong><small>{{ collect([$destination->kab_kota, $destination->provinsi])->filter()->join(', ') }}</small></div></div>
                        @endforeach
                    </div>
                </div>
                <div style="margin-top:22px">
                    <small style="display:block;color:#7b899b;font-size:11px;margin-bottom:9px">Peserta ({{ $visit->participants->count() }} orang)</small>
                    <div class="participants">
                        @foreach($visit->participants as $participant)
                            <span class="person-chip"><strong>{{ $participant->name }}</strong> · {{ $participant->organizational_unit ?: 'Unit belum tersedia' }}</span>
                        @endforeach
                    </div>
                </div>
            </section>

            <section class="card detail-card">
                <div class="section-heading"><div><h2>Riwayat Persetujuan</h2><p>Keputusan moderator pada setiap putaran pengajuan.</p></div></div>
                @if($visit->approvals->isNotEmpty())
                    <div class="timeline">
                        @foreach($visit->approvals->sortBy('round') as $approval)
                            <div class="timeline-item">
                                <h4>Putaran {{ $approval->round }} · {{ $approval->decision === 'APPROVED' ? 'Disetujui' : 'Ditolak' }}</h4>
                                <p>{{ $approval->notes ?: 'Tanpa catatan.' }}<br>Oleh {{ $actorNames[$approval->acted_by_user_id] ?? 'Moderator' }}</p>
                                <time>{{ $approval->decided_at->translatedFormat('d M Y, H:i') }} WIB</time>
                            </div>
                        @endforeach
                    </div>
                @else
                    <x-empty-state title="Belum ada keputusan moderator." description="Pengajuan masih menunggu pemeriksaan moderator." />
                @endif
            </section>
        </div>

        <aside class="stack">
            <section class="card detail-card">
                <div class="section-heading"><div><h2>Status Timeline</h2><p>Rekam perubahan status kunjungan.</p></div></div>
                <div class="timeline">
                    @foreach($visit->statusLogs as $log)
                        <div class="timeline-item">
                            <h4>{{ $log->to_status->label() }}</h4>
                            <p>{{ $log->notes }}@if($log->actor_user_id)<br>Oleh {{ $actorNames[$log->actor_user_id] ?? 'Pengguna' }}@endif</p>
                            <time>{{ $log->created_at->translatedFormat('d M Y, H:i') }} WIB</time>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="card detail-card">
                <div class="section-heading"><div><h2>Laporan Kunjungan</h2><p>Satu laporan PDF untuk setiap kunjungan, diunggah oleh salah satu anggota.</p></div></div>
                @can('uploadReport', $visit)
                    <form method="POST" action="{{ route('kunjungan.reports.store', $visit) }}" enctype="multipart/form-data" style="padding:14px;background:#f4f9fd;border-radius:12px;margin-bottom:17px">
                        @csrf
                        <div class="field"><label for="report">Upload Laporan Kunjungan</label><input class="input" id="report" type="file" name="report" accept="application/pdf,.pdf" required><span class="help">PDF, maksimum 10 MB.</span></div>
                        <button class="btn btn-primary btn-sm" style="margin-top:10px" type="submit">Upload Laporan</button>
                    </form>
                @endcan
                @forelse($visit->reports as $report)
                    <div class="report-item">
                        <div class="report-meta">
                            <div><h4>{{ $report->original_filename }}</h4><p>{{ number_format($report->file_size / 1024, 1) }} KB<br>{{ $report->uploaded_at->translatedFormat('d M Y, H:i') }} WIB</p></div>
                            <span class="badge badge-success">Laporan</span>
                        </div>
                        <div class="actions" style="margin-top:10px"><a target="_blank" class="btn btn-light btn-sm" href="{{ route('kunjungan.reports.show', [$visit, $report]) }}">Lihat PDF</a><a class="btn btn-light btn-sm" href="{{ route('kunjungan.reports.download', [$visit, $report]) }}">Download</a></div>
                    </div>
                @empty
                    <x-empty-state title="Belum ada laporan PDF." description="Laporan dapat diunggah setelah kunjungan ditandai selesai." />
                @endforelse
            </section>
        </aside>
    </div>

    @push('modals')
        @can('approve', $visit)
            <div class="modal-backdrop" id="approve-modal"><div class="modal"><h3>Setujui Kunjungan</h3><p>Pengajuan akan disetujui dan masuk ke jadwal pelaksanaan.</p><form method="POST" action="{{ route('kunjungan.approvals.approve', $visit) }}">@csrf<div class="field"><label>Catatan (opsional)</label><textarea class="textarea" name="notes" maxlength="2000" placeholder="Tambahkan catatan untuk pegawai..."></textarea></div><div class="modal-actions"><button type="button" class="btn btn-light" data-modal-close>Batal</button><button class="btn btn-success">Ya, Setujui</button></div></form></div></div>
            <div class="modal-backdrop" id="reject-modal"><div class="modal"><h3>Tolak Kunjungan</h3><p>Alasan penolakan akan ditampilkan kepada pegawai untuk diperbaiki.</p><form method="POST" action="{{ route('kunjungan.approvals.reject', $visit) }}">@csrf<div class="field"><label>Alasan Penolakan *</label><textarea class="textarea" name="notes" minlength="5" maxlength="2000" required placeholder="Jelaskan bagian yang harus diperbaiki..."></textarea></div><div class="modal-actions"><button type="button" class="btn btn-light" data-modal-close>Batal</button><button class="btn btn-danger">Tolak Pengajuan</button></div></form></div></div>
        @endcan
        @can('cancel', $visit)
            <div class="modal-backdrop" id="cancel-modal"><div class="modal"><h3>Batalkan Kunjungan</h3><p>Alasan pembatalan akan disimpan dalam timeline status.</p><form method="POST" action="{{ route('kunjungan.visits.cancel', $visit) }}">@csrf<div class="field"><label>Alasan Pembatalan *</label><textarea class="textarea" name="reason" minlength="5" maxlength="2000" required></textarea></div><div class="modal-actions"><button class="btn btn-light" type="button" data-modal-close>Kembali</button><button class="btn btn-danger">Batalkan Kunjungan</button></div></form></div></div>
        @endcan
    @endpush
</x-layouts.app>
