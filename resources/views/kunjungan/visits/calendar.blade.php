<x-layouts.app title="Kalender Kunjungan">
    <x-page-header
        title="Kalender Kunjungan"
        :description="auth()->user()->canViewAllVisits()
            ? 'Pantau jadwal seluruh pegawai, baik yang sudah berlalu maupun yang akan datang.'
            : 'Lihat seluruh jadwal kunjungan Anda, baik yang sudah berlalu maupun yang akan datang.'"
    >
        <x-slot:actions>
            <a class="btn btn-light" href="{{ route('kunjungan.calendar', ['month' => now()->format('Y-m'), 'employee_id' => $selectedEmployee?->id]) }}">Bulan Ini</a>
        </x-slot:actions>
    </x-page-header>

    <section class="card calendar-toolbar">
        <div class="calendar-navigation">
            <a class="calendar-nav-button" aria-label="Bulan sebelumnya" href="{{ route('kunjungan.calendar', ['month' => $month->subMonth()->format('Y-m'), 'employee_id' => $selectedEmployee?->id]) }}">&larr;</a>
            <div>
                <span class="calendar-kicker">PERIODE AGENDA</span>
                <h2>{{ $month->translatedFormat('F Y') }}</h2>
            </div>
            <a class="calendar-nav-button" aria-label="Bulan berikutnya" href="{{ route('kunjungan.calendar', ['month' => $month->addMonth()->format('Y-m'), 'employee_id' => $selectedEmployee?->id]) }}">&rarr;</a>
        </div>

        @if(auth()->user()->canViewAllVisits())
            <form class="calendar-filter" method="GET" action="{{ route('kunjungan.calendar') }}">
                <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
                <label for="employee_id">Tampilkan agenda</label>
                <select class="select" id="employee_id" name="employee_id" onchange="this.form.submit()">
                    <option value="">Semua pegawai</option>
                    @foreach($employees as $employee)
                        <option value="{{ $employee->id }}" @selected($selectedEmployee?->id === $employee->id)>{{ $employee->name }} — {{ $employee->organizational_unit ?: 'Unit belum tersedia' }}</option>
                    @endforeach
                </select>
            </form>
        @else
            <div class="calendar-scope">
                <span class="calendar-scope-icon">✓</span>
                <span><strong>Agenda pribadi</strong><small>Hanya kunjungan Anda yang ditampilkan</small></span>
            </div>
        @endif
    </section>

    <section class="card calendar-card">
        <div class="calendar-legend">
            <span><i class="legend-status upcoming"></i>Mendatang</span>
            <span><i class="legend-status active"></i>Berlangsung</span>
            <span><i class="legend-status past"></i>Sudah berlalu</span>
            <span><i class="legend-status pending"></i>Menunggu persetujuan</span>
        </div>
        <div class="calendar-scroll">
            <div class="calendar-grid">
                @foreach(['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'] as $weekday)
                    <div class="calendar-weekday">{{ $weekday }}</div>
                @endforeach

                @foreach($days as $day)
                    @php
                        $dayEvents = $eventsByDate[$day->toDateString()];
                    @endphp
                    <div class="calendar-day {{ !$day->isSameMonth($month) ? 'outside' : '' }} {{ $day->isToday() ? 'today' : '' }}">
                        <div class="calendar-date">
                            <span>{{ $day->day }}</span>
                            @if($day->isToday())
                                <small>Hari ini</small>
                            @endif
                        </div>
                        <div class="calendar-events">
                            @foreach($dayEvents->take(3) as $visit)
                                @php
                                    $timing = $visit->status === \App\Enums\VisitStatus::PENDING
                                        ? 'pending'
                                        : ($visit->start_at->isFuture() ? 'upcoming' : ($visit->end_at->isPast() ? 'past' : 'active'));
                                @endphp
                                <a class="calendar-event {{ $timing }}" href="{{ route('kunjungan.visits.show', $visit) }}" title="{{ $visit->title }}">
                                    <span class="calendar-event-time">{{ $visit->start_at->isSameDay($day) ? 'Agenda' : 'Lanjut' }}</span>
                                    <strong>{{ $visit->title }}</strong>
                                    <small>{{ $visit->picUnitKerja?->kode_unit }} - {{ $visit->picUnitKerja?->nama_unit }} · {{ $visit->destination_list->count() }} lokasi</small>
                                </a>
                            @endforeach
                            @if($dayEvents->count() > 3)
                                <a class="calendar-more" href="{{ route('kunjungan.visits.index', ['date_from' => $day->toDateString(), 'date_to' => $day->toDateString()]) }}">+{{ $dayEvents->count() - 3 }} agenda lainnya</a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="calendar-summary-grid">
        <div class="card calendar-summary">
            <span class="calendar-summary-icon upcoming">→</span>
            <div><strong>{{ $visits->filter(fn($visit) => $visit->start_at->isFuture())->count() }}</strong><span>Agenda mendatang di periode ini</span></div>
        </div>
        <div class="card calendar-summary">
            <span class="calendar-summary-icon past">✓</span>
            <div><strong>{{ $visits->filter(fn($visit) => $visit->end_at->isPast())->count() }}</strong><span>Agenda yang sudah berlalu</span></div>
        </div>
        <div class="card calendar-summary">
            <span class="calendar-summary-icon total">#</span>
            <div><strong>{{ $visits->count() }}</strong><span>Total agenda pada tampilan kalender</span></div>
        </div>
    </section>
</x-layouts.app>

