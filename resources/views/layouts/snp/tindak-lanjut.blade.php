<x-app-layout>
    <div x-data="{
        openModal: false,
        openDetailModal: false,
        butirSearch: '',
        selectedButirId: '',
        selectedButir: null,
        detailButir: null,
        selectedDetailTlId: null,
        detailSearch: '',
        butirs: @js(
    $butirSiapTindakLanjut
        ->map(
            fn($butir) => [
                'id' => $butir->id,
                'id_butir_snp' => $butir->id_butir_snp,
                'id_snp' => $butir->record?->id_snp,
                'nomor_surat' => $butir->record?->nomor_surat,
                'perihal_surat' => $butir->record?->perihal_surat,
                'butir_snp' => $butir->butir_snp,
                'jth_tempo' => $butir->record?->jth_tempo ? \Carbon\Carbon::parse($butir->record->jth_tempo)->format('Y-m-d') : null,
                'jth_tempo_label' => $butir->record?->jth_tempo ? \Carbon\Carbon::parse($butir->record->jth_tempo)->format('d/m/Y') : '-',
                'pic_units' => $butir->butirPics
                    ->whereIn('jenis_pic', ['utama', 'pendukung'])
                    ->reject(function ($pic) use ($butir) {
                        return $butir->tindakLanjuts
                            ->where('putaran_tl', $butir->putaran_tl_aktif ?? 1)
                            ->where('butir_pic_id', $pic->id)
                            ->count() > 0;
                    })
                    ->map(
                        fn($pic) => [
                            'id' => $pic->id,
                            'label' => strtoupper($pic->jenis_pic) . ' - ' . ($pic->unitKerja?->kode_unit ?? '-') . ' - ' . ($pic->unitKerja?->nama_unit ?? '-'),
                        ],
                    )
                    ->values(),
            ],
        )
        ->values(),
),

        get filteredButirs() {
            const keyword = this.butirSearch.toLowerCase().trim();

            if (!keyword) {
                return this.butirs;
            }

            return this.butirs.filter((butir) => {
                return String(butir.id_butir_snp || '').toLowerCase().includes(keyword) ||
                    String(butir.id_snp || '').toLowerCase().includes(keyword) ||
                    String(butir.nomor_surat || '').toLowerCase().includes(keyword) ||
                    String(butir.perihal_surat || '').toLowerCase().includes(keyword) ||
                    String(butir.butir_snp || '').toLowerCase().includes(keyword);
            });
        },

        selectButir(butir) {
            this.selectedButir = butir;
            this.selectedButirId = butir.id;
            this.butirSearch = `${butir.id_butir_snp} - ${butir.nomor_surat ?? '-'}`;
        },

        resetButir() {
            this.selectedButir = null;
            this.selectedButirId = '';
            this.butirSearch = '';
        },

        openDetailModalFor(butir) {
            this.detailButir = butir;
            this.detailSearch = '';
            this.selectedDetailTlId = butir.tindak_lanjuts?.[0]?.id ?? null;
            this.openDetailModal = true;
        },

        selectDetailTl(tindakLanjut) {
            this.selectedDetailTlId = tindakLanjut.id;
        },

        get detailTindakLanjuts() {
            return this.detailButir?.tindak_lanjuts ?? [];
        },

        get filteredDetailTindakLanjuts() {
            const keyword = this.detailSearch.toLowerCase().trim();

            if (!keyword) {
                return this.detailTindakLanjuts;
            }

            return this.detailTindakLanjuts.filter((tindakLanjut) => {
                return String(tindakLanjut.unit_label || '').toLowerCase().includes(keyword) ||
                    String(tindakLanjut.tindak_lanjut || '').toLowerCase().includes(keyword) ||
                    String(tindakLanjut.deliverables || '').toLowerCase().includes(keyword);
            });
        },

        get selectedDetailTl() {
            const selected = this.detailTindakLanjuts.find((tindakLanjut) => {
                return String(tindakLanjut.id) === String(this.selectedDetailTlId);
            });

            if (selected) {
                return selected;
            }

            return this.filteredDetailTindakLanjuts[0] ?? null;
        },

        get nextDetailTl() {
            if (this.filteredDetailTindakLanjuts.length === 0) {
                return null;
            }

            const currentIndex = this.filteredDetailTindakLanjuts.findIndex((tindakLanjut) => {
                return String(tindakLanjut.id) === String(this.selectedDetailTl?.id);
            });

            if (currentIndex < 0) {
                return this.filteredDetailTindakLanjuts[0];
            }

            return this.filteredDetailTindakLanjuts[(currentIndex + 1) % this.filteredDetailTindakLanjuts.length];
        },

        selectNextDetailTl() {
            if (this.nextDetailTl) {
                this.selectDetailTl(this.nextDetailTl);
            }
        }
    }" class="space-y-6">

        <div class="rounded-2xl border border-blue-100 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide" style="color: #2377b9;">
                        SNP Dewas
                    </p>

                    <h1 class="mt-2 text-3xl font-bold text-slate-800">
                        Tindak Lanjut SNP
                    </h1>

                    <p class="mt-2 text-sm text-slate-500">
                        Halaman ini digunakan untuk menginput tindak lanjut terhadap butir SNP yang sudah masuk tahap
                        tindak lanjut direksi.
                    </p>
                </div>

                <button type="button" @click="openModal = true"
                    class="inline-flex items-center justify-center rounded-xl px-5 py-3 text-sm font-bold text-white shadow-sm hover:opacity-90"
                    style="background-color: #2377b9;">
                    Tambah Tindak Lanjut
                </button>
            </div>
        </div>

        @if (session('success'))
            <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-700">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
                {{ session('error') }}
            </div>
        @endif

        @include('layouts.snp.partials.filter-lanjutan', [
            'action' => route('snp.tindak-lanjut.index'),
            'statusOptions' => $statusOptions,
            'keywordPlaceholder' =>
                'Cari ID SNP, ID butir, nomor surat, perihal, tanggapan, tindak lanjut, atau deliverables...',
        ])

        <div class="overflow-hidden rounded-2xl border border-blue-100 bg-white shadow-sm">
            <div class="border-b border-blue-50 px-6 py-5">
                <h2 class="text-lg font-bold text-slate-800">
                    Riwayat Tindak Lanjut
                </h2>

                <p class="mt-1 text-sm text-slate-500">
                    Data akan muncul setelah butir SNP masuk tahap tindak lanjut direksi.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-600">
                                Informasi SNP
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-600">
                                Tindak Lanjut
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-600">
                                Reviu
                            </th>
                            <th class="px-6 py-4 text-center text-xs font-bold uppercase tracking-wider text-slate-600">
                                Aksi
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-200 bg-white">
                        @forelse ($tindakLanjutRows as $row)
                            @php
                                $butir = $row['butir'];
                                $items = $row['items'];
                                $availablePicUnits = $row['available_pic_units'];
                                $semuaPicSudahTl = $row['semua_pic_sudah_tl'];
                                $item = $items->first();
                                $record = $butir?->record;

                                $reviewTerakhir = $butir->reviews
                                    ->where('tahap_review', 'tindak_lanjut')
                                    ->sortByDesc('putaran_tl')
                                    ->sortByDesc('id')
                                    ->first();

                                $reviewTanggapan = $butir?->reviews
                                    ?->where('tahap_review', 'tanggapan')
                                    ->where('status', 'dalam_proses_tindak_lanjut_direksi')
                                    ->sortByDesc('id')
                                    ->first();

                                $detailTindakLanjutPayload = [
                                    'id' => $butir?->id,
                                    'id_snp' => $record?->id_snp,
                                    'id_butir_snp' => $butir?->id_butir_snp,
                                    'butir_snp' => $butir?->butir_snp,
                                    'tindak_lanjuts' => $items
                                        ->map(function ($tl) use ($butir) {
                                            $jenisPic =
                                                $tl->butirPic?->jenis_pic === 'utama'
                                                    ? 'PIC Utama'
                                                    : 'PIC Pendukung';
                                            $unitLabel = ($tl->butirPic?->unitKerja?->kode_unit ?? '-') .
                                                ' - ' .
                                                ($tl->butirPic?->unitKerja?->nama_unit ?? '-');

                                            return [
                                                'id' => $tl->id,
                                                'unit_label' => $unitLabel,
                                                'initial' => $tl->butirPic?->unitKerja?->kode_unit ?? '-',
                                                'jenis_pic' => $jenisPic,
                                                'putaran_tl' => $tl->putaran_tl ?? 1,
                                                'id_butir_snp' => $butir?->id_butir_snp,
                                                'isi_butir_singkat' => \Illuminate\Support\Str::limit(
                                                    $butir?->butir_snp ?? '-',
                                                    80,
                                                ),
                                                'tindak_lanjut' => $tl->tindak_lanjut,
                                                'tindak_lanjut_singkat' => \Illuminate\Support\Str::limit(
                                                    $tl->tindak_lanjut ?? '-',
                                                    110,
                                                ),
                                                'deliverables' => $tl->deliverables,
                                                'deliverables_singkat' => \Illuminate\Support\Str::limit(
                                                    $tl->deliverables ?? '-',
                                                    90,
                                                ),
                                                'dokumen_url' => $tl->dokumen
                                                    ? asset('storage/' . $tl->dokumen)
                                                    : null,
                                                'jth_tempo' => $tl->jth_tempo
                                                    ? \Carbon\Carbon::parse($tl->jth_tempo)->format('d/m/Y')
                                                    : '-',
                                                'creator' => $tl->creator?->name ?? '-',
                                                'created_at' => $tl->created_at
                                                    ? \Carbon\Carbon::parse($tl->created_at)->format('d/m/Y')
                                                    : '-',
                                            ];
                                        })
                                        ->values()
                                        ->all(),
                                ];
                            @endphp

                            <tr class="hover:bg-blue-50/40">
                                <td class="px-6 py-6 align-top">
                                    <p class="text-xs font-bold" style="color: #2377b9;">
                                        {{ $record?->id_snp ?? '-' }}
                                    </p>

                                    <p class="mt-2 text-xs text-slate-700">
                                        Nomor: {{ $record?->nomor_surat ?? '-' }}
                                    </p>

                                    <p class="mt-1 text-xs text-slate-700">
                                        Tanggal:
                                        {{ $record?->tanggal_surat ? \Carbon\Carbon::parse($record->tanggal_surat)->format('d/m/Y') : '-' }}
                                    </p>

                                    <p class="mt-1 text-xs text-slate-700">
                                        Butir: {{ $butir?->id_butir_snp ?? '-' }}
                                    </p>

                                    <p
                                        class="mt-3 max-w-md whitespace-pre-line text-xs font-medium uppercase leading-relaxed text-slate-800">
                                        {{ $butir?->butir_snp ?? '-' }}
                                    </p>

                                    <div class="mt-4">
                                        <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                                            Dokumen Reviu Tanggapan
                                        </p>

                                        @if ($reviewTanggapan?->dokumen)
                                            <a href="{{ route('snp.reviu.dokumen', $reviewTanggapan->id) }}"
                                                class="mt-2 inline-flex rounded-lg px-3 py-2 text-xs font-bold text-white hover:opacity-90"
                                                style="background-color: #2377b9;">
                                                Download Dokumen
                                            </a>
                                        @else
                                            <p class="mt-1 text-xs text-slate-400">
                                                -
                                            </p>
                                        @endif
                                    </div>
                                </td>

                                <td class="px-6 py-6 align-top">
                                    @if ($items->count() > 0)
                                        @if ($semuaPicSudahTl)
                                            <span class="rounded-full px-3 py-1 text-xs font-bold text-white"
                                                style="background-color: #6bb17e;">
                                                Semua PIC Sudah Menindaklanjuti
                                            </span>
                                        @else
                                            <span class="rounded-full px-3 py-1 text-xs font-bold text-slate-700"
                                                style="background-color: #c8e079;">
                                                Sebagian Sudah Menindaklanjuti
                                            </span>
                                        @endif

                                        <div class="mt-4 space-y-3">
                                            @foreach ($items->take(2) as $tl)
                                                <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                                                    <p class="text-xs font-bold uppercase tracking-wide"
                                                        style="color: #2377b9;">
                                                        {{ $tl->butirPic?->unitKerja?->kode_unit ?? '-' }}
                                                        -
                                                        {{ $tl->butirPic?->unitKerja?->nama_unit ?? '-' }}
                                                    </p>

                                                    <p class="mt-2 max-w-lg text-xs leading-relaxed text-slate-800">
                                                        {{ \Illuminate\Support\Str::limit($tl->tindak_lanjut ?? '-', 120) }}
                                                    </p>

                                                    <p class="mt-2 text-xs text-slate-500">
                                                        Deliverables:
                                                        {{ \Illuminate\Support\Str::limit($tl->deliverables ?? '-', 80) }}
                                                    </p>

                                                    <p class="mt-2 text-xs text-slate-500">
                                                        Jatuh Tempo:
                                                        <span class="font-bold text-slate-700">
                                                            {{ $tl->jth_tempo ? \Carbon\Carbon::parse($tl->jth_tempo)->format('d/m/Y') : '-' }}
                                                        </span>
                                                    </p>
                                                </div>
                                            @endforeach

                                            @if ($items->count() > 2)
                                                <p class="text-xs font-semibold text-slate-500">
                                                    + {{ $items->count() - 2 }} tindak lanjut lainnya
                                                </p>
                                            @endif
                                        </div>
                                    @else
                                        <span
                                            class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-500">
                                            Belum Ditindaklanjuti
                                        </span>

                                        <p class="mt-4 text-xs text-slate-500">
                                            Surat/butir ini sudah masuk tahap tindak lanjut direksi, tetapi tindak
                                            lanjut belum diinput.
                                        </p>

                                        <p class="mt-4 text-xs text-slate-500">
                                            Jatuh Tempo:
                                            <span class="font-bold text-slate-700">
                                                {{ $record?->jth_tempo ? \Carbon\Carbon::parse($record->jth_tempo)->format('d/m/Y') : '-' }}
                                            </span>
                                        </p>
                                    @endif
                                </td>

                                <td class="px-6 py-6 align-top">
                                    @if ($reviewTerakhir)
                                        <span
                                            class="inline-flex rounded-xl px-3 py-1 text-center text-xs font-bold text-white"
                                            style="background-color: #2377b9;">
                                            {{ ucwords(str_replace('_', ' ', $reviewTerakhir->status)) }}
                                        </span>

                                        <p class="mt-4 text-xs text-slate-500">
                                            Komite:
                                            <span class="font-bold">
                                                {{ $reviewTerakhir->komite?->kode_komite ?? '-' }}
                                            </span>
                                        </p>

                                        @if ($reviewTerakhir->hasil_review)
                                            <p class="mt-4 text-xs font-bold uppercase tracking-wide text-slate-500">
                                                Hasil Reviu
                                            </p>

                                            <p class="mt-2 max-w-lg whitespace-pre-line text-xs text-slate-800">
                                                {{ $reviewTerakhir->hasil_review }}
                                            </p>
                                        @endif

                                        @if ($reviewTerakhir->deliverables)
                                            <p class="mt-4 text-xs font-bold uppercase tracking-wide text-slate-500">
                                                Deliverables Reviu
                                            </p>

                                            <p class="mt-2 max-w-lg whitespace-pre-line text-xs text-slate-800">
                                                {{ $reviewTerakhir->deliverables }}
                                            </p>
                                        @endif
                                    @else
                                        @if ($item)
                                            <span
                                                class="inline-flex rounded-xl px-3 py-1 text-center text-xs font-bold text-white"
                                                style="background-color: #2377b9;">
                                                Belum Direviu
                                            </span>
                                        @else
                                            <span
                                                class="inline-flex rounded-xl px-3 py-1 text-center text-xs font-bold text-white"
                                                style="background-color: #64748b;">
                                                Belum Ditindaklanjuti
                                            </span>
                                        @endif
                                    @endif
                                </td>

                                <td class="px-6 py-6 align-top">
                                    <div class="flex flex-wrap justify-center gap-2">
                                        <button type="button"
                                            @click="openDetailModalFor(@js($detailTindakLanjutPayload))"
                                            class="rounded-lg px-4 py-2 text-xs font-bold text-white shadow-sm hover:opacity-90"
                                            style="background-color: #6bb17e;">
                                            Detail
                                        </button>

                                        @if ($butir?->kompilasiTindakLanjut?->status === 'dalam_proses_reviu_dewas')
                                            <button type="button" disabled
                                                class="cursor-not-allowed rounded-lg bg-slate-200 px-4 py-2 text-xs font-bold text-slate-400">
                                                Sudah Masuk Kompilasi
                                            </button>
                                        @elseif ($availablePicUnits->count() > 0)
                                            <button type="button"
                                                @click="selectedButir = {
                                                        id: {{ $butir->id }},
                                                        id_butir_snp: @js($butir->id_butir_snp),
                                                        id_snp: @js($record?->id_snp),
                                                        putaran_tl: {{ $row['putaran_tl'] ?? 1 }},
                                                        nomor_surat: @js($record?->nomor_surat),
                                                        butir_snp: @js($butir->butir_snp),
                                                        jth_tempo_label: @js($record?->jth_tempo ? \Carbon\Carbon::parse($record->jth_tempo)->format('d/m/Y') : '-'),
                                                        pic_units: @js(
    $availablePicUnits
        ->map(
            fn($pic) => [
                'id' => $pic->id,
                'label' => strtoupper($pic->jenis_pic) . ' - ' . ($pic->unitKerja?->kode_unit ?? '-') . ' - ' . ($pic->unitKerja?->nama_unit ?? '-'),
            ],
        )
        ->values(),
)
                                                    };
                                                                selectedButirId = {{ $butir->id }};
                                                                openModal = true"
                                                class="rounded-lg px-4 py-2 text-xs font-bold text-white text-center hover:opacity-90"
                                                style="background-color: #2377b9;">
                                                Lakukan Tindak Lanjut
                                            </button>
                                        @else
                                            <button type="button" disabled
                                                class="cursor-not-allowed rounded-lg bg-slate-200 px-4 py-2 text-xs font-bold text-slate-400">
                                                Semua PIC Sudah TL
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-12 text-center">
                                    <p class="text-sm font-semibold text-slate-600">
                                        Belum ada surat/butir yang harus ditindaklanjuti.
                                    </p>

                                    <p class="mt-1 text-xs text-slate-400">
                                        Data akan muncul setelah reviu tanggapan berstatus Dalam Proses Tindak Lanjut
                                        Direksi.
                                    </p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div
                class="flex flex-col gap-3 border-t border-slate-100 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-slate-500">
                    Menampilkan
                    <span class="font-semibold text-slate-700">{{ $tindakLanjutRows->firstItem() ?? 0 }}</span>
                    -
                    <span class="font-semibold text-slate-700">{{ $tindakLanjutRows->lastItem() ?? 0 }}</span>
                    dari
                    <span class="font-semibold text-slate-700">{{ $tindakLanjutRows->total() }}</span>
                    entri
                </p>

                @include('layouts.partials.compact-pagination', ['paginator' => $tindakLanjutRows])
            </div>
        </div>

        {{-- Modal Detail Tindak Lanjut --}}
        <div x-show="openDetailModal" x-transition.opacity
            class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/60 px-4 py-8"
            style="display: none;">
            <div @click.outside="openDetailModal = false" x-transition
                class="w-full max-w-5xl overflow-hidden rounded-2xl bg-white shadow-2xl">

                <div class="flex items-start justify-between border-b border-slate-100 px-6 py-5">
                    <div>
                        <h2 class="text-2xl font-bold text-slate-800">
                            Detail Tindak Lanjut SNP
                        </h2>
                        <p class="mt-1 text-sm font-semibold text-slate-500" x-text="detailButir?.id_snp ?? '-'"></p>
                    </div>

                    <button type="button" @click="openDetailModal = false"
                        class="rounded-xl p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="grid max-h-[70vh] overflow-hidden lg:grid-cols-[340px_minmax(0,1fr)]">
                    <div class="border-b border-slate-100 p-5 lg:border-b-0 lg:border-r">
                        <div class="relative">
                            <svg class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400"
                                fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="m21 21-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z" />
                            </svg>
                            <input type="text" x-model="detailSearch"
                                placeholder="Cari unit / isi tindak lanjut..."
                                class="w-full rounded-xl border-slate-300 pl-10 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>

                        <div class="mt-4 max-h-[52vh] space-y-3 overflow-y-auto pr-1">
                            <template x-for="tindakLanjut in filteredDetailTindakLanjuts" :key="tindakLanjut.id">
                                <button type="button" @click="selectDetailTl(tindakLanjut)"
                                    class="flex w-full items-center gap-3 rounded-xl border px-4 py-3 text-left transition hover:bg-blue-50"
                                    :class="String(selectedDetailTl?.id) === String(tindakLanjut.id)
                                        ? 'border-blue-300 bg-blue-50'
                                        : 'border-slate-200 bg-white'">
                                    <span
                                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-blue-100 text-xs font-bold text-blue-700"
                                        x-text="tindakLanjut.initial"></span>

                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm font-bold text-slate-800"
                                            x-text="`${tindakLanjut.initial} - Putaran ${tindakLanjut.putaran_tl}`"></span>
                                        <span class="mt-1 block text-xs text-slate-500"
                                            x-text="tindakLanjut.jenis_pic"></span>
                                    </span>

                                    <svg class="h-5 w-5 shrink-0 text-green-500" fill="none"
                                        stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                </button>
                            </template>

                            <div x-show="filteredDetailTindakLanjuts.length === 0"
                                class="rounded-xl border border-dashed border-slate-200 px-4 py-8 text-center text-sm text-slate-400">
                                Tindak lanjut tidak ditemukan.
                            </div>
                        </div>
                    </div>

                    <div class="max-h-[70vh] overflow-y-auto p-6">
                        <template x-if="selectedDetailTl">
                            <div class="space-y-5">
                                <div>
                                    <p class="text-lg font-bold" style="color: #2377b9;"
                                        x-text="selectedDetailTl.unit_label"></p>
                                </div>

                                <div class="grid gap-4 text-sm md:grid-cols-[150px_minmax(0,1fr)]">
                                    <p class="font-bold text-slate-600">Tipe PIC</p>
                                    <p class="text-slate-700" x-text="selectedDetailTl.jenis_pic"></p>

                                    <p class="font-bold text-slate-600">Putaran TL</p>
                                    <p class="text-slate-700" x-text="selectedDetailTl.putaran_tl"></p>

                                    <p class="font-bold text-slate-600">Butir SNP</p>
                                    <p class="text-slate-700" x-text="selectedDetailTl.id_butir_snp"></p>

                                    <p class="font-bold text-slate-600">Isi Butir Singkat</p>
                                    <p class="text-slate-700" x-text="selectedDetailTl.isi_butir_singkat"></p>

                                    <p class="font-bold text-slate-600">Tindak Lanjut</p>
                                    <p class="whitespace-pre-line leading-relaxed text-slate-700"
                                        x-text="selectedDetailTl.tindak_lanjut ?? '-'"></p>

                                    <p class="font-bold text-slate-600">Deliverables</p>
                                    <p class="whitespace-pre-line leading-relaxed text-slate-700"
                                        x-text="selectedDetailTl.deliverables ?? '-'"></p>

                                    <p class="font-bold text-slate-600">Jatuh Tempo</p>
                                    <p class="text-slate-700" x-text="selectedDetailTl.jth_tempo"></p>

                                    <p class="font-bold text-slate-600">Dokumen PIC</p>
                                    <div>
                                        <template x-if="selectedDetailTl.dokumen_url">
                                            <a :href="selectedDetailTl.dokumen_url" target="_blank"
                                                class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-xs font-bold text-white hover:opacity-90"
                                                style="background-color: #2377b9;">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor"
                                                    stroke-width="1.8" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M12 3v12m0 0 4-4m-4 4-4-4M4 21h16" />
                                                </svg>
                                                Download Dokumen
                                            </a>
                                        </template>
                                        <p class="text-slate-400" x-show="!selectedDetailTl.dokumen_url">-</p>
                                    </div>

                                    <p class="font-bold text-slate-600">Diinput oleh</p>
                                    <p class="text-slate-700" x-text="selectedDetailTl.creator"></p>

                                    <p class="font-bold text-slate-600">Tanggal Input</p>
                                    <p class="text-slate-700" x-text="selectedDetailTl.created_at"></p>
                                </div>
                            </div>
                        </template>

                        <div x-show="!selectedDetailTl"
                            class="rounded-xl border border-dashed border-slate-200 px-4 py-14 text-center text-sm text-slate-400">
                            Belum ada tindak lanjut untuk butir ini.
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-3 border-t border-slate-100 px-6 py-4">
                    <button type="button" @click="openDetailModal = false"
                        class="rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Tutup
                    </button>

                    <button type="button" @click="selectNextDetailTl()"
                        class="inline-flex items-center gap-2 rounded-xl border border-blue-200 bg-white px-5 py-3 text-sm font-semibold hover:bg-blue-50"
                        style="color: #2377b9;">
                        Unit Berikutnya
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        {{-- Modal Tambah Tindak Lanjut --}}
        <div x-show="openModal" x-transition.opacity
            class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/60 px-4 py-8"
            style="display: none;">
            <div @click.outside="openModal = false" x-transition
                class="w-full max-w-4xl overflow-hidden rounded-2xl bg-white shadow-2xl">

                <div class="flex items-start justify-between border-b border-slate-100 px-6 py-5">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-wide" style="color: #2377b9;">
                            Form Tindak Lanjut SNP
                        </p>

                        <h2 class="mt-1 text-2xl font-bold text-slate-800">
                            Tambah Tindak Lanjut
                        </h2>

                        <p class="mt-1 text-sm text-slate-500">
                            Pilih butir SNP yang sudah berstatus dalam proses tindak lanjut direksi.
                        </p>
                    </div>

                    <button type="button" @click="openModal = false"
                        class="rounded-xl p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                        ✕
                    </button>
                </div>

                <form method="POST" action="{{ route('snp.tindak-lanjut.store') }}" enctype="multipart/form-data"
                    class="px-6 py-6">
                    @csrf

                    @if ($errors->any())
                        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                            <p class="font-semibold">Data belum bisa disimpan.</p>
                            <ul class="mt-2 list-disc pl-5">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="grid gap-5">
                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-700">
                                Pilih Butir SNP
                            </label>

                            <input type="hidden" name="butir_id" :value="selectedButirId" required>

                            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                                <div class="border-b border-slate-200 p-3">
                                    <input type="text" x-model="butirSearch"
                                        @input="selectedButir = null; selectedButirId = ''"
                                        placeholder="Ketik ID butir, ID SNP, nomor surat, atau isi butir..."
                                        class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                </div>

                                <div class="max-h-72 overflow-y-auto p-3">
                                    <div class="space-y-2">
                                        <template x-for="butir in filteredButirs" :key="butir.id">
                                            <button type="button" @click="selectButir(butir)"
                                                class="w-full rounded-xl border border-slate-100 p-4 text-left transition hover:bg-blue-50"
                                                :class="String(selectedButirId) === String(butir.id) ?
                                                    'bg-blue-50 ring-2 ring-blue-200' : ''">
                                                <div class="flex flex-col gap-1">
                                                    <p class="text-sm font-bold" style="color: #2377b9;"
                                                        x-text="butir.id_butir_snp"></p>

                                                    <p class="text-xs text-slate-500">
                                                        <span x-text="butir.id_snp"></span>
                                                        <span> • </span>
                                                        <span x-text="butir.nomor_surat ?? '-'"></span>
                                                    </p>

                                                    <p class="mt-2 text-sm font-semibold uppercase leading-relaxed text-slate-800"
                                                        x-text="butir.butir_snp"></p>

                                                    <p class="mt-2 text-xs text-slate-500">
                                                        Jatuh Tempo:
                                                        <span class="font-bold text-slate-700"
                                                            x-text="butir.jth_tempo_label"></span>
                                                    </p>
                                                </div>
                                            </button>
                                        </template>
                                    </div>

                                    <div x-show="filteredButirs.length === 0"
                                        class="py-8 text-center text-sm text-slate-400">
                                        Butir SNP tidak ditemukan.
                                    </div>
                                </div>
                            </div>

                            <template x-if="selectedButir">
                                <div class="mt-3 rounded-xl bg-blue-50 p-4">
                                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                                        Butir Terpilih
                                    </p>

                                    <p class="mt-1 text-sm font-bold" style="color: #2377b9;"
                                        x-text="selectedButir.id_butir_snp"></p>

                                    <p class="mt-2 text-sm text-slate-700">
                                        Jatuh tempo tindak lanjut:
                                        <span class="font-bold" x-text="selectedButir.jth_tempo_label"></span>
                                    </p>

                                    <button type="button" @click="resetButir()"
                                        class="mt-3 text-xs font-bold text-red-500 hover:text-red-600">
                                        Ganti Butir
                                    </button>
                                </div>
                            </template>

                            @if ($butirSiapTindakLanjut->count() === 0)
                                <p class="mt-2 text-xs font-semibold text-red-500">
                                    Belum ada butir SNP yang siap ditindaklanjuti.
                                </p>
                            @endif
                        </div>

                        <template x-if="selectedButir">
                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-700">
                                    PIC Unit yang Melakukan Tindak Lanjut
                                </label>

                                <select name="butir_pic_id" required
                                    class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="">Pilih PIC Unit</option>

                                    <template x-for="pic in selectedButir?.pic_units ?? []" :key="pic.id">
                                        <option :value="pic.id" x-text="pic.label"></option>
                                    </template>
                                </select>

                                <p class="mt-1 text-xs text-slate-500">
                                    Pilih unit kerja PIC yang tindak lanjutnya sedang diinput.
                                </p>
                            </div>
                        </template>

                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-700">
                                Tindak Lanjut SNP
                            </label>

                            <textarea name="tindak_lanjut" rows="4" required
                                class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                placeholder="Nomor memo tindak lanjut (ME/XXXX/MMYYYY)&#10;Tanggal (DD-MM-YYYY)&#10;&#10;Isi tindak lanjut...">{{ old('tindak_lanjut') }}</textarea>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-700">
                                Deliverables
                            </label>

                            <textarea name="deliverables" rows="3" required
                                class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                placeholder="Masukkan deliverables...">{{ old('deliverables') }}</textarea>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-700">
                                Dokumen Pendukung (Dokumen Memo & Deliverables)
                            </label>

                            <input type="file" name="dokumen"
                                class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">

                            <p class="mt-1 text-xs text-slate-500">
                                Opsional. Format PDF, Word, Excel, JPG, PNG. Maksimal 5 MB.
                            </p>
                        </div>
                    </div>

                    <div class="mt-8 flex justify-end gap-3 border-t border-slate-100 pt-5">
                        <button type="button" @click="openModal = false"
                            class="rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                            Batal
                        </button>

                        <button type="submit"
                            class="rounded-xl px-5 py-3 text-sm font-semibold text-white shadow-sm hover:opacity-90"
                            style="background-color: #2377b9;">
                            Simpan Tindak Lanjut
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
