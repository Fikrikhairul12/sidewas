<x-app-layout>
    <div x-data="{ openModal: false, selectedReview: null }" class="space-y-6">
        <div class="rounded-2xl border border-blue-100 bg-white p-6 shadow-sm">
            <p class="text-sm font-semibold uppercase tracking-wide" style="color: #2377b9;">
                SNP Dewas
            </p>

            <h1 class="mt-2 text-3xl font-bold text-slate-800">
                Reviu Tanggapan & Tindak Lanjut SNP
            </h1>

            <p class="mt-2 text-sm text-slate-500">
                Halaman ini digunakan komite untuk mereviu tanggapan dan tindak lanjut SNP.
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

        {{-- FILTER --}}
        @include('layouts.snp.partials.filter-lanjutan', [
            'action' => route('snp.reviu.index'),
            'statusOptions' => [
                'belum_ditanggapi' => 'Belum Ditanggapi',
                'dalam_proses_reviu_dewas' => 'Dalam Proses Reviu Dewas',
                'dalam_proses_tindak_lanjut_direksi' => 'Dalam Proses Tindak Lanjut Direksi',
                'selesai_tuntas' => 'Selesai Tuntas',
            ],
            'keywordPlaceholder' =>
                'Cari ID SNP, ID butir, nomor surat, perihal, tanggapan, tindak lanjut, atau hasil reviu...',
        ])

        {{-- DAFTAR REVIU Tanggapan & Tindak Lanjut SNP --}}
        <div class="overflow-hidden rounded-2xl border border-blue-100 bg-white shadow-sm">
            <div class="border-b border-blue-50 px-6 py-5">
                <h2 class="text-lg font-bold text-slate-800">
                    Daftar Reviu Tanggapan & Tindak Lanjut SNP
                </h2>
                <p class="mt-1 text-sm text-slate-500">
                    Data yang ditampilkan adalah reviu tanggapan dan reviu tindak lanjut sesuai komite user.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-600">
                                Informasi Tanggapan & Tindak Lanjut SNP
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-600">
                                Tahap Reviu
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
                        @forelse ($reviews as $review)
                            @php
                                $record = $review->butir?->record;

                                $isTanggapan = $review->tahap_review === 'tanggapan';
                                $isTindakLanjut = $review->tahap_review === 'tindak_lanjut';

                                $kompilasi = $isTanggapan
                                    ? $review->kompilasiTanggapan
                                    : $review->kompilasiTindakLanjut;

                                $isiKompilasi = $kompilasi?->hasil_kompilasi;
                                $deliverablesKompilasi = $kompilasi?->deliverables;
                                $dokumenAda = $kompilasi?->dokumen;
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
                                        Butir: {{ $review->id_butir_snp }}
                                    </p>

                                    <p
                                        class="mt-3 max-w-md text-xs font-medium uppercase leading-relaxed text-slate-800">
                                        {{ $review->butir?->butir_snp ?? '-' }}
                                    </p>

                                    <p class="mt-3 text-xs text-slate-500">
                                        Komite: {{ $review->komite?->kode_komite ?? '-' }}
                                    </p>
                                </td>

                                <td class="px-6 py-6 align-top">
                                    <span class="inline-flex rounded-xl px-3 py-1 text-xs font-bold text-white"
                                        style="background-color: {{ $isTanggapan ? '#2377b9' : '#6bb17e' }};">
                                        {{ $isTanggapan ? 'Reviu Tanggapan' : 'Reviu Tindak Lanjut' }}
                                    </span>

                                    @if ($isTanggapan)
                                        <p class="mt-4 text-xs font-bold uppercase tracking-wide text-slate-500">
                                            Tanggapan
                                        </p>
                                        <p class="whitespace-pre-line mt-2 max-w-lg text-xs text-slate-800">
                                            {{ $isiKompilasi ?? '-' }}
                                        </p>

                                        <p class="mt-4 text-xs font-bold uppercase tracking-wide text-slate-500">
                                            Deliverables Tanggapan
                                        </p>
                                        <p class="mt-2 max-w-lg text-xs text-slate-800">
                                            {{ $deliverablesKompilasi ?? '-' }}
                                        </p>

                                        @if ($kompilasi?->ubah_tgl)
                                            <p class="mt-4 text-xs text-slate-500">
                                                Status Pengajuan Tanggal:
                                                <span class="font-bold">
                                                    {{ ucwords(str_replace('_', ' ', $kompilasi?->status_pengajuan_tgl ?? '-')) }}
                                                </span>
                                            </p>

                                            <p class="mt-2 text-xs text-slate-500">
                                                Tanggal Jatuh Tempo Diajukan:
                                                <span class="font-bold text-slate-700">
                                                    {{ \Carbon\Carbon::parse($kompilasi->ubah_tgl)->format('d/m/Y') }}
                                                </span>
                                            </p>
                                        @else
                                            <p class="mt-4 text-xs text-slate-500">
                                                Pengajuan Ubah Tanggal:
                                                <span class="font-bold text-slate-700">Tidak Ada</span>
                                            </p>
                                        @endif
                                    @else
                                        <p class="mt-4 text-xs font-bold uppercase tracking-wide text-slate-500">
                                            Tindak Lanjut
                                        </p>
                                        <p class="whitespace-pre-line mt-2 max-w-lg text-xs text-slate-800">
                                            {{ $isiKompilasi ?? '-' }}
                                        </p>

                                        <p class="mt-4 text-xs font-bold uppercase tracking-wide text-slate-500">
                                            Deliverables Tindak Lanjut
                                        </p>
                                        <p class="mt-2 max-w-lg text-xs text-slate-800">
                                            {{ $deliverablesKompilasi ?? '-' }}
                                        </p>

                                        <p class="mt-4 text-xs text-slate-500">
                                            Jatuh Tempo:
                                            <span class="font-bold">
                                                {{ $record?->jth_tempo ? \Carbon\Carbon::parse($record->jth_tempo)->format('d/m/Y') : '-' }}
                                            </span>
                                        </p>
                                    @endif

                                    <p class="mt-4 text-xs font-bold uppercase tracking-wide text-slate-500">
                                        Dokumen
                                    </p>

                                    @if ($dokumenAda || $review->dokumen_memo)
                                        <div class="mt-2 flex flex-wrap gap-2">
                                            @if ($dokumenAda)
                                                <a href="{{ route('snp.reviu.dokumen', $review->id) }}"
                                                    class="inline-flex rounded-lg px-3 py-2 text-xs font-bold text-white hover:opacity-90"
                                                    style="background-color: #2377b9;">
                                                    Download Dokumen
                                                </a>
                                            @endif

                                            @if ($review->dokumen_memo)
                                                <a href="{{ route('snp.reviu.dokumen-memo', $review->id) }}"
                                                    class="inline-flex rounded-lg px-3 py-2 text-xs font-bold text-white hover:opacity-90"
                                                    style="background-color: #2377b9;">
                                                    Download Memo Reviu
                                                </a>
                                            @endif
                                        </div>
                                    @else
                                        <p class="mt-2 text-xs text-slate-400">-</p>
                                    @endif
                                </td>

                                <td class="px-6 py-6 align-top">
                                    <span class="inline-flex rounded-xl px-3 py-1 text-xs font-bold text-white"
                                        style="background-color: #2377b9;">
                                        {{ ucwords(str_replace('_', ' ', $review->status)) }}
                                    </span>

                                    <p class="mt-4 text-xs font-bold uppercase tracking-wide text-slate-500">
                                        Hasil Reviu
                                    </p>
                                    <p class="whitespace-pre-line mt-2 max-w-lg text-xs text-slate-800">
                                        {{ $review->hasil_review ?? '-' }}
                                    </p>

                                    <p class="mt-4 text-xs font-bold uppercase tracking-wide text-slate-500">
                                        Deliverables Reviu
                                    </p>
                                    <p class="mt-2 max-w-lg text-xs text-slate-800">
                                        {{ $review->deliverables ?? '-' }}
                                    </p>
                                </td>

                                <td class="px-6 py-6 align-top">
                                    <div class="flex justify-center">
                                        @if (
                                            ($review->tahap_review === 'tanggapan' && $review->status === 'dalam_proses_tindak_lanjut_direksi') ||
                                                ($review->tahap_review === 'tindak_lanjut' && $review->status === 'selesai_tuntas'))
                                            <button type="button" disabled
                                                class="cursor-not-allowed rounded-lg bg-slate-200 px-4 py-2 text-xs font-bold text-slate-400">
                                                Sudah Direviu
                                            </button>
                                        @else
                                            <button type="button"
                                                @click="selectedReview = {
                                                    id: {{ $review->id }},
                                                    id_butir_snp: @js($review->id_butir_snp),
                                                    tahap_review: @js($review->tahap_review),
                                                    status: @js($review->status),
                                                    status_pengajuan_tgl: @js($kompilasi?->status_pengajuan_tgl ?? 'pending'),
                                                    hasil_review: @js($review->hasil_review ?? ''),
                                                    deliverables: @js($review->deliverables ?? '')
                                                }; openModal = true"
                                                class="rounded-lg px-4 py-2 text-xs font-bold text-white shadow-sm hover:opacity-90"
                                                style="background-color: #FFA500;">
                                                Detail / Reviu
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-12 text-center">
                                    <p class="text-sm font-semibold text-slate-600">
                                        Belum ada data reviu tanggapan.
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
                    <span class="font-semibold text-slate-700">{{ $reviews->firstItem() ?? 0 }}</span>
                    -
                    <span class="font-semibold text-slate-700">{{ $reviews->lastItem() ?? 0 }}</span>
                    dari
                    <span class="font-semibold text-slate-700">{{ $reviews->total() }}</span>
                    entri
                </p>

                <div>
                    {{ $reviews->links() }}
                </div>
            </div>
        </div>

        {{-- Modal Reviu --}}
        <div x-show="openModal" x-transition.opacity
            class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/60 px-4 py-8"
            style="display: none;">
            <div @click.outside="openModal = false" x-transition
                class="w-full max-w-3xl overflow-hidden rounded-2xl bg-white shadow-2xl">

                <div class="flex items-start justify-between border-b border-slate-100 px-6 py-5">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-wide" style="color: #2377b9;">
                            <span
                                x-text="selectedReview?.tahap_review === 'tindak_lanjut' ? 'Form Reviu Tindak Lanjut' : 'Form Reviu Tanggapan'"></span>
                        </p>

                        <h2 class="mt-1 text-2xl font-bold text-slate-800">
                            <span x-text="selectedReview?.id_butir_snp"></span>
                        </h2>

                        <p class="mt-1 text-sm text-slate-500">
                            Isi hasil reviu dan ubah status sesuai hasil pemeriksaan.
                        </p>
                    </div>

                    <button type="button" @click="openModal = false"
                        class="rounded-xl p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                        ✕
                    </button>
                </div>

                <form method="POST" :action="`/snp/reviu/${selectedReview?.id}`" enctype="multipart/form-data"
                    class="px-6 py-6">
                    @csrf
                    @method('PATCH')

                    <div class="grid gap-5">
                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-700">
                                Hasil Reviu
                            </label>
                            <textarea name="hasil_review" rows="4" required x-model="selectedReview.hasil_review"
                                class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                placeholder="Nomor surat reviu Dewas (B/XXXX/MMYYYY)&#10;Tanggal (DD-MM-YYYY)&#10;&#10;Isi hasil reviu..."></textarea>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-700">
                                Deliverables Reviu
                            </label>
                            <textarea name="deliverables" rows="3" x-model="selectedReview.deliverables"
                                class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                placeholder="Opsional. Masukkan deliverables hasil reviu..."></textarea>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-700">
                                Dokumen Reviu
                            </label>

                            <input type="file" name="dokumen"
                                class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">

                            <p class="mt-1 text-xs text-slate-500">
                                Opsional. Format PDF, Word, Excel, JPG, PNG. Maksimal 5 MB.
                            </p>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-700">
                                Dokumen Memo Reviu
                            </label>

                            <input type="file" name="dokumen_memo"
                                class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">

                            <p class="mt-1 text-xs text-slate-500">
                                Opsional. Format PDF, Word, Excel, JPG, PNG. Maksimal 5 MB.
                            </p>
                        </div>

                        <div class="grid gap-5 md:grid-cols-2">
                            <template x-if="selectedReview?.tahap_review === 'tanggapan'">
                                <div>
                                    <label class="mb-2 block text-sm font-semibold text-slate-700">
                                        Status Pengajuan Ubah Tanggal
                                    </label>
                                    <select name="status_pengajuan_tgl" x-model="selectedReview.status_pengajuan_tgl"
                                        required
                                        class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                        <option value="pending">Pending</option>
                                        <option value="disetujui">Disetujui</option>
                                        <option value="ditolak">Ditolak</option>
                                    </select>
                                </div>
                            </template>

                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-700">
                                    Status Reviu
                                </label>
                                <select name="status" x-model="selectedReview.status" required
                                    class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="belum_ditanggapi">Belum Ditanggapi</option>
                                    <option value="dalam_proses_reviu_dewas">Dalam Proses Reviu Dewan Pengawas
                                    </option>
                                    <option value="dalam_proses_tindak_lanjut_direksi">Dalam Proses Tindak Lanjut
                                        Direksi</option>
                                    <option value="selesai_tuntas">Selesai Tuntas</option>
                                </select>
                            </div>
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
                            Simpan Reviu
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
