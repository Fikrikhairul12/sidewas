<x-app-layout>
    <div x-data="{
        openModal: false,
        butirSearch: '',
        selectedButirId: '',
        selectedButir: null,
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

        <div class="rounded-2xl border border-blue-100 bg-white shadow-sm">
            <div class="border-b border-blue-50 px-6 py-5">
                <form method="GET" action="{{ route('snp.tindak-lanjut.index') }}">
                    <div class="grid gap-4 md:grid-cols-3">
                        <div class="md:col-span-2">
                            <label class="mb-2 block text-sm font-medium text-slate-700">
                                Kata Kunci
                            </label>
                            <input type="text" name="keyword" value="{{ request('keyword') }}"
                                placeholder="Cari ID SNP, ID butir, isi tindak lanjut, deliverables..."
                                class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>

                        <div class="flex items-end gap-3">
                            <a href="{{ route('snp.tindak-lanjut.index') }}"
                                class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                                Reset
                            </a>

                            <button type="submit"
                                class="rounded-xl px-4 py-2 text-sm font-semibold text-white hover:opacity-90"
                                style="background-color: #2377b9;">
                                Cari
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-blue-100 bg-white shadow-sm">
            <div class="border-b border-blue-50 px-6 py-5">
                <h2 class="text-lg font-bold text-slate-800">
                    Riwayat Tindak Lanjut
                </h2>

                <p class="mt-1 text-sm text-slate-500">
                    Data akan muncul setelah user memilih butir SNP dan membuat tindak lanjut.
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
                                Review
                            </th>
                            <th class="px-6 py-4 text-center text-xs font-bold uppercase tracking-wider text-slate-600">
                                Aksi
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-200 bg-white">
                        @forelse ($tindakLanjuts as $item)
                            @php
                                $butir = $item->butir;
                                $record = $butir?->record;
                                $reviewTerakhir = $item->reviews->sortByDesc('id')->first();
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
                                        Butir: {{ $butir?->id_butir_snp ?? '-' }}
                                    </p>

                                    <p
                                        class="mt-3 max-w-md text-xs font-medium uppercase leading-relaxed text-slate-800">
                                        {{ $butir?->butir_snp ?? '-' }}
                                    </p>
                                </td>

                                <td class="px-6 py-6 align-top">
                                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                                        Tindak Lanjut
                                    </p>

                                    <p class="mt-2 max-w-lg text-xs text-slate-800">
                                        {{ $item->tindak_lanjut }}
                                    </p>

                                    <p class="mt-4 text-xs font-bold uppercase tracking-wide text-slate-500">
                                        Deliverables
                                    </p>

                                    <p class="mt-2 max-w-lg text-xs text-slate-800">
                                        {{ $item->deliverables ?? '-' }}
                                    </p>

                                    <p class="mt-4 text-xs text-slate-500">
                                        Jatuh Tempo:
                                        <span class="font-bold text-slate-700">
                                            {{ $item->jth_tempo ? $item->jth_tempo->format('d/m/Y') : '-' }}
                                        </span>
                                    </p>

                                    <p class="mt-2 text-xs text-slate-500">
                                        Diinput oleh:
                                        <span class="font-bold text-slate-700">
                                            {{ $item->creator?->name ?? '-' }}
                                        </span>
                                    </p>
                                </td>

                                <td class="px-6 py-6 align-top">
                                    @if ($reviewTerakhir)
                                        <span class="inline-flex rounded-xl px-3 py-1 text-xs font-bold text-center text-white"
                                            style="background-color: #2377b9;">
                                            {{ ucwords(str_replace('_', ' ', $reviewTerakhir->status)) }}
                                        </span>

                                        <p class="mt-4 text-xs text-slate-500">
                                            Komite:
                                            <span class="font-bold">
                                                {{ $reviewTerakhir->komite?->kode_komite ?? '-' }}
                                            </span>
                                        </p>
                                    @else
                                        <span
                                            class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-500">
                                            Belum Ada Review
                                        </span>
                                    @endif
                                </td>

                                <td class="px-6 py-6 align-top">
                                    <div class="flex justify-center">
                                        <a href="#"
                                            class="rounded-lg px-4 py-2 text-xs font-bold text-white shadow-sm hover:opacity-90"
                                            style="background-color: #6bb17e;">
                                            Detail
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-12 text-center">
                                    <p class="text-sm font-semibold text-slate-600">
                                        Belum ada data tindak lanjut.
                                    </p>
                                    <p class="mt-1 text-xs text-slate-400">
                                        Klik Tambah Tindak Lanjut untuk memilih butir SNP yang sudah siap
                                        ditindaklanjuti.
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
                    <span class="font-semibold text-slate-700">{{ $tindakLanjuts->firstItem() ?? 0 }}</span>
                    -
                    <span class="font-semibold text-slate-700">{{ $tindakLanjuts->lastItem() ?? 0 }}</span>
                    dari
                    <span class="font-semibold text-slate-700">{{ $tindakLanjuts->total() }}</span>
                    entri
                </p>

                <div>
                    {{ $tindakLanjuts->links() }}
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

                            <input type="hidden" name="butir_id" x-model="selectedButirId">

                            <div class="rounded-xl border border-slate-300 bg-white">
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
                                        Butir SNP tidak ditemukan atau belum berstatus Dalam Proses Tindak Lanjut
                                        Direksi.
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

                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-700">
                                Tindak Lanjut
                            </label>

                            <textarea name="tindak_lanjut" rows="4" required
                                class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                placeholder="Masukkan tindak lanjut...">{{ old('tindak_lanjut') }}</textarea>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-700">
                                Deliverables
                            </label>

                            <textarea name="deliverables" rows="3" required
                                class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                placeholder="Masukkan deliverables...">{{ old('deliverables') }}</textarea>
                        </div>

                        <div class="grid gap-5 md:grid-cols-2">
                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-700">
                                    Dokumen Pendukung
                                </label>

                                <input type="file" name="dokumen"
                                    class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">

                                <p class="mt-1 text-xs text-slate-500">
                                    Opsional. Format PDF, Word, Excel, JPG, PNG. Maksimal 5 MB.
                                </p>
                            </div>

                            {{-- <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-700">
                                    Jatuh Tempo
                                </label>

                                <input type="date" name="jth_tempo" value="{{ old('jth_tempo') }}"
                                    class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div> --}}
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
