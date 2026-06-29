<x-app-layout>
    {{-- <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-slate-800">
            Statistik
        </h2>
    </x-slot> --}}

    @php
        $statusOptions = [
            'terbit' => 'Terbit',
            'dalam_proses' => 'Dalam Proses',
            'diusulkan_tuntas' => 'Diusulkan Tuntas',
            'selesai_tuntas' => 'Selesai Tuntas',
        ];
        $butirProgressStatuses = [
            'terbit' => ['label' => 'Terbit', 'bar' => 'bg-slate-400', 'dot' => 'bg-slate-400'],
            'dalam_proses' => ['label' => 'Dalam Proses', 'bar' => 'bg-[#c8e079]', 'dot' => 'bg-[#c8e079]'],
            'diusulkan_tuntas' => ['label' => 'Diusulkan Tuntas', 'bar' => 'bg-[#6bb17e]', 'dot' => 'bg-[#6bb17e]'],
            'selesai_tuntas' => ['label' => 'Selesai Tuntas', 'bar' => 'bg-[#2377b9]', 'dot' => 'bg-[#2377b9]'],
        ];
    @endphp

    <div class="mx-auto max-w-[1500px] space-y-5 px-4 py-5 sm:px-6 lg:px-8">
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-sidewas-blue">Statistik Dashboard</p>
                    <h1 class="mt-2 text-2xl font-bold text-slate-900">Monitoring Tindak Lanjut Pengawasan</h1>
                    <p class="mt-2 text-sm text-slate-600">Data mengikuti akses role aktif pada akun ini.</p>
                </div>
                <span class="inline-flex w-fit items-center gap-2 rounded-full border border-blue-200 bg-blue-50 px-4 py-2 text-xs font-semibold text-sidewas-blue">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25h1.5v6h-1.5v-6ZM12 6.75h.008v.008H12V6.75Zm9 5.25a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                    Scope: {{ $allowedTypes->pluck('label')->join(', ') ?: 'Tidak ada modul' }}
                </span>
            </div>
        </section>

        <form method="GET" action="{{ route('dashboard') }}" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="grid gap-4 lg:grid-cols-[1fr_1fr_1fr_1fr_auto_auto] lg:items-end">
                <label class="block">
                    <span class="text-xs font-semibold text-slate-700">Jenis Pengawasan</span>
                    <select name="jenis_rapat" class="mt-2 w-full rounded-xl border-slate-300 text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Semua</option>
                        @foreach ($allowedTypes as $type)
                            <option value="{{ $type['code'] }}" @selected($filters['jenis_rapat'] === $type['code'])>{{ $type['label'] }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="text-xs font-semibold text-slate-700">Interval Bulan</span>
                    <select name="interval_bulan" class="mt-2 w-full rounded-xl border-slate-300 text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="all" @selected($filters['interval_bulan'] === 'all')>Semua Bulan</option>
                        <option value="1-3" @selected($filters['interval_bulan'] === '1-3')>Bulan 1 - 3</option>
                        <option value="4-6" @selected($filters['interval_bulan'] === '4-6')>Bulan 4 - 6</option>
                        <option value="7-9" @selected($filters['interval_bulan'] === '7-9')>Bulan 7 - 9</option>
                        <option value="10-12" @selected($filters['interval_bulan'] === '10-12')>Bulan 10 - 12</option>
                    </select>
                </label>
                <label class="block">
                    <span class="text-xs font-semibold text-slate-700">Status Tindak Lanjut</span>
                    <select name="status" class="mt-2 w-full rounded-xl border-slate-300 text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Semua</option>
                        @foreach ($statusOptions as $value => $label)
                            <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="text-xs font-semibold text-slate-700">Unit Kerja</span>
                    <select name="unit_kerja_id" class="mt-2 w-full rounded-xl border-slate-300 text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Semua</option>
                        @foreach ($unitKerjas as $unitKerja)
                            <option value="{{ $unitKerja->id }}" @selected((int) $filters['unit_kerja_id'] === $unitKerja->id)>
                                {{ $unitKerja->kode_unit }} - {{ $unitKerja->nama_unit }}
                            </option>
                        @endforeach
                    </select>
                </label>
                <a href="{{ route('dashboard') }}" class="inline-flex justify-center rounded-xl border border-slate-300 px-5 py-3 text-xs font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">Reset</a>
                <button type="submit" class="inline-flex justify-center rounded-xl bg-sidewas-blue px-5 py-3 text-xs font-semibold text-white shadow-sm transition hover:bg-blue-700">Terapkan</button>
            </div>
        </form>

        <section class="grid gap-4 xl:grid-cols-[1fr_1.35fr]">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-bold text-slate-900">Statistik Hasil Pengawasan</h2>
                <p class="mt-1 text-sm text-slate-500">Jumlah surat per jenis hasil pengawasan berdasarkan status surat.</p>
                <div class="mt-4 h-64">
                    <canvas id="suratPerJenisChart"></canvas>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-bold text-slate-900">Statistik Tindak Lanjut Hasil Pengawasan</h2>
                <p class="mt-1 text-sm text-slate-500">Progress per butir, dibedakan berdasarkan status tindak lanjut.</p>
                <div class="mt-4 flex flex-wrap gap-3">
                    @foreach ($butirProgressStatuses as $config)
                        <span class="inline-flex items-center gap-2 text-xs font-semibold text-slate-600">
                            <span class="h-2.5 w-2.5 rounded-full {{ $config['dot'] }}"></span>
                            {{ $config['label'] }}
                        </span>
                    @endforeach
                </div>
                <div class="mt-5 space-y-5">
                    @forelse ($moduleStats as $module)
                        @php
                            $totalModuleButir = (int) $module['total_butir'];
                        @endphp
                        <div>
                            <div class="mb-2 flex flex-wrap items-center justify-between gap-2 text-sm">
                                <div>
                                    <span class="font-bold text-slate-800">{{ $module['label'] }}</span>
                                    <span class="ml-2 text-xs font-semibold text-slate-500">{{ number_format($totalModuleButir, 0, ',', '.') }} butir</span>
                                </div>
                                <span class="font-bold text-slate-900">{{ number_format($module['progress'], 1, ',', '.') }}% selesai tuntas</span>
                            </div>
                            <div class="flex h-4 overflow-hidden rounded-full bg-slate-100">
                                @foreach ($butirProgressStatuses as $statusKey => $config)
                                    @php
                                        $statusCount = (int) ($module['status_butir'][$statusKey] ?? 0);
                                        $statusWidth = $totalModuleButir > 0 ? ($statusCount / $totalModuleButir) * 100 : 0;
                                    @endphp
                                    @if ($statusWidth > 0)
                                        <div class="{{ $config['bar'] }}" style="width: {{ $statusWidth }}%" title="{{ $config['label'] }}: {{ $statusCount }} butir"></div>
                                    @endif
                                @endforeach
                            </div>
                            <div class="mt-2 grid gap-2 text-xs text-slate-500 sm:grid-cols-2 xl:grid-cols-4">
                                @foreach ($butirProgressStatuses as $statusKey => $config)
                                    @php
                                        $statusCount = (int) ($module['status_butir'][$statusKey] ?? 0);
                                    @endphp
                                    <span>{{ $config['label'] }}: <strong class="text-slate-700">{{ number_format($statusCount, 0, ',', '.') }}</strong></span>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <p class="py-12 text-center text-sm font-semibold text-slate-500">Belum ada modul yang bisa ditampilkan.</p>
                    @endforelse
                </div>
            </div>
        </section>

        <section class="grid gap-4 xl:grid-cols-[1.3fr_1fr]">
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 p-5">
                    <h2 class="text-base font-bold text-slate-900">Status Tindak Lanjut yang Perlu Perhatian</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-left text-xs">
                        <thead class="bg-slate-50 text-[11px] uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-5 py-3">Hasil Pengawasan</th>
                                <th class="px-5 py-3">No Surat</th>
                                <th class="px-5 py-3">Butir</th>
                                <th class="px-5 py-3">Status</th>
                                <th class="px-5 py-3">Jatuh Tempo</th>
                                <th class="px-5 py-3">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($attentionRows as $row)
                                <tr>
                                    <td class="px-5 py-4 font-semibold text-slate-700">{{ $row['jenis'] }}</td>
                                    <td class="px-5 py-4">
                                        <p class="font-bold text-sidewas-blue">{{ $row['id'] }}</p>
                                        <p class="mt-1 max-w-xs text-xs text-slate-500">{{ $row['perihal'] }}</p>
                                    </td>
                                    <td class="px-5 py-4 font-semibold text-slate-700">{{ $row['butir'] }}</td>
                                    <td class="px-5 py-4">
                                        <span class="inline-flex rounded-lg px-3 py-1 text-xs font-bold {{ $row['status_class'] }}">{{ $row['status'] }}</span>
                                    </td>
                                    <td class="px-5 py-4 font-semibold text-red-500">{{ $row['jatuh_tempo'] }}</td>
                                    <td class="px-5 py-4">
                                        @if ($row['reminder_gmail_url'])
                                            <a href="{{ $row['reminder_gmail_url'] }}" target="_blank" rel="noopener"
                                                class="inline-flex rounded-lg bg-sidewas-blue px-3 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-blue-700"
                                                title="Kirim pengingat ke {{ implode(', ', $row['reminder_recipients']) }}">
                                                Pengingat
                                            </a>
                                        @else
                                            <button type="button" disabled
                                                class="cursor-not-allowed rounded-lg bg-slate-200 px-3 py-2 text-xs font-bold text-slate-500 shadow-sm"
                                                title="Belum ada email PIC aktif untuk butir ini.">
                                                Pengingat
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-5 py-12 text-center font-semibold text-slate-500">Belum ada data yang perlu perhatian.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <h2 class="text-base font-bold text-slate-900">Aktivitas Terbaru</h2>
                </div>
                <div class="mt-4 space-y-4">
                    @forelse ($recentActivities as $activity)
                        <div class="flex gap-3 border-b border-slate-100 pb-4 last:border-0">
                            <span class="mt-1 flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-blue-50 text-xs font-bold text-sidewas-blue">
                                {{ strtoupper(substr($activity->type_code ?? '-', 0, 1)) }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-slate-800">{{ $activity->description ?: str($activity->action ?? '-')->replace('_', ' ')->title() }}</p>
                                <p class="mt-1 text-xs text-slate-500">Oleh {{ $activity->user->name ?? '-' }}</p>
                            </div>
                            <time class="shrink-0 text-xs font-semibold text-slate-500">{{ optional($activity->created_at)->format('d/m/Y H:i') }}</time>
                        </div>
                    @empty
                        <p class="py-12 text-center text-xs font-semibold text-slate-500">Belum ada aktivitas terbaru.</p>
                    @endforelse
                </div>
            </div>
        </section>
    </div>

    <script type="application/json" id="dashboard-chart-data">@json($chartData)</script>
</x-app-layout>
