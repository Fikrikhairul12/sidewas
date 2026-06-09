<x-app-layout>
    <div x-data="{ openModal: false, selectedReview: null }" class="space-y-6">
        <div class="rounded-2xl border border-blue-100 bg-white p-6 shadow-sm">
            <p class="text-sm font-semibold uppercase tracking-wide" style="color: #2377b9;">
                Keputusan RAGAB
            </p>

            <h1 class="mt-2 text-3xl font-bold text-slate-800">
                Reviu Tindak Lanjut Keputusan RAGAB
            </h1>

            <p class="mt-2 text-sm text-slate-500">
                Reviu dilakukan per butir Keputusan RAGAB. Data tetap tampil walaupun tindak lanjut dari PIC Unit belum lengkap.
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

        @include('layouts.ragab.partials.filter-lanjutan', [
            'action' => route('ragab.reviu.index'),
            'statusOptions' => $statusOptions,
            'keywordPlaceholder' =>
                'Cari ID Keputusan RAGAB, ID butir, nomor surat, agenda, keputusan, PIC unit, tindak lanjut, atau hasil reviu...',
        ])

        <div class="overflow-hidden rounded-2xl border border-blue-100 bg-white shadow-sm">
            <div class="border-b border-blue-50 px-6 py-5">
                <h2 class="text-lg font-bold text-slate-800">
                    Daftar Reviu Butir Keputusan RAGAB
                </h2>

                <p class="mt-1 text-sm text-slate-500">
                    Butir masuk ke halaman ini setelah memiliki minimal satu tindak lanjut.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-600">
                                Informasi Keputusan RAGAB
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-600">
                                Tindak Lanjut PIC Unit
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-600">
                                Status & Reviu Butir
                            </th>
                            <th class="px-6 py-4 text-center text-xs font-bold uppercase tracking-wider text-slate-600">
                                Aksi
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-200 bg-white">
                        @forelse ($reviews as $review)
                            @php
                                $butir = $review->butir;
                                $record = $butir?->record;

                                $picUnits = $butir?->butirPics?->where('jenis_pic', 'unit') ?? collect();

                                $statusTl = $butir?->statusTindakLanjut() ?? 'dalam_proses_tindak_lanjut';
                                $statusTlLabel = $butir?->statusTindakLanjutLabel() ?? 'Dalam Proses Tindak Lanjut';
                                $progressTlLabel = $butir?->progressTindakLanjutLabel() ?? '-';

                                $canReview = $statusTl === 'diusulkan_tuntas' && $review->status !== 'selesai_tuntas';

                                $tindakLanjutsByUnit = ($butir?->tindakLanjuts ?? collect())
                                    ->sortBy([
                                        fn($tl) => $tl->unitKerja?->direktorat?->nama_direktorat ?? 'ZZZ',
                                        fn($tl) => $tl->unitKerja?->kode_unit ?? 'ZZZ',
                                        fn($tl) => $tl->id,
                                    ])
                                    ->groupBy(
                                        fn($tl) => ($tl->unitKerja?->kode_unit ?? '-') .
                                            ' - ' .
                                            ($tl->unitKerja?->nama_unit ?? '-'),
                                    );
                            @endphp

                            <tr class="hover:bg-blue-50/40">
                                <td class="px-6 py-6 align-top">
                                    <p class="text-xs font-bold" style="color: #2377b9;">
                                        {{ $record?->id_ragab ?? '-' }}
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
                                            Butir Keputusan RAGAB
                                        </p>

                                        <p class="mt-2 text-xs font-bold" style="color: #2377b9;">
                                            {{ $butir?->id_butir_ragab ?? '-' }}
                                        </p>

                                        <p class="mt-3 text-xs text-slate-700">
                                            Tanggal RAGAB:
                                            <span class="font-bold">
                                                {{ $butir?->tanggal_ragab ? \Carbon\Carbon::parse($butir->tanggal_ragab)->format('d/m/Y') : '-' }}
                                            </span>
                                        </p>

                                        <p class="mt-3 text-xs font-bold uppercase tracking-wide text-slate-500">
                                            Agenda
                                        </p>
                                        <p class="mt-1 whitespace-pre-line text-xs text-slate-800">
                                            {{ $butir?->agenda_ragab ?? '-' }}
                                        </p>

                                        <p class="mt-3 text-xs font-bold uppercase tracking-wide text-slate-500">
                                            Keputusan
                                        </p>
                                        <p class="mt-1 whitespace-pre-line text-xs text-slate-800">
                                            {{ $butir?->keputusan_ragab ?? '-' }}
                                        </p>
                                    </div>

                                    <div class="mt-4">
                                        <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                                            PIC Unit Terkait
                                        </p>

                                        <div class="mt-2 flex flex-wrap gap-2">
                                            @forelse ($picUnits as $pic)
                                                @php
                                                    $hasTl =
                                                        ($butir?->tindakLanjuts ?? collect())
                                                            ->where('unit_kerja_id', $pic->unit_kerja_id)
                                                            ->count() > 0;
                                                @endphp

                                                <span
                                                    class="rounded-full px-3 py-1 text-xs font-bold {{ $hasTl ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500' }}">
                                                    {{ $pic->unitKerja?->kode_unit ?? '-' }}
                                                    {{ $hasTl ? '✓' : '•' }}
                                                </span>
                                            @empty
                                                <span class="text-xs text-slate-400">-</span>
                                            @endforelse
                                        </div>
                                    </div>
                                </td>

                                <td class="px-6 py-6 align-top">
                                    @forelse ($tindakLanjutsByUnit as $unitLabel => $tindakLanjuts)
                                        <div class="mb-4 rounded-xl border border-slate-200 bg-white p-4">
                                            <p class="text-xs font-bold uppercase tracking-wide"
                                                style="color: #2377b9;">
                                                {{ $unitLabel }}
                                            </p>

                                            @foreach ($tindakLanjuts as $tl)
                                                @php
                                                    $allowedDirektoratIds = ($butir?->butirDirektorats ?? collect())
                                                        ->pluck('direktorat_id')
                                                        ->map(fn($id) => (int) $id)
                                                        ->toArray();

                                                    $tlDirektoratId = $tl->unitKerja?->direktorat_id;

                                                    $direktoratLabel =
                                                        $tlDirektoratId &&
                                                        in_array((int) $tlDirektoratId, $allowedDirektoratIds, true)
                                                            ? $tl->unitKerja?->direktorat?->nama_direktorat ?? '-'
                                                            : '-';
                                                @endphp

                                                <div class="mt-3 rounded-xl bg-slate-50 p-4">
                                                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                                                        Direktorat
                                                    </p>
                                                    <p class="mt-1 text-xs text-slate-700">
                                                        {{ $direktoratLabel }}
                                                    </p>

                                                    <p
                                                        class="mt-3 text-xs font-bold uppercase tracking-wide text-slate-500">
                                                        Tindak Lanjut #{{ $loop->iteration }}
                                                    </p>
                                                    <p class="mt-2 whitespace-pre-line text-xs text-slate-800">
                                                        {{ $tl->tindak_lanjut ?? '-' }}
                                                    </p>

                                                    <p
                                                        class="mt-3 text-xs font-bold uppercase tracking-wide text-slate-500">
                                                        Deliverable
                                                    </p>
                                                    <p class="mt-2 whitespace-pre-line text-xs text-slate-800">
                                                        {{ $tl->deliverables ?? '-' }}
                                                    </p>

                                                    <div
                                                        class="mt-3 flex flex-wrap items-center gap-4 text-xs text-slate-500">
                                                        <p>
                                                            Jatuh Tempo:
                                                            <span class="font-bold text-slate-700">
                                                                {{ $tl->jth_tempo ? \Carbon\Carbon::parse($tl->jth_tempo)->format('d/m/Y') : '-' }}
                                                            </span>
                                                        </p>

                                                        <p>
                                                            Diinput oleh:
                                                            <span class="font-bold text-slate-700">
                                                                {{ $tl->creator?->name ?? '-' }}
                                                            </span>
                                                        </p>
                                                    </div>

                                                    <div class="mt-3">
                                                        <p
                                                            class="text-xs font-bold uppercase tracking-wide text-slate-500">
                                                            Dokumen Pendukung TL
                                                        </p>

                                                        @if ($tl->dokumen)
                                                            <a href="{{ asset('storage/' . $tl->dokumen) }}"
                                                                target="_blank"
                                                                class="mt-2 inline-flex rounded-lg px-3 py-2 text-xs font-bold text-white hover:opacity-90"
                                                                style="background-color: #2377b9;">
                                                                Download Dokumen
                                                            </a>
                                                        @else
                                                            <p class="mt-1 text-xs text-slate-400">-</p>
                                                        @endif
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @empty
                                        <p class="text-xs text-slate-400">Belum ada tindak lanjut.</p>
                                    @endforelse
                                </td>

                                <td class="px-6 py-6 align-top">
                                    <span class="inline-flex rounded-xl px-3 py-1 text-xs font-bold text-white"
                                        style="background-color: {{ $statusTl === 'diusulkan_tuntas' ? '#6bb17e' : '#64748b' }};">
                                        {{ $statusTlLabel }}
                                    </span>

                                    <p class="mt-3 text-xs text-slate-500">
                                        {{ $progressTlLabel }}
                                    </p>

                                    <div class="mt-5">
                                        <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                                            Status Reviu
                                        </p>

                                        <span class="mt-2 inline-flex rounded-xl px-3 py-1 text-xs font-bold text-white"
                                            style="background-color: #2377b9;">
                                            {{ ucwords(str_replace('_', ' ', $review->status)) }}
                                        </span>
                                    </div>

                                    <p class="mt-4 text-xs text-slate-500">
                                        Reviewer:
                                        <span class="font-bold text-slate-700">
                                            {{ $review->creator?->name ?? ($record?->creator?->name ?? '-') }}
                                        </span>
                                    </p>

                                    @if ($review->hasil_review)
                                        <p class="mt-4 text-xs font-bold uppercase tracking-wide text-slate-500">
                                            Hasil Reviu
                                        </p>
                                        <p class="mt-2 max-w-lg whitespace-pre-line text-xs text-slate-800">
                                            {{ $review->hasil_review }}
                                        </p>
                                    @endif

                                    @if ($review->deliverables)
                                        <p class="mt-4 text-xs font-bold uppercase tracking-wide text-slate-500">
                                            Deliverables Reviu
                                        </p>
                                        <p class="mt-2 max-w-lg whitespace-pre-line text-xs text-slate-800">
                                            {{ $review->deliverables }}
                                        </p>
                                    @endif

                                    @if ($review->dokumen)
                                        <div class="mt-4">
                                            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                                                Dokumen Reviu
                                            </p>

                                            <a href="{{ route('ragab.reviu.dokumen', $review->id) }}"
                                                class="mt-2 inline-flex rounded-lg px-3 py-2 text-xs font-bold text-white hover:opacity-90"
                                                style="background-color: #2377b9;">
                                                Download Dokumen Reviu
                                            </a>
                                        </div>
                                    @endif
                                </td>

                                <td class="px-6 py-6 align-top">
                                    <div class="flex justify-center">
                                        @if ($review->status === 'selesai_tuntas')
                                            <button type="button" disabled
                                                class="cursor-not-allowed rounded-lg bg-slate-200 px-4 py-2 text-xs font-bold text-slate-400">
                                                Sudah Direviu
                                            </button>
                                        @elseif (!$canReview)
                                            <button type="button" disabled
                                                class="cursor-not-allowed rounded-lg bg-slate-200 px-4 py-2 text-xs font-bold text-slate-400">
                                                Menunggu TL Lengkap
                                            </button>
                                        @else
                                            <button type="button"
                                                @click="selectedReview = {
                                                    id: {{ $review->id }},
                                                    id_butir_ragab: @js($review->id_butir_ragab),
                                                    status: @js($review->status),
                                                    hasil_review: @js($review->hasil_review ?? ''),
                                                    deliverables: @js($review->deliverables ?? '')
                                                }; openModal = true"
                                                class="rounded-lg px-4 py-2 text-xs font-bold text-white shadow-sm hover:opacity-90"
                                                style="background-color: #FFA500;">
                                                Reviu
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-12 text-center">
                                    <p class="text-sm font-semibold text-slate-600">
                                        Belum ada butir Keputusan RAGAB yang memiliki tindak lanjut.
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
                            Form Reviu Tindak Lanjut Butir Keputusan RAGAB
                        </p>

                        <h2 class="mt-1 text-2xl font-bold text-slate-800">
                            <span x-text="selectedReview?.id_butir_ragab"></span>
                        </h2>

                        <p class="mt-1 text-sm text-slate-500">
                            Reviu hanya dapat disimpan setelah semua PIC Unit menginput tindak lanjut.
                        </p>
                    </div>

                    <button type="button" @click="openModal = false"
                        class="rounded-xl p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                        ✕
                    </button>
                </div>

                <form method="POST" :action="`/ragab/reviu/${selectedReview?.id}`" enctype="multipart/form-data"
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
                                placeholder="Masukkan hasil reviu..."></textarea>
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
                                Status Reviu
                            </label>

                            <select name="status" x-model="selectedReview.status" required
                                class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="belum_ditanggapi">Belum Direviu</option>
                                <option value="dalam_proses_reviu_dewan_pengawas">
                                    Dalam Proses Reviu Dewan Pengawas
                                </option>
                                <option value="selesai_tuntas">Selesai Tuntas</option>
                            </select>
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
