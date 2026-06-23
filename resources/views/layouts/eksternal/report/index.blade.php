<x-app-layout>
    <div class="space-y-6">
        <div class="rounded-2xl border border-blue-100 bg-white p-6 shadow-sm">
            <p class="text-sm font-semibold uppercase tracking-wide" style="color: #2377b9;">
                Keputusan EKSTERNAL
            </p>

            <h1 class="mt-2 text-3xl font-bold text-slate-800">
                Cetak Report Keputusan EKSTERNAL
            </h1>

            <p class="mt-2 text-sm text-slate-500">
                Pilih satu atau lebih surat Keputusan EKSTERNAL untuk dicetak ke PDF atau Excel.
            </p>
        </div>

        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                {{ $errors->first() }}
            </div>
        @endif

        @if (session('error'))
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                {{ session('error') }}
            </div>
        @endif

        <div class="rounded-2xl border border-blue-100 bg-white shadow-sm">
            <div class="border-b border-blue-50 px-6 py-5">
                <form method="GET" action="{{ route('eksternal.report.index') }}">
                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-slate-700">
                                Direktorat
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
                                Unit PIC
                            </label>
                            <select name="unit_kerja_id"
                                class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Semua Unit PIC</option>
                                @foreach ($unitKerjas as $unit)
                                    <option value="{{ $unit->id }}" @selected(request('unit_kerja_id') == $unit->id)>
                                        {{ $unit->kode_unit }} - {{ $unit->nama_unit }}
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
                                @foreach ($statusOptions as $value => $label)
                                    <option value="{{ $value }}" @selected(request('status') === $value)>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-slate-700">
                                Kata Kunci
                            </label>
                            <input type="text" name="keyword" value="{{ request('keyword') }}"
                                placeholder="Cari nomor surat, instansi, perihal, agenda, keputusan, atau TL..."
                                class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
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
                    </div>

                    <div class="mt-5 flex justify-end gap-3">
                        <a href="{{ route('eksternal.report.index') }}"
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

        <form id="reportForm" method="POST">
            @csrf

            <div class="overflow-hidden rounded-2xl border border-blue-100 bg-white shadow-sm">
                <div
                    class="flex flex-col gap-4 border-b border-blue-50 px-6 py-5 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-slate-800">
                            Daftar Surat Keputusan EKSTERNAL
                        </h2>
                        <p class="mt-1 text-sm text-slate-500">
                            Centang surat yang ingin dicetak.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <button id="openReportFormatModalBtn" type="button"
                            class="rounded-xl px-4 py-2 text-sm font-bold text-white hover:opacity-90"
                            style="background-color: #2377b9;">
                            Cetak Report
                        </button>

                        <button id="openCustomReportModalBtn" type="button"
                            class="rounded-xl px-4 py-2 text-sm font-bold text-white hover:opacity-90"
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
                                    <label class="inline-flex cursor-pointer items-center justify-center gap-2">
                                        <input type="checkbox" id="selectAllReportRecords"
                                            class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                        <span>Pilih Semua</span>
                                    </label>
                                </th>
                                <th
                                    class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-600">
                                    Surat
                                </th>
                                <th
                                    class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-600">
                                    Butir Keputusan EKSTERNAL
                                </th>
                                <th
                                    class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-600">
                                    Unit PIC
                                </th>
                                <th
                                    class="px-6 py-4 text-center text-xs font-bold uppercase tracking-wider text-slate-600">
                                    Status
                                </th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-200 bg-white">
                            @forelse ($records as $record)
                                @php
                                    $butirsForReport = $record->butirEksternal
                                        ->map(function ($butir) {
                                            return [
                                                'id' => $butir->id,
                                                'id_butir_eksternal' => $butir->id_butir_eksternal,
                                                'butir_eksternal' => trim(
                                                    ($butir->tanggal_eksternal
                                                        ? \Carbon\Carbon::parse($butir->tanggal_eksternal)->format('d/m/Y')
                                                        : '-') .
                                                        ' | ' .
                                                        ($butir->agenda_eksternal ?? '-') .
                                                        ' | ' .
                                                        \Illuminate\Support\Str::limit(
                                                            $butir->keputusan_eksternal ?? '-',
                                                            120,
                                                        ),
                                                ),
                                            ];
                                        })
                                        ->values();

                                    $unitPics = $record->butirEksternal
                                        ->flatMap(fn($butir) => $butir->butirPics->where('jenis_pic', 'unit'))
                                        ->map(fn($pic) => $pic->unitKerja?->kode_unit)
                                        ->filter()
                                        ->unique()
                                        ->values();
                                @endphp

                                <tr class="hover:bg-blue-50/40">
                                    <td class="px-6 py-6 text-center align-top">
                                        <input type="checkbox" name="record_ids[]" value="{{ $record->id }}"
                                            data-record-label="{{ $record->nomor_surat ?? $record->id_eksternal }}"
                                            data-butirs='@json($butirsForReport)'
                                            class="record-report-checkbox rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                    </td>

                                    <td class="px-6 py-6 align-top">
                                        <p class="text-sm font-bold text-slate-800">
                                            {{ $record->nomor_surat ?? '-' }}
                                        </p>
                                        <p class="mt-1 text-xs text-slate-500">
                                            {{ $record->tanggal_surat ? \Carbon\Carbon::parse($record->tanggal_surat)->format('d/m/Y') : '-' }}
                                        </p>
                                        <p class="mt-2 text-xs font-semibold text-slate-600">
                                            Instansi: {{ $record->nama_instansi_pengundang ?? '-' }}
                                        </p>
                                        <p class="mt-2 max-w-md whitespace-pre-line text-xs text-slate-700">
                                            {{ $record->perihal_surat ?? '-' }}
                                        </p>
                                    </td>

                                    <td class="px-6 py-6 align-top">
                                        <p class="text-sm font-bold text-slate-800">
                                            {{ $record->butir_eksternal_count }} butir
                                        </p>

                                        @foreach ($record->butirEksternal->take(2) as $butir)
                                            <p class="mt-2 text-xs text-slate-500">
                                                {{ $butir->tanggal_eksternal ? \Carbon\Carbon::parse($butir->tanggal_eksternal)->format('d/m/Y') : '-' }}
                                                —
                                                {{ \Illuminate\Support\Str::limit($butir->agenda_eksternal ?? '-', 80) }}
                                            </p>
                                        @endforeach
                                    </td>

                                    <td class="px-6 py-6 align-top">
                                        <div class="flex max-w-sm flex-wrap gap-2">
                                            @forelse ($unitPics as $index => $unitPic)
                                                @php
                                                    $colors = [
                                                        'bg-sidewas-blue text-white',
                                                        'bg-green-600 text-white',
                                                        'bg-yellow-500 text-white',
                                                        'bg-[#FFA500] text-white',
                                                        'bg-pink-600 text-white',
                                                        'bg-slate-700 text-white',
                                                    ];

                                                    $colorClass = $colors[$index % count($colors)];
                                                @endphp

                                                <span
                                                    class="inline-flex rounded-full px-3 py-1 text-xs font-bold {{ $colorClass }}">
                                                    {{ $unitPic }}
                                                </span>
                                            @empty
                                                <span class="text-xs text-slate-400">-</span>
                                            @endforelse
                                        </div>
                                    </td>

                                    <td class="px-6 py-6 text-center align-top">
                                        <span class="inline-flex rounded-full px-3 py-1 text-xs font-bold text-white"
                                            style="background-color: #2377b9;">
                                            {{ ucwords(str_replace('_', ' ', $record->status)) }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-12 text-center">
                                        <p class="text-sm font-semibold text-slate-600">
                                            Belum ada data EKSTERNAL.
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
                        <span class="font-semibold text-slate-700">{{ $records->firstItem() ?? 0 }}</span>
                        -
                        <span class="font-semibold text-slate-700">{{ $records->lastItem() ?? 0 }}</span>
                        dari
                        <span class="font-semibold text-slate-700">{{ $records->total() }}</span>
                        entri
                    </p>

                    <div>
                        @include('layouts.partials.compact-pagination', ['paginator' => $records])
                    </div>
                </div>
            </div>
        </form>

        {{-- Modal Pilih Format --}}
        <div id="reportFormatModal"
            class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 px-4">
            <div class="w-full max-w-xl rounded-2xl bg-white p-6 shadow-2xl">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-wide" style="color: #2377b9;">
                            Pilih Format
                        </p>
                        <h2 class="mt-1 text-2xl font-bold text-slate-800">
                            Cetak Report EKSTERNAL
                        </h2>
                    </div>

                    <button id="closeReportFormatModalBtn" type="button"
                        class="rounded-xl p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                        ✕
                    </button>
                </div>

                <div class="mt-6 grid gap-4 md:grid-cols-2">
                    <button type="submit" form="reportForm" formaction="{{ route('eksternal.report.cetak') }}"
                        formmethod="POST" formtarget="_blank"
                        class="rounded-xl px-5 py-4 text-sm font-bold text-white shadow-sm hover:opacity-90"
                        style="background-color: #2377b9;">
                        Cetak PDF
                    </button>

                    <button type="submit" form="reportForm" formaction="{{ route('eksternal.report.cetak-excel') }}"
                        formmethod="POST"
                        class="rounded-xl px-5 py-4 text-sm font-bold text-white shadow-sm hover:opacity-90"
                        style="background-color: #6bb17e;">
                        Cetak Excel
                    </button>
                </div>
            </div>
        </div>

        {{-- Modal Report Custom --}}
        <div id="customReportModal"
            class="fixed inset-0 z-50 hidden items-start justify-center overflow-y-auto bg-slate-900/60 px-4 py-8">
            <div class="w-full max-w-5xl rounded-2xl bg-white shadow-2xl">
                <div class="flex items-start justify-between border-b border-slate-100 px-6 py-5">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-wide" style="color: #2377b9;">
                            Report Custom
                        </p>
                        <h2 class="mt-1 text-2xl font-bold text-slate-800">
                            Pilih Butir dan Kolom
                        </h2>
                    </div>

                    <button id="closeCustomReportModalBtn" type="button"
                        class="rounded-xl p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                        ✕
                    </button>
                </div>

                <form id="customReportForm" method="POST" class="px-6 py-6">
                    @csrf

                    <div id="customReportRecordIds"></div>

                    <div class="grid gap-6 lg:grid-cols-2">
                        <div>
                            <div class="mb-3 flex items-center justify-between gap-3">
                                <h3 class="text-sm font-bold text-slate-800">
                                    Pilih Butir
                                </h3>

                                <button id="selectAllCustomButirBtn" type="button"
                                    class="text-xs font-bold text-blue-600 hover:text-blue-700">
                                    Hapus Pilihan Butir
                                </button>
                            </div>

                            <div id="customReportButirList"
                                class="max-h-96 space-y-3 overflow-y-auto rounded-xl bg-slate-50 p-3">
                            </div>
                        </div>

                        <div>
                            <div class="mb-3 flex items-center justify-between gap-3">
                                <h3 class="text-sm font-bold text-slate-800">
                                    Pilih Kolom
                                </h3>

                                <button id="selectAllCustomFieldsBtn" type="button"
                                    class="text-xs font-bold text-blue-600 hover:text-blue-700">
                                    Hapus Pilihan Kolom
                                </button>
                            </div>

                            <div class="grid gap-2 rounded-xl bg-slate-50 p-3">
                                @foreach ($reportFields as $field => $label)
                                    <label
                                        class="flex cursor-pointer items-start gap-3 rounded-lg bg-white px-3 py-2 text-sm hover:bg-blue-50">
                                        <input type="checkbox" name="fields[]" value="{{ $field }}" checked
                                            class="custom-field-checkbox mt-1 rounded border-slate-300 text-blue-600 focus:ring-blue-500">

                                        <span class="font-medium text-slate-700">
                                            {{ $label }}
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="mt-8 flex justify-end gap-3 border-t border-slate-100 pt-5">
                        <button id="cancelCustomReportModalBtn" type="button"
                            class="rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                            Batal
                        </button>

                        <button type="submit" formaction="{{ route('eksternal.report.cetak-custom') }}"
                            formmethod="POST" formtarget="_blank"
                            class="rounded-xl px-5 py-3 text-sm font-bold text-white shadow-sm hover:opacity-90"
                            style="background-color: #2377b9;">
                            Cetak PDF Custom
                        </button>

                        <button type="submit" formaction="{{ route('eksternal.report.cetak-excel-custom') }}"
                            formmethod="POST"
                            class="rounded-xl px-5 py-3 text-sm font-bold text-white shadow-sm hover:opacity-90"
                            style="background-color: #6bb17e;">
                            Cetak Excel Custom
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
