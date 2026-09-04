<x-app-layout>
    <div x-data="tanggapanSnpPage()" class="space-y-6">
        {{-- HEADER --}}
        <div class="rounded-2xl border border-blue-100 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide" style="color: #2377b9;">
                        SNP Dewas
                    </p>

                    <h1 class="mt-2 text-3xl font-bold text-slate-800">
                        Tanggapan SNP
                    </h1>

                    <p class="mt-2 text-sm text-slate-500">
                        Halaman ini digunakan untuk memberikan tanggapan terhadap butir SNP yang menjadi tanggung jawab
                        PIC.
                    </p>
                </div>


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

        {{-- FILTER --}}
        @include('layouts.snp.partials.filter-lanjutan', [
            'action' => route('snp.tanggapan.index'),
            'statusOptions' => [
                'belum' => 'Belum Ditanggapi',
                'sudah' => 'Sudah Ditanggapi',
            ],
            'keywordPlaceholder' => 'Cari ID SNP, ID butir, nomor surat, perihal, isi butir, atau tanggapan...',
        ])

        {{-- TABLE --}}
        <div class="overflow-hidden rounded-2xl border border-blue-100 bg-white shadow-sm">
            <div
                class="flex flex-col gap-3 border-b border-blue-50 px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-bold text-slate-800">
                        Daftar Butir SNP
                    </h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Setiap PIC Unit hanya bisa memberi satu tanggapan untuk setiap butir SNP.
                    </p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-600">
                                Informasi SNP
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-600">
                                Butir SNP
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-600">
                                PIC
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-600">
                                Tanggapan
                            </th>
                            <th class="px-6 py-4 text-center text-xs font-bold uppercase tracking-wider text-slate-600">
                                Aksi
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-200 bg-white">
                        @forelse ($butirs as $butir)
                            @php
                                $user = \App\Models\User::find(auth()->id());

                                $picUtama = $butir->butirPics->where('jenis_pic', 'utama')->first();
                                $picPendukung = $butir->butirPics->where('jenis_pic', 'pendukung');

                                $picUnits = $butir->butirPics->whereIn('jenis_pic', ['utama', 'pendukung']);
                                $tanggapanList = $butir->tanggapan ?? collect();

                                $tanggapanPicIds = $tanggapanList
                                    ->pluck('butir_pic_id')
                                    ->filter()
                                    ->map(fn($id) => (int) $id)
                                    ->toArray();

                                $availablePicUnits = $picUnits
                                    ->reject(fn($pic) => in_array((int) $pic->id, $tanggapanPicIds, true))
                                    ->values();

                                $semuaPicSudahTanggapan = $picUnits->count() > 0 && $availablePicUnits->count() === 0;

                                $canRespond = $user?->canAccessSnpTanggapan() ?? false;

                                $picTanggapanPayload = $picUnits
                                    ->map(function ($pic) use ($tanggapanList, $butir, $user) {
                                        $tanggapan = $tanggapanList->firstWhere('butir_pic_id', $pic->id);
                                        $sudahDireviu = $butir->reviews
                                            ->where('tahap_review', 'tanggapan')
                                            ->contains(fn ($review) => filled($review->hasil_review));
                                        $canEdit = $tanggapan && ($user?->isSuperAdmin() || $user?->hasRoleType('admin_snp') ||
                                            (! $sudahDireviu && in_array((int) $pic->unit_kerja_id, array_map('intval', $user?->unitKerjaIds() ?? []), true)));
                                        $unitLabel = ($pic->unitKerja?->kode_unit ?? '-') .
                                            ' - ' .
                                            ($pic->unitKerja?->nama_unit ?? '-');
                                        $jenisPic =
                                            $pic->jenis_pic === 'utama'
                                                ? 'PIC Utama'
                                                : 'PIC Pendukung';

                                        return [
                                            'id' => $pic->id,
                                            'tanggapan_id' => $tanggapan?->id,
                                            'can_edit' => (bool) $canEdit,
                                            'unit_label' => $unitLabel,
                                            'initial' => $pic->unitKerja?->kode_unit ?? '-',
                                            'jenis_pic' => $jenisPic,
                                            'id_butir_snp' => $butir->id_butir_snp,
                                            'isi_butir_singkat' => \Illuminate\Support\Str::limit(
                                                $butir->butir_snp,
                                                80,
                                            ),
                                            'sudah_menanggapi' => (bool) $tanggapan,
                                            'tanggapan' => $tanggapan?->tanggapan,
                                            'tanggapan_singkat' => $tanggapan
                                                ? \Illuminate\Support\Str::limit($tanggapan->tanggapan, 110)
                                                : null,
                                            'deliverables' => $tanggapan?->deliverables,
                                            'deliverables_singkat' => $tanggapan
                                                ? \Illuminate\Support\Str::limit($tanggapan->deliverables, 90)
                                                : null,
                                            'dokumen_url' => $tanggapan?->dokumen
                                                ? asset('storage/' . $tanggapan->dokumen)
                                                : null,
                                            'creator' => $tanggapan?->creator?->name ?? '-',
                                            'created_at' => $tanggapan?->created_at
                                                ? \Carbon\Carbon::parse($tanggapan->created_at)->format('d/m/Y')
                                                : '-',
                                        ];
                                    })
                                    ->values()
                                    ->all();

                                $detailTanggapanPayload = [
                                    'id' => $butir->id,
                                    'id_snp' => $butir->record?->id_snp,
                                    'id_butir_snp' => $butir->id_butir_snp,
                                    'butir_snp' => $butir->butir_snp,
                                    'pic_tanggapans' => $picTanggapanPayload,
                                ];
                            @endphp

                            <tr class="hover:bg-blue-50/40">
                                <td class="px-6 py-6 align-top">
                                    <p class="text-xs font-bold" style="color: #2377b9;">
                                        {{ $butir->record?->id_snp ?? '-' }}
                                    </p>

                                    <p class="mt-2 text-xs text-slate-700">
                                        Nomor: {{ $butir->record?->nomor_surat ?? '-' }}
                                    </p>

                                    <p class="mt-1 text-xs text-slate-700">
                                        Tanggal:
                                        {{ $butir->record?->tanggal_surat ? \Carbon\Carbon::parse($butir->record->tanggal_surat)->format('d/m/Y') : '-' }}
                                    </p>

                                    <p
                                        class="mt-3 max-w-sm text-xs font-medium uppercase leading-relaxed text-slate-800">
                                        {{ $butir->record?->perihal_surat ?? '-' }}
                                    </p>

                                    <div class="mt-4">
                                        <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                                            Dokumen Surat
                                        </p>

                                        @if ($butir->record?->dokumen)
                                            <a href="{{ route('snp.perekaman.dokumen', $butir->record->id) }}"
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
                                    <p class="text-xs font-bold" style="color: #2377b9;">
                                        {{ $butir->id_butir_snp }}
                                    </p>

                                    <p
                                        class="mt-3 max-w-lg text-xs font-medium uppercase leading-relaxed text-slate-800">
                                        {{ $butir->butir_snp }}
                                    </p>
                                </td>

                                <td class="px-6 py-6 align-top">
                                    <div>
                                        <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                                            PIC Utama
                                        </p>

                                        @if ($picUtama?->unitKerja)
                                            <span
                                                class="mt-2 inline-flex rounded-xl px-3 py-1 text-xs font-bold text-white"
                                                style="background-color: #6bb17e;">
                                                {{ $picUtama->unitKerja->kode_unit }}
                                            </span>
                                        @else
                                            <p class="mt-1 text-sm text-slate-400">-</p>
                                        @endif
                                    </div>

                                    <div class="mt-4">
                                        <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                                            PIC Pendukung
                                        </p>

                                        @if ($picPendukung->count() > 0)
                                            <div class="mt-2 flex flex-wrap gap-2">
                                                @foreach ($picPendukung as $pic)
                                                    @if ($pic->unitKerja)
                                                        <span
                                                            class="inline-flex rounded-full px-3 py-1 text-xs font-bold text-slate-700"
                                                            style="background-color: #c8e079;">
                                                            {{ $pic->unitKerja->kode_unit }}
                                                        </span>
                                                    @endif
                                                @endforeach
                                            </div>
                                        @else
                                            <p class="mt-1 text-sm text-slate-400">-</p>
                                        @endif
                                    </div>
                                </td>

                                <td class="px-6 py-6 align-top">
                                    @if ($tanggapanList->count() > 0)
                                        @if ($semuaPicSudahTanggapan)
                                            <span
                                                class="inline-flex text-center rounded-full px-3 py-1 text-xs font-bold text-white"
                                                style="background-color: #2377b9;">
                                                Tanggapan PIC Lengkap
                                            </span>
                                        @else
                                            <span
                                                class="inline-flex text-center rounded-full px-3 py-1 text-xs font-bold text-slate-700"
                                                style="background-color: #c8e079;">
                                                Tanggapan PIC Opsional
                                            </span>
                                        @endif

                                        <div class="mt-4 space-y-3">
                                            @foreach ($tanggapanList->take(2) as $item)
                                                <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                                                    <p class="text-xs font-bold uppercase tracking-wide"
                                                        style="color: #2377b9;">
                                                        {{ $item->butirPic?->unitKerja?->kode_unit ?? '-' }}
                                                        -
                                                        {{ $item->butirPic?->unitKerja?->nama_unit ?? '-' }}
                                                    </p>

                                                    <p class="mt-2 text-xs leading-relaxed text-slate-700">
                                                        {{ \Illuminate\Support\Str::limit($item->tanggapan ?? '-', 120) }}
                                                    </p>

                                                    <p class="mt-2 text-xs text-slate-500">
                                                        Deliverables:
                                                        {{ \Illuminate\Support\Str::limit($item->deliverables ?? '-', 80) }}
                                                    </p>
                                                </div>
                                            @endforeach

                                            @if ($tanggapanList->count() > 2)
                                                <p class="text-xs font-semibold text-slate-500">
                                                    + {{ $tanggapanList->count() - 2 }} tanggapan lainnya
                                                </p>
                                            @endif
                                        </div>
                                    @else
                                        <span
                                            class="inline-flex text-center rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-500">
                                            Belum Ditanggapi
                                        </span>
                                    @endif
                                </td>

                                <td class="px-6 py-6 align-top">
                                    <div class="flex flex-wrap justify-center gap-2">
                                        <button type="button"
                                            @click="openDetailModalFor(@js($detailTanggapanPayload))"
                                            class="rounded-lg px-4 py-2 text-xs font-bold text-white shadow-sm hover:opacity-90"
                                            style="background-color: #6bb17e;">
                                            Detail
                                        </button>

                                        @if ($availablePicUnits->count() > 0 && $canRespond)
                                            <button type="button"
                                                @click="selectedButir = {
                                                    id: {{ $butir->id }},
                                                    id_butir_snp: @js($butir->id_butir_snp),
                                                    id_snp: @js($butir->record?->id_snp),
                                                    pic_units: @js(
                                                        $availablePicUnits
                                                            ->map(function ($pic) {
                                                                return [
                                                                    'id' => $pic->id,
                                                                    'label' => strtoupper($pic->jenis_pic) . ' - ' . ($pic->unitKerja?->kode_unit ?? '-') . ' - ' . ($pic->unitKerja?->nama_unit ?? '-'),
                                                                ];
                                                            })
                                                            ->values(),
                                                    )
                                                }; openModal = true"
                                                class="rounded-lg px-4 py-2 text-xs font-bold text-white shadow-sm hover:opacity-90"
                                                style="background-color: #FFA500;">
                                                Beri Tanggapan
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center">
                                    <p class="text-sm font-semibold text-slate-600">
                                        Belum ada butir SNP yang dapat ditanggapi.
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
                    <span class="font-semibold text-slate-700">{{ $butirs->firstItem() ?? 0 }}</span>
                    -
                    <span class="font-semibold text-slate-700">{{ $butirs->lastItem() ?? 0 }}</span>
                    dari
                    <span class="font-semibold text-slate-700">{{ $butirs->total() }}</span>
                    entri
                </p>

                @include('layouts.partials.compact-pagination', ['paginator' => $butirs])
            </div>
        </div>

        {{-- Modal Detail Tanggapan --}}
        <div x-show="openDetailModal" x-transition.opacity
            class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/60 px-4 py-8"
            style="display: none;">
            <div @click.outside="openDetailModal = false" x-transition
                class="w-full max-w-5xl overflow-hidden rounded-2xl bg-white shadow-2xl">

                <div class="flex items-start justify-between border-b border-slate-100 px-6 py-5">
                    <div>
                        <h2 class="text-2xl font-bold text-slate-800">
                            Detail Tanggapan SNP
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
                            <input type="text" x-model="detailSearch" placeholder="Cari unit / isi tanggapan..."
                                class="w-full rounded-xl border-slate-300 pl-10 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>

                        <div class="mt-4 max-h-[52vh] space-y-3 overflow-y-auto pr-1">
                            <template x-for="pic in filteredDetailPics" :key="pic.id">
                                <button type="button" @click="selectDetailPic(pic)"
                                    class="flex w-full items-center gap-3 rounded-xl border px-4 py-3 text-left transition hover:bg-blue-50"
                                    :class="String(selectedDetailPic?.id) === String(pic.id)
                                        ? 'border-blue-300 bg-blue-50'
                                        : 'border-slate-200 bg-white'">
                                    <span
                                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                                        :class="pic.sudah_menanggapi ? 'bg-blue-100 text-blue-700' :
                                            'bg-slate-100 text-slate-500'"
                                        x-text="pic.initial"></span>

                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm font-bold text-slate-800"
                                            x-text="`${pic.initial} - ${pic.jenis_pic}`"></span>
                                        <span class="mt-1 block text-xs text-slate-500"
                                            x-text="pic.sudah_menanggapi ? 'Sudah mengisi tanggapan' : 'Belum mengisi tanggapan'"></span>
                                    </span>

                                    <svg x-show="pic.sudah_menanggapi" class="h-5 w-5 shrink-0 text-green-500"
                                        fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                </button>
                            </template>

                            <div x-show="filteredDetailPics.length === 0"
                                class="rounded-xl border border-dashed border-slate-200 px-4 py-8 text-center text-sm text-slate-400">
                                Unit/tanggapan tidak ditemukan.
                            </div>
                        </div>
                    </div>

                    <div class="max-h-[70vh] overflow-y-auto p-6">
                        <template x-if="selectedDetailPic">
                            <div class="space-y-5">
                                <div>
                                    <p class="text-lg font-bold" style="color: #2377b9;"
                                        x-text="selectedDetailPic.unit_label"></p>
                                </div>

                                <div class="grid gap-4 text-sm md:grid-cols-[130px_minmax(0,1fr)]">
                                    <p class="font-bold text-slate-600">Tipe PIC</p>
                                    <p class="text-slate-700" x-text="selectedDetailPic.jenis_pic"></p>

                                    <p class="font-bold text-slate-600">Butir SNP</p>
                                    <p class="text-slate-700" x-text="selectedDetailPic.id_butir_snp"></p>

                                    <p class="font-bold text-slate-600">Isi Butir Singkat</p>
                                    <p class="text-slate-700" x-text="selectedDetailPic.isi_butir_singkat"></p>

                                    <p class="font-bold text-slate-600">Tanggapan</p>
                                    <p class="whitespace-pre-line leading-relaxed text-slate-700"
                                        x-text="selectedDetailPic.tanggapan ?? '-'"></p>

                                    <p class="font-bold text-slate-600">Deliverables</p>
                                    <p class="whitespace-pre-line leading-relaxed text-slate-700"
                                        x-text="selectedDetailPic.deliverables ?? '-'"></p>

                                    <p class="font-bold text-slate-600">Dokumen PIC</p>
                                    <div>
                                        <template x-if="selectedDetailPic.dokumen_url">
                                            <a :href="selectedDetailPic.dokumen_url" target="_blank"
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
                                        <p class="text-slate-400" x-show="!selectedDetailPic.dokumen_url">-</p>
                                    </div>

                                    <p class="font-bold text-slate-600">Diinput oleh</p>
                                    <p class="text-slate-700" x-text="selectedDetailPic.creator"></p>

                                    <p class="font-bold text-slate-600">Tanggal Input</p>
                                    <p class="text-slate-700" x-text="selectedDetailPic.created_at"></p>

                                    <button type="button" x-show="selectedDetailPic.can_edit"
                                        @click="openEditModalFor(selectedDetailPic)"
                                        class="mt-4 rounded-lg bg-amber-500 px-4 py-2 text-xs font-bold text-white">
                                        Edit Tanggapan
                                    </button>
                                </div>
                            </div>
                        </template>

                        <div x-show="!selectedDetailPic"
                            class="rounded-xl border border-dashed border-slate-200 px-4 py-14 text-center text-sm text-slate-400">
                            Belum ada PIC pada butir ini.
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-3 border-t border-slate-100 px-6 py-4">
                    <button type="button" @click="openDetailModal = false"
                        class="rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Tutup
                    </button>

                    <button type="button" @click="selectNextDetailPic()"
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

        <div x-show="openEditModal" x-transition.opacity
            class="fixed inset-0 z-[60] flex items-start justify-center overflow-y-auto bg-slate-900/60 px-4 py-8"
            style="display: none;">
            <div @click.outside="openEditModal = false" class="w-full max-w-2xl rounded-2xl bg-white p-6 shadow-2xl">
                <h2 class="text-xl font-bold text-slate-800">Edit Tanggapan SNP</h2>
                <form method="POST" enctype="multipart/form-data" :action="`/snp/tanggapan/${editTanggapan?.tanggapan_id}`" class="mt-5 space-y-4">
                    @csrf
                    @method('PATCH')
                    <textarea name="tanggapan" rows="5" required x-model="editTanggapan.tanggapan" class="w-full rounded-xl border-slate-300"></textarea>
                    <textarea name="deliverables" rows="3" required x-model="editTanggapan.deliverables" class="w-full rounded-xl border-slate-300"></textarea>
                    <input type="file" name="dokumen" class="w-full rounded-xl border border-slate-300 p-3 text-sm">
                    <div class="flex justify-end gap-3">
                        <button type="button" @click="openEditModal = false" class="rounded-xl border px-4 py-2">Batal</button>
                        <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 font-semibold text-white">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Modal Tanggapan --}}
        <div x-show="openModal" x-transition.opacity
            class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/60 px-4 py-8"
            style="display: none;">
            <div @click.outside="openModal = false" x-transition
                class="w-full max-w-3xl overflow-hidden rounded-2xl bg-white shadow-2xl">

                <div class="flex items-start justify-between border-b border-slate-100 px-6 py-5">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-wide" style="color: #2377b9;">
                            Form Tanggapan SNP
                        </p>

                        <h2 class="mt-1 text-2xl font-bold text-slate-800">
                            <span x-text="selectedButir?.id_butir_snp"></span>
                        </h2>

                        <p class="mt-1 text-sm text-slate-500">
                            Setiap PIC Unit hanya dapat memberi satu tanggapan untuk butir SNP ini.
                        </p>
                    </div>

                    <button type="button" @click="openModal = false"
                        class="rounded-xl p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                        ✕
                    </button>
                </div>

                <form method="POST" enctype="multipart/form-data" :action="`/snp/tanggapan/${selectedButir?.id}`"
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
                                PIC Unit yang Memberi Tanggapan
                            </label>

                            <select name="butir_pic_id" required
                                class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Pilih PIC Unit</option>

                                <template x-for="pic in selectedButir?.pic_units ?? []" :key="pic.id">
                                    <option :value="pic.id" x-text="pic.label"></option>
                                </template>
                            </select>

                            <p class="mt-1 text-xs text-slate-500">
                                Pilih unit kerja PIC yang tanggapannya sedang diinput.
                            </p>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-700">
                                Tanggapan SNP
                            </label>
                            <textarea name="tanggapan" rows="4" required
                                class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                placeholder="Nomor memo tanggapan (ME/XXXX/MMYYYY)&#10;Tanggal (DD-MM-YYYY)&#10;&#10;Isi tanggapan...">{{ old('tanggapan') }}</textarea>
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
                                Opsional. Format: PDF, Word, Excel, JPG, PNG. Maksimal 5 MB.
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
                            Simpan Tanggapan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
