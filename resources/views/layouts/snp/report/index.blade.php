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

        <form method="POST" action="{{ route('snp.report.cetak') }}" target="_blank">
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

                    <button type="submit"
                        class="rounded-xl px-5 py-3 text-sm font-bold text-white shadow-sm hover:opacity-90"
                        style="background-color: #2377b9;">
                        Cetak PDF
                    </button>
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
                                        <input type="checkbox" name="record_ids[]" value="{{ $record->id }}"
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
                                        <span class="inline-flex text-center rounded-full px-3 py-1 text-xs font-bold text-white"
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
                    {{ $records->links() }}
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
