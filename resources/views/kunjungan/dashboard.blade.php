<x-layouts.app title="Dashboard Kunjungan">
    <x-page-header title="Monitoring Kunjungan Kerja" description="Pantau perencanaan, pelaksanaan, dan laporan kunjungan unit kerja BPJS Ketenagakerjaan.">
        @can('create', \App\Models\Kunjungan\Visit::class)
            <x-slot:actions><a href="{{ route('kunjungan.visits.create') }}" class="btn btn-primary">+ Ajukan Kunjungan</a></x-slot:actions>
        @endcan
    </x-page-header>

    @if(auth()->user()->canCreateKunjungan())
        <section class="report-health {{ $reportHealth['missing'] > 0 ? 'needs-attention' : 'complete' }}">
            <div class="report-health-icon">
                @if($reportHealth['missing'] > 0)
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 3h9l4 4v14H6z"/><path d="M14 3v5h5M9 13h6M9 17h4"/><circle cx="18" cy="18" r="4" fill="currentColor" stroke="white"/><path d="M18 16v2.5M18 20h.01" stroke="white"/></svg>
                @else
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 3h9l4 4v14H6z"/><path d="M14 3v5h5M9 13l2 2 4-4"/></svg>
                @endif
            </div>
            <div class="report-health-copy">
                <span class="report-health-eyebrow">STATUS LAPORAN ANDA</span>
                @if($reportHealth['missing'] > 0)
                    <h2>{{ $reportHealth['missing'] }} laporan masih perlu diunggah</h2>
                    <p>Kunjungan sudah selesai. Segera lengkapi laporan PDF agar seluruh administrasi Anda tuntas.</p>
                @elseif($reportHealth['required'] > 0)
                    <h2>Semua laporan sudah lengkap</h2>
                    <p>Bagus, seluruh kunjungan yang telah selesai sudah memiliki laporan PDF.</p>
                @else
                    <h2>Belum ada kewajiban laporan</h2>
                    <p>Laporan yang perlu dilengkapi akan muncul di sini setelah kunjungan selesai.</p>
                @endif
                <div class="report-progress" aria-label="Kelengkapan laporan {{ $reportHealth['percentage'] }} persen"><span style="width: {{ $reportHealth['percentage'] }}%"></span></div>
                <small>{{ $reportHealth['uploaded'] }} dari {{ $reportHealth['required'] }} laporan selesai · {{ $reportHealth['percentage'] }}% lengkap</small>
            </div>
            @if($reportHealth['missing'] > 0)
                <div class="report-health-actions">
                    @foreach($outstandingReports as $reportVisit)
                        <a href="{{ route('kunjungan.visits.show', $reportVisit) }}"><span>{{ $reportVisit->end_at->translatedFormat('d M') }}</span><strong>{{ Str::limit($reportVisit->title, 42) }}</strong><i>Upload →</i></a>
                    @endforeach
                    <a class="report-all-link" href="{{ route('kunjungan.visits.index', ['status' => 'WAITING_REPORT']) }}">Lihat semua laporan tertunda</a>
                </div>
            @else
                <a class="btn report-calendar-button" href="{{ route('kunjungan.calendar') }}">Buka Kalender</a>
            @endif
        </section>
    @endif

    <div class="stats-grid {{ auth()->user()->canViewAllVisits() ? '' : 'stats-grid-4' }}">
        <x-stat-card :label="auth()->user()->canViewAllVisits() ? 'Total Kunjungan' : 'Total Kunjungan Saya'" :value="$stats['total']" :href="route('kunjungan.visits.index')" />
        <x-stat-card label="Kunjungan Bulan Ini" :value="$stats['month']" tone="info" :href="route('kunjungan.visits.index', ['date_from'=>now()->startOfMonth()->toDateString(),'date_to'=>now()->endOfMonth()->toDateString()])" />
        @if(auth()->user()->canViewAllVisits())
            <x-stat-card label="Sedang Berlangsung" :value="$stats['ongoing']" tone="warning" :href="route('kunjungan.visits.index', ['status'=>'ONGOING'])" />
        @else
            <x-stat-card label="Kunjungan Mendatang" :value="$stats['upcoming']" tone="warning" :href="route('kunjungan.visits.index', ['status'=>'APPROVED'])" />
        @endif
        <x-stat-card label="Belum Upload Laporan" :value="$stats['waiting_report']" tone="danger" :href="route('kunjungan.visits.index', ['status'=>'WAITING_REPORT'])" />
        @if(auth()->user()->canModerateKunjungan())
            <x-stat-card label="Menunggu Persetujuan" :value="$stats['pending']" tone="success" :href="route('kunjungan.approvals.index')" />
        @endif
    </div>

    <div class="dashboard-grid">
        <section class="card section-card">
            <div class="section-heading"><div><h2>Jumlah Kunjungan per Bulan</h2><p>Kunjungan valid berdasarkan jadwal mulai tahun {{ now()->year }}.</p></div></div>
            <div class="chart-wrap"><canvas data-monthly-chart='@json($chartData)'></canvas></div>
        </section>
        <section class="card section-card">
            <div class="section-heading"><div><h2>Kunjungan Terdekat</h2><p>Rencana yang telah disetujui dan segera dilaksanakan.</p></div></div>
            @if($upcomingVisit)
                <div class="upcoming-highlight"><div class="visit-number">{{ $upcomingVisit->visit_number }}</div><div class="visit-title">{{ $upcomingVisit->title }}</div>
                    <div class="detail-list"><div class="detail-item"><small>Lokasi tujuan</small><strong>{{ $upcomingVisit->destination_list->first()?->nama_unit_kerja }}{{ $upcomingVisit->destination_list->count() > 1 ? ' +'.($upcomingVisit->destination_list->count() - 1).' lokasi' : '' }}</strong></div><div class="detail-item"><small>Tanggal Mulai</small><strong>{{ $upcomingVisit->start_at->translatedFormat('d M Y') }}</strong></div><div class="detail-item"><small>Tanggal Selesai</small><strong>{{ $upcomingVisit->end_at->translatedFormat('d M Y') }}</strong></div><div class="detail-item"><small>PIC Unit / Peserta</small><strong>{{ $upcomingVisit->picUnitKerja?->kode_unit }} - {{ $upcomingVisit->picUnitKerja?->nama_unit }} · {{ $upcomingVisit->participants_count }} orang</strong></div></div>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:18px"><x-status-badge :status="$upcomingVisit->status"/><a class="btn btn-primary btn-sm" href="{{ route('kunjungan.visits.show',$upcomingVisit) }}">Lihat Detail</a></div>
                </div>
            @else<x-empty-state title="Belum ada kunjungan mendatang." description="Rencana yang sudah dijadwalkan akan tampil di sini."/>@endif
        </section>
    </div>

    <section class="card section-card" style="margin-top:20px">
        <div class="section-heading"><div><h2>{{ auth()->user()->canViewAllVisits() ? 'Aktivitas Kunjungan Terbaru' : 'Riwayat Terbaru' }}</h2><p>Perkembangan kunjungan yang terakhir diajukan.</p></div><a class="btn btn-light btn-sm" href="{{ route('kunjungan.visits.index') }}">Lihat Semua</a></div>
        @if($recentVisits->isNotEmpty())<div class="visit-mini-list">@foreach($recentVisits as $visit)<x-visit-card :visit="$visit"/>@endforeach</div>@else<x-empty-state/>@endif
    </section>
</x-layouts.app>
