<x-app-layout>
    <div class="space-y-6">
        <div class="rounded-2xl border border-blue-100 bg-white p-6 shadow-sm">
            <p class="text-sm font-semibold uppercase tracking-wide" style="color: #2377b9;">
                SNP Dewas
            </p>

            <h1 class="mt-2 text-3xl font-bold text-slate-800">
                Cetak Report SNP
            </h1>

            <p class="mt-2 text-sm text-slate-500">
                Pilih satu atau lebih surat SNP untuk dicetak ke PDF.
            </p>
        </div>

        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                {{ $errors->first() }}
            </div>
        @endif

        <div class="rounded-2xl border border-blue-100 bg-white shadow-sm">
            <div class="border-b border-blue-50 px-6 py-5">
                <form method="GET" action="{{ route('snp.report.index') }}">
                    <div class="grid gap-4 md:grid-cols-4">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-slate-700">
                                Direktorat Penanggung Jawab
                            </label>
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
                            <label class="mb-2 block text-sm font-medium text-slate-700">
                                Unit Kerja Utama
                            </label>
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
                            <label class="mb-2 block text-sm font-medium text-slate-700">
                                PIC Pendukung
                            </label>
                            <select name="unit_kerja_pendukung_id"
                                class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Semua PIC Pendukung</option>
                                @foreach ($unitKerjas as $unit)
                                    <option value="{{ $unit->id }}" @selected(request('unit_kerja_pendukung_id') == $unit->id)>
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

                        <div>
                            <label class="mb-2 block text-sm font-medium text-slate-700">
                                Status
                            </label>
                            <select name="status"
                                class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Semua Status</option>
                                <option value="draft" @selected(request('status') === 'draft')>Draft</option>
                                <option value="dalam_proses" @selected(request('status') === 'dalam_proses')>Dalam Proses</option>
                                <option value="selesai" @selected(request('status') === 'selesai')>Selesai</option>
                            </select>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-slate-700">
                                Tanggal Mulai
                            </label>
                            <input type="date" name="tanggal_mulai" value="{{ request('tanggal_mulai') }}"
                                class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-slate-700">
                                Tanggal Selesai
                            </label>
                            <input type="date" name="tanggal_selesai" value="{{ request('tanggal_selesai') }}"
                                class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-slate-700">
                                Kata Kunci
                            </label>
                            <input type="text" name="keyword" value="{{ request('keyword') }}"
                                placeholder="Cari ID SNP, nomor surat, perihal..."
                                class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                    </div>

                    <div class="mt-4 flex justify-end gap-3">
                        <a href="{{ route('snp.report.index') }}"
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

        <form method="POST" id="reportForm">
            @csrf

            <div class="overflow-hidden rounded-2xl border border-blue-100 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-blue-50 px-6 py-5">
                    <div>
                        <h2 class="text-lg font-bold text-slate-800">
                            Daftar Surat SNP
                        </h2>
                        <p class="mt-1 text-sm text-slate-500">
                            Centang surat yang ingin dimasukkan ke report.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <button type="button" id="openReportFormatModalBtn"
                            class="rounded-xl px-5 py-3 text-sm font-bold text-white shadow-sm hover:opacity-90"
                            style="background-color: #2377b9;">
                            Cetak Report
                        </button>

                        <button type="button" id="openCustomReportModalBtn"
                            class="rounded-xl px-5 py-3 text-sm font-bold text-white shadow-sm hover:opacity-90"
                            style="background-color: #6bb17e;">
                            Cetak Report Custom
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th
                                    class="px-6 py-4 text-center text-xs font-bold uppercase tracking-wider text-slate-600">
                                    Pilih
                                </th>
                                <th
                                    class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-600">
                                    ID SNP
                                </th>
                                <th
                                    class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-600">
                                    Informasi Surat
                                </th>
                                <th
                                    class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-600">
                                    Jumlah Butir
                                </th>
                                <th
                                    class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-600">
                                    Status
                                </th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-100 bg-white">
                            @forelse ($records as $record)
                                <tr class="hover:bg-blue-50/40">
                                    <td class="px-6 py-5 text-center align-top">
                                        @php
                                            $butirsForReport = $record->butirSnp
                                                ->map(function ($butir) {
                                                    $unitOptions = $butir->butirPics
                                                        ->whereIn('jenis_pic', ['utama', 'pendukung'])
                                                        ->map(function ($pic) {
                                                            return [
                                                                'id' => $pic->unitKerja?->id,
                                                                'label' => trim(
                                                                    ($pic->unitKerja?->kode_unit ?? '-') .
                                                                        ' - ' .
                                                                        ($pic->unitKerja?->nama_unit ?? '-'),
                                                                ),
                                                            ];
                                                        })
                                                        ->filter(fn ($unit) => !empty($unit['id']))
                                                        ->unique('id')
                                                        ->values();

                                                    return [
                                                        'id' => $butir->id,
                                                        'id_butir_snp' => $butir->id_butir_snp,
                                                        'butir_snp' => \Illuminate\Support\Str::limit(
                                                            $butir->butir_snp,
                                                            120,
                                                        ),
                                                        'tanggapan_units' => $unitOptions,
                                                        'tindak_lanjut_units' => $unitOptions,
                                                    ];
                                                })
                                                ->values();
                                        @endphp

                                        <input type="checkbox" name="record_ids[]" value="{{ $record->id }}"
                                            data-record-label="{{ $record->nomor_surat }}"
                                            data-butirs='@json($butirsForReport)'
                                            class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                    </td>

                                    <td class="px-6 py-5 align-top">
                                        <p class="text-sm font-bold" style="color: #2377b9;">
                                            {{ $record->id_snp }}
                                        </p>
                                    </td>

                                    <td class="px-6 py-5 align-top">
                                        <p class="text-sm font-semibold text-slate-800">
                                            {{ $record->nomor_surat }}
                                        </p>
                                        <p class="mt-1 text-sm text-slate-500">
                                            {{ $record->tanggal_surat ? \Carbon\Carbon::parse($record->tanggal_surat)->format('d/m/Y') : '-' }}
                                        </p>
                                        <p class="mt-2 text-sm font-medium uppercase text-slate-700">
                                            {{ $record->perihal_surat }}
                                        </p>
                                    </td>

                                    <td class="px-6 py-5 align-top text-sm text-slate-700">
                                        {{ $record->butir_snp_count }} butir
                                    </td>

                                    <td class="px-6 py-5 align-top">
                                        <span
                                            class="inline-flex text-center rounded-full px-3 py-1 text-xs font-bold text-white"
                                            style="background-color: #2377b9;">
                                            {{ ucwords(str_replace('_', ' ', $record->status)) }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-12 text-center text-sm text-slate-500">
                                        Belum ada data SNP.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-slate-100 px-6 py-4">
                    @include('layouts.partials.compact-pagination', ['paginator' => $records])
                </div>
            </div>
        </form>

        <div id="customReportModal"
            class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 px-4">

            <div class="max-h-[90vh] w-full max-w-4xl overflow-y-auto rounded-2xl bg-white shadow-2xl">
                <div class="flex items-start justify-between border-b border-slate-100 px-6 py-5">
                    <div>
                        <h2 class="text-xl font-bold text-slate-800">
                            Cetak Report Custom
                        </h2>
                        <p class="mt-1 text-sm text-slate-500">
                            Pilih butir SNP dan kolom yang ingin ditampilkan di report.
                        </p>
                    </div>

                    <button type="button" id="closeCustomReportModalBtn"
                        class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                        ✕
                    </button>
                </div>

                <form method="POST" id="customReportForm" target="_blank">
                    @csrf

                    <div id="customReportRecordIds"></div>

                    <div class="space-y-6 px-6 py-5">
                        <div>
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <h3 class="text-sm font-bold uppercase tracking-wide text-slate-600">
                                        Pilih Butir SNP
                                    </h3>
                                    <p class="mt-1 text-sm text-slate-500">
                                        Pilih butir dari surat SNP yang sudah dicentang.
                                    </p>
                                </div>

                                <button type="button" id="selectAllCustomButirBtn"
                                    class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50">
                                    Pilih Semua Butir
                                </button>
                            </div>

                            <div id="customReportButirList"
                                class="mt-4 grid max-h-72 gap-3 overflow-y-auto rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <p class="text-sm text-slate-400">
                                    Pilih surat SNP terlebih dahulu.
                                </p>
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <div class="mb-2 flex items-center justify-between gap-3">
                                    <label class="block text-sm font-semibold text-slate-700">
                                        Filter Tanggapan dari Unit Kerja
                                    </label>

                                    <button type="button" id="selectAllTanggapanUnitBtn"
                                        class="text-xs font-bold text-blue-600 hover:text-blue-700">
                                        Pilih Semua
                                    </button>
                                </div>

                                <div id="customReportTanggapanUnitList"
                                    class="grid max-h-48 gap-2 overflow-y-auto rounded-xl border border-slate-200 bg-slate-50 p-3">
                                    <p class="text-sm text-slate-400">
                                        Pilih butir SNP terlebih dahulu.
                                    </p>
                                </div>

                                <p class="mt-1 text-xs text-slate-500">
                                    Dipakai kalau memilih kolom Tanggapan Unit Kerja.
                                </p>
                            </div>

                            <div>
                                <div class="mb-2 flex items-center justify-between gap-3">
                                    <label class="block text-sm font-semibold text-slate-700">
                                        Filter Tindak Lanjut dari Unit Kerja
                                    </label>

                                    <button type="button" id="selectAllTindakLanjutUnitBtn"
                                        class="text-xs font-bold text-blue-600 hover:text-blue-700">
                                        Pilih Semua
                                    </button>
                                </div>

                                <div id="customReportTindakLanjutUnitList"
                                    class="grid max-h-48 gap-2 overflow-y-auto rounded-xl border border-slate-200 bg-slate-50 p-3">
                                    <p class="text-sm text-slate-400">
                                        Pilih butir SNP terlebih dahulu.
                                    </p>
                                </div>

                                <p class="mt-1 text-xs text-slate-500">
                                    Dipakai kalau memilih kolom Tindak Lanjut Unit Kerja.
                                </p>
                            </div>
                        </div>

                        <div>
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <h3 class="text-sm font-bold uppercase tracking-wide text-slate-600">
                                        Pilih Kolom Report
                                    </h3>
                                    <p class="mt-1 text-sm text-slate-500">
                                        Kolom yang dicentang akan muncul di PDF/Excel.
                                    </p>
                                </div>

                                <button type="button" id="selectAllCustomFieldsBtn"
                                    class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50">
                                    Pilih Semua Kolom
                                </button>
                            </div>

                            @php
                                $reportFields = [
                                    'surat' => 'Nomor, Tanggal & Perihal Surat',
                                    'id_butir' => 'ID Butir SNP',
                                    'isi_butir' => 'Isi Butir SNP',
                                    'pic_utama' => 'PIC Unit Kerja Utama',
                                    'pic_pendukung' => 'PIC Unit Kerja Pendukung',
                                    'tanggapan_unit' => 'Tanggapan Unit Kerja',
                                    'tindak_lanjut_unit' => 'Tindak Lanjut Unit Kerja',
                                    'kompilasi_tanggapan' => 'Kompilasi Tanggapan',
                                    'kompilasi_tindak_lanjut' => 'Kompilasi Tindak Lanjut',
                                    'deliverable' => 'Deliverable',
                                    'dokumen' => 'Dokumen Pendukung',
                                    'jatuh_tempo' => 'Tanggal Jatuh Tempo',
                                    'komite' => 'PIC Komite Dewan Pengawas',
                                    'hasil_reviu' => 'Hasil Reviu Dewan Pengawas',
                                    'status' => 'Status Tindak Lanjut',
                                ];
                            @endphp

                            <div class="mt-4 grid gap-3 md:grid-cols-2">
                                @foreach ($reportFields as $fieldKey => $fieldLabel)
                                    <label
                                        class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 hover:bg-blue-50">
                                        <input type="checkbox" name="fields[]" value="{{ $fieldKey }}" checked
                                            class="custom-field-checkbox rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                        <span>{{ $fieldLabel }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap justify-end gap-3 border-t border-slate-100 px-6 py-4">
                        <button type="button" id="cancelCustomReportModalBtn"
                            class="rounded-xl border border-slate-200 px-5 py-3 text-sm font-bold text-slate-600 hover:bg-slate-50">
                            Batal
                        </button>

                        <button type="submit" formaction="{{ route('snp.report.cetak-custom') }}" formmethod="POST"
                            formtarget="_blank"
                            class="rounded-xl px-5 py-3 text-sm font-bold text-white shadow-sm hover:opacity-90"
                            style="background-color: #2377b9;">
                            Cetak PDF Custom
                        </button>

                        <button type="submit" formaction="{{ route('snp.report.cetak-excel-custom') }}"
                            formmethod="POST"
                            class="rounded-xl px-5 py-3 text-sm font-bold text-white shadow-sm hover:opacity-90"
                            style="background-color: #6bb17e;">
                            Cetak Excel Custom
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div id="reportFormatModal"
            class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 px-4">

            <div class="w-full max-w-md rounded-2xl bg-white shadow-2xl">
                <div class="flex items-start justify-between border-b border-slate-100 px-6 py-5">
                    <div>
                        <h2 class="text-xl font-bold text-slate-800">
                            Pilih Format Report
                        </h2>
                        <p class="mt-1 text-sm text-slate-500">
                            Pilih format file yang ingin dicetak.
                        </p>
                    </div>

                    <button type="button" id="closeReportFormatModalBtn"
                        class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                        ✕
                    </button>
                </div>

                <div class="grid gap-3 px-6 py-5">
                    <button type="submit" form="reportForm" formaction="{{ route('snp.report.cetak') }}"
                        formmethod="POST" formtarget="_blank"
                        class="rounded-xl px-5 py-4 text-sm font-bold text-white shadow-sm hover:opacity-90"
                        style="background-color: #2377b9;">
                        Cetak PDF
                    </button>

                    <button type="submit" form="reportForm" formaction="{{ route('snp.report.cetak-excel') }}"
                        formmethod="POST"
                        class="rounded-xl px-5 py-4 text-sm font-bold text-white shadow-sm hover:opacity-90"
                        style="background-color: #6bb17e;">
                        Cetak Excel
                    </button>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
