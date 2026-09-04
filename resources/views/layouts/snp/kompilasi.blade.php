<x-app-layout>
    <div x-data="{ openModal: false, selectedItem: null }" class="space-y-6">
        <div class="rounded-2xl border border-blue-100 bg-white p-6 shadow-sm">
            <p class="text-sm font-semibold uppercase tracking-wide" style="color: #2377b9;">
                SNP Dewas
            </p>

            <h1 class="mt-2 text-3xl font-bold text-slate-800">
                Kompilasi Tanggapan & Tindak Lanjut SNP
            </h1>

            <p class="mt-2 text-sm text-slate-500">
                Merangkum tanggapan atau tindak lanjut PIC Unit sebelum masuk ke reviu Dewas. Input PIC bersifat opsional.
            </p>
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

        <div x-data="{ openFilter: false }" class="rounded-2xl border border-blue-100 bg-white shadow-sm">
            <button type="button" @click="openFilter = !openFilter"
                class="flex w-full items-center justify-between px-6 py-4 text-left">
                <div>
                    <p class="font-semibold text-slate-800">Filter Kompilasi</p>
                    <p class="text-sm text-slate-500">Cari data yang siap dikompilasi atau sudah masuk proses reviu.</p>
                </div>

                <svg class="h-5 w-5 text-slate-500 transition-transform" :class="{ 'rotate-180': openFilter }"
                    fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m19 9-7 7-7-7" />
                </svg>
            </button>

            <div x-show="openFilter" x-transition class="border-t border-blue-50 px-6 py-5" style="display: none;">
                <form method="GET" action="{{ route('snp.kompilasi.index') }}">
                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-slate-700">Tahap Kompilasi</label>
                            <select name="tahap_kompilasi"
                                class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Semua Tahap</option>
                                <option value="tanggapan" @selected(request('tahap_kompilasi') === 'tanggapan')>Tanggapan</option>
                                <option value="tindak_lanjut" @selected(request('tahap_kompilasi') === 'tindak_lanjut')>Tindak Lanjut</option>
                            </select>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-slate-700">Status Kompilasi</label>
                            <select name="status"
                                class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Semua Status</option>
                                @foreach ($statusOptions as $value => $label)
                                    <option value="{{ $value }}" @selected(request('status') === $value)>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-slate-700">Cluster</label>
                            <select name="cluster_id"
                                class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Semua Cluster</option>
                                @foreach ($clusters as $cluster)
                                    <option value="{{ $cluster->id }}" @selected(request('cluster_id') == $cluster->id)>
                                        {{ $cluster->nama_cluster }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-slate-700">Direktorat</label>
                            <select name="direktorat_id"
                                class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Semua Direktorat</option>
                                @foreach ($direktorats as $direktorat)
                                    <option value="{{ $direktorat->id }}" @selected(request('direktorat_id') == $direktorat->id)>
                                        {{ $direktorat->nama_direktorat }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-slate-700">Unit Kerja Utama</label>
                            <select name="unit_kerja_utama_id"
                                class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Semua Unit Kerja Utama</option>
                                @foreach ($unitKerjas as $unit)
                                    <option value="{{ $unit->id }}" @selected(request('unit_kerja_utama_id') == $unit->id)>
                                        {{ $unit->kode_unit }} - {{ $unit->nama_unit }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-slate-700">Komite</label>
                            <select name="komite_id"
                                class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Semua Komite</option>
                                @foreach ($komites as $komite)
                                    <option value="{{ $komite->id }}" @selected(request('komite_id') == $komite->id)>
                                        {{ $komite->kode_komite }} - {{ $komite->nama_komite }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="md:col-span-2 xl:col-span-3">
                            <label class="mb-2 block text-sm font-medium text-slate-700">Kata Kunci</label>
                            <input type="text" name="keyword" value="{{ request('keyword') }}"
                                placeholder="Cari ID SNP, ID butir, nomor surat, perihal, tanggapan, tindak lanjut..."
                                class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                    </div>

                    <div class="mt-5 flex justify-end gap-3">
                        <a href="{{ route('snp.kompilasi.index') }}"
                            class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                            Reset
                        </a>

                        <button type="submit"
                            class="rounded-xl px-4 py-2 text-sm font-semibold text-white hover:opacity-90"
                            style="background-color: #2377b9;">
                            Terapkan Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-blue-100 bg-white shadow-sm">
            <div class="border-b border-blue-50 px-6 py-5">
                <h2 class="text-lg font-bold text-slate-800">
                    Daftar Kompilasi SNP
                </h2>
                <p class="mt-1 text-sm text-slate-500">
                    Data muncul setelah seluruh PIC Unit mengisi tanggapan atau minimal satu tindak lanjut.
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
                                Data PIC Unit
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-600">
                                Status Kompilasi
                            </th>
                            <th class="px-6 py-4 text-center text-xs font-bold uppercase tracking-wider text-slate-600">
                                Aksi
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-200 bg-white">
                        @forelse ($kompilasiItems as $item)
                            @php
                                $butir = $item->butir;
                                $record = $butir?->record;
                                $kompilasi = $item->kompilasi;
                                $locked = $item->status === 'dalam_proses_reviu_dewas';
                                $statusLabel = $locked ? 'Dalam Proses Reviu Dewas' : 'Belum Dikompilasi';
                                $statusColor = $locked ? '#2377b9' : '#64748b';
                                $visibleDataUnit = $item->data_unit->take(2);
                                $remainingDataUnitCount = max($item->data_unit->count() - $visibleDataUnit->count(), 0);
                                $remainingDataUnitLabel =
                                    $item->tahap === 'tanggapan' ? 'tanggapan lainnya' : 'tindak lanjut lainnya';
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
                                        Tanggal Surat:
                                        {{ $record?->tanggal_surat ? \Carbon\Carbon::parse($record->tanggal_surat)->format('d/m/Y') : '-' }}
                                    </p>

                                    <p class="mt-1 text-xs text-slate-700">
                                        Perihal: {{ $record?->perihal_surat ?? '-' }}
                                    </p>

                                    <div class="mt-4 rounded-xl border border-slate-100 bg-white p-4">
                                        <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                                            Butir SNP
                                        </p>

                                        <p class="mt-2 text-xs font-bold" style="color: #2377b9;">
                                            {{ $butir?->id_butir_snp ?? '-' }}
                                        </p>

                                        <p class="mt-3 whitespace-pre-line text-xs text-slate-800">
                                            {{ $butir?->butir_snp ?? '-' }}
                                        </p>
                                    </div>

                                    <div class="mt-4">
                                        <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                                            Tahap
                                        </p>
                                        <span class="mt-2 inline-flex rounded-xl px-3 py-1 text-xs font-bold text-white"
                                            style="background-color: {{ $item->tahap === 'tanggapan' ? '#2377b9' : '#6bb17e' }};">
                                            {{ $item->tahap_label }}
                                        </span>
                                    </div>
                                </td>

                                <td class="px-6 py-6 align-top">
                                    @forelse ($visibleDataUnit as $picId => $rows)
                                        @php
                                            $firstRow = $rows->first();
                                            $unit = $firstRow?->butirPic?->unitKerja;
                                            $unitLabel = trim(
                                                ($unit?->kode_unit ?? '-') . ' - ' . ($unit?->nama_unit ?? '-'),
                                            );
                                        @endphp

                                        <div class="mb-4 rounded-xl border border-slate-200 bg-white p-4">
                                            <div class="flex flex-wrap items-start justify-between gap-3">
                                                <p class="text-xs font-bold uppercase tracking-wide"
                                                    style="color: #2377b9;">
                                                    {{ $unitLabel }}
                                                </p>

                                                <span
                                                    class="rounded-full bg-slate-100 px-3 py-1 text-[11px] font-bold text-slate-500">
                                                    {{ $rows->count() }} data
                                                </span>
                                            </div>

                                            @php
                                                $previewRow = $rows->first();
                                                $previewText =
                                                    $item->tahap === 'tanggapan'
                                                        ? $previewRow?->tanggapan
                                                        : $previewRow?->tindak_lanjut;
                                            @endphp

                                            <div class="mt-3 rounded-xl bg-slate-50 p-4">
                                                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                                                    {{ $item->tahap === 'tanggapan' ? 'Tanggapan Singkat' : 'Tindak Lanjut Singkat' }}
                                                </p>
                                                <p class="mt-2 text-xs leading-relaxed text-slate-800">
                                                    {{ \Illuminate\Support\Str::limit($previewText ?? '-', 140) }}
                                                </p>

                                                <p class="mt-3 text-xs font-bold uppercase tracking-wide text-slate-500">
                                                    Deliverables
                                                </p>
                                                <p class="mt-2 text-xs leading-relaxed text-slate-800">
                                                    {{ \Illuminate\Support\Str::limit($previewRow?->deliverables ?? '-', 100) }}
                                                </p>

                                                @if ($item->tahap === 'tanggapan' && $previewRow?->ubah_tgl)
                                                    <p class="mt-3 text-xs text-slate-500">
                                                        Pengajuan ubah tanggal:
                                                        <span class="font-bold text-slate-700">
                                                            {{ \Carbon\Carbon::parse($previewRow->ubah_tgl)->format('d/m/Y') }}
                                                        </span>
                                                    </p>
                                                @endif

                                                @if ($item->tahap === 'tindak_lanjut')
                                                    <p class="mt-3 text-xs text-slate-500">
                                                        Jatuh Tempo:
                                                        <span class="font-bold text-slate-700">
                                                            {{ $previewRow?->jth_tempo ? \Carbon\Carbon::parse($previewRow->jth_tempo)->format('d/m/Y') : '-' }}
                                                        </span>
                                                    </p>
                                                @endif

                                                <p class="mt-3 text-xs text-slate-500">
                                                    Diinput oleh:
                                                    <span class="font-bold text-slate-700">
                                                        {{ $previewRow?->creator?->name ?? '-' }}
                                                    </span>
                                                </p>
                                            </div>

                                            @if ($rows->count() > 1)
                                                <p class="mt-3 text-xs font-semibold text-slate-500">
                                                    + {{ $rows->count() - 1 }} data lainnya dari unit ini.
                                                </p>
                                            @endif
                                        </div>
                                    @empty
                                        <p class="text-xs text-slate-400">Belum ada data PIC Unit.</p>
                                    @endforelse

                                    @if ($remainingDataUnitCount > 0)
                                        <p class="text-xs font-bold text-slate-500">
                                            + {{ $remainingDataUnitCount }} {{ $remainingDataUnitLabel }}
                                        </p>
                                    @endif
                                </td>

                                <td class="px-6 py-6 align-top">
                                    <span class="inline-flex rounded-xl px-3 py-1 text-xs font-bold text-white"
                                        style="background-color: {{ $statusColor }};">
                                        {{ $statusLabel }}
                                    </span>

                                    @if ($kompilasi?->hasil_kompilasi)
                                        <p class="mt-4 text-xs font-bold uppercase tracking-wide text-slate-500">
                                            Hasil Kompilasi
                                        </p>
                                        <p class="mt-2 whitespace-pre-line text-xs text-slate-800">
                                            {{ $kompilasi->hasil_kompilasi }}
                                        </p>
                                    @endif

                                    @if ($kompilasi?->deliverables)
                                        <p class="mt-4 text-xs font-bold uppercase tracking-wide text-slate-500">
                                            Deliverables Kompilasi
                                        </p>
                                        <p class="mt-2 whitespace-pre-line text-xs text-slate-800">
                                            {{ $kompilasi->deliverables }}
                                        </p>
                                    @endif

                                    @if ($kompilasi?->ubah_tgl)
                                        <p class="mt-4 text-xs font-bold uppercase tracking-wide text-slate-500">
                                            Pengajuan Ubah Tanggal
                                        </p>

                                        <p class="mt-2 text-xs text-slate-800">
                                            {{ \Carbon\Carbon::parse($kompilasi->ubah_tgl)->format('d/m/Y') }}
                                        </p>

                                        <p class="mt-1 text-xs text-slate-500">
                                            Status:
                                            <span class="font-bold text-slate-700">
                                                {{ ucwords(str_replace('_', ' ', $kompilasi->status_pengajuan_tgl ?? '-')) }}
                                            </span>
                                        </p>
                                    @endif

                                    @if ($kompilasi?->dokumen)
                                        <div class="mt-4">
                                            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                                                Dokumen Kompilasi
                                            </p>
                                            <a href="{{ route('snp.kompilasi.dokumen', $kompilasi->id) }}"
                                                class="mt-2 inline-flex rounded-lg px-3 py-2 text-xs font-bold text-white hover:opacity-90"
                                                style="background-color: #2377b9;">
                                                Download Dokumen
                                            </a>
                                        </div>
                                    @endif
                                </td>

                                <td class="px-6 py-6 align-top">
                                    <div class="flex justify-center">
                                        @if ($locked)
                                            <button type="button" disabled
                                                class="cursor-not-allowed rounded-lg bg-slate-200 px-4 py-2 text-xs font-bold text-slate-400">
                                                Sudah Masuk Reviu
                                            </button>
                                        @elseif (! $canCreateKompilasi)
                                            <span
                                                class="rounded-lg bg-slate-100 px-4 py-2 text-center text-xs font-bold text-slate-500">
                                                Hanya dapat melihat
                                            </span>
                                        @else
                                            <button type="button"
                                                @click="selectedItem = {
                                                    id: {{ $butir->id }},
                                                    id_butir_snp: @js($butir->id_butir_snp),
                                                    tahap: @js($item->tahap),
                                                    putaran_tl: @js($item->putaran_tl ?? 1),
                                                    tahap_label: @js($item->tahap_label),
                                                    hasil_kompilasi: @js($kompilasi?->hasil_kompilasi ?? ''),
                                                    deliverables: @js($kompilasi?->deliverables ?? ''),
                                                    ubah_tgl: @js($kompilasi?->ubah_tgl ? \Carbon\Carbon::parse($kompilasi->ubah_tgl)->format('Y-m-d') : ''),
                                                }; openModal = true"
                                                class="rounded-lg px-4 py-2 text-xs font-bold text-white shadow-sm hover:opacity-90"
                                                style="background-color: #FFA500;">
                                                Kompilasi
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-12 text-center">
                                    <p class="text-sm font-semibold text-slate-600">
                                        Belum ada data yang siap dikompilasi.
                                    </p>
                                    <p class="mt-1 text-xs text-slate-400">
                                        Kompilasi tanggapan muncul setelah butir dibuat. Kompilasi tindak lanjut muncul setelah reviu tanggapan membuka tahap tindak lanjut.
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
                    <span class="font-semibold text-slate-700">{{ $kompilasiItems->firstItem() ?? 0 }}</span>
                    -
                    <span class="font-semibold text-slate-700">{{ $kompilasiItems->lastItem() ?? 0 }}</span>
                    dari
                    <span class="font-semibold text-slate-700">{{ $kompilasiItems->total() }}</span>
                    entri
                </p>

                @include('layouts.partials.compact-pagination', ['paginator' => $kompilasiItems])
            </div>
        </div>

        @if ($canCreateKompilasi)
            <div x-show="openModal" x-transition.opacity
                class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/60 px-4 py-8"
                style="display: none;">
                <div @click.outside="openModal = false" x-transition
                    class="w-full max-w-3xl overflow-hidden rounded-2xl bg-white shadow-2xl">

                <div class="flex items-start justify-between border-b border-slate-100 px-6 py-5">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-wide" style="color: #2377b9;">
                            <span x-text="selectedItem?.tahap_label"></span>
                        </p>

                        <h2 class="mt-1 text-2xl font-bold text-slate-800">
                            <span x-text="selectedItem?.id_butir_snp"></span>
                        </h2>

                        <p class="mt-1 text-sm text-slate-500">
                            Setelah disimpan, data masuk ke proses reviu Dewas dan tidak bisa dikompilasi ulang.
                        </p>
                    </div>

                    <button type="button" @click="openModal = false"
                        class="rounded-xl p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                        ✕
                    </button>
                </div>

                <form method="POST" :action="`/snp/kompilasi/${selectedItem?.id}`" enctype="multipart/form-data"
                    class="px-6 py-6">
                    @csrf

                    <input type="hidden" name="tahap_kompilasi" :value="selectedItem?.tahap">

                    <input type="hidden" name="putaran_tl" :value="selectedItem?.putaran_tl ?? 1">

                    <div class="grid gap-5">
                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-700">
                                Hasil Kompilasi
                            </label>

                            <textarea name="hasil_kompilasi" rows="5" required x-model="selectedItem.hasil_kompilasi"
                                class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                placeholder="Nomor surat kompilasi (B/XXXX/MMYYYY)&#10;Tanggal (DD-MM-YYYY)&#10;&#10;Isi kompilasi..."></textarea>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-700">
                                Deliverables Kompilasi
                            </label>

                            <textarea name="deliverables" rows="3" x-model="selectedItem.deliverables"
                                class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                placeholder="Opsional. Masukkan deliverables hasil kompilasi..."></textarea>
                        </div>

                        <template x-if="selectedItem?.tahap === 'tanggapan'">
                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-700">
                                    Pengajuan Ubah Tanggal Jatuh Tempo
                                </label>

                                <input type="date" name="ubah_tgl" x-model="selectedItem.ubah_tgl"
                                    class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">

                                <p class="mt-1 text-xs text-slate-500">
                                    Opsional. Diisi jika hasil kompilasi tanggapan mengajukan perubahan jatuh tempo.
                                </p>
                            </div>
                        </template>

                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-700">
                                Dokumen Kompilasi (Dokumen Surat & Deliverables)
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
                            Simpan & Kirim ke Reviu Dewas
                        </button>
                    </div>
                </form>
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
