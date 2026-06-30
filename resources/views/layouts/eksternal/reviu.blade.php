<x-app-layout>
    <div x-data="{
        openModal: false,
        openDetailModal: false,
        selectedReview: null,
        detailButir: null,
        selectedDetailTlId: null,
        detailSearch: '',

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
            <p class="text-sm font-semibold uppercase tracking-wide" style="color: #2377b9;">
                Rapat EKSTERNAL
            </p>

            <h1 class="mt-2 text-3xl font-bold text-slate-800">
                Reviu Tindak Lanjut Rapat EKSTERNAL
            </h1>

            <p class="mt-2 text-sm text-slate-500">
                Reviu dilakukan per butir Rapat EKSTERNAL. Data tetap tampil walaupun tindak lanjut dari PIC Unit belum lengkap.
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

        @include('layouts.eksternal.partials.filter-lanjutan', [
            'action' => route('eksternal.reviu.index'),
            'statusOptions' => $statusOptions,
            'keywordPlaceholder' =>
                'Cari ID Rapat Eksternal, ID butir, nomor surat, instansi, agenda, PIC unit, tindak lanjut, atau hasil reviu...',
        ])

        <div class="overflow-hidden rounded-2xl border border-blue-100 bg-white shadow-sm">
            <div class="border-b border-blue-50 px-6 py-5">
                <h2 class="text-lg font-bold text-slate-800">
                    Daftar Reviu Butir Rapat EKSTERNAL
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
                                Informasi Rapat EKSTERNAL
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

                                $authUser = auth()->user();
                                $canSubmitReview =
                                    $authUser?->isSuperAdmin() ||
                                    (($authUser?->hasRoleType('admin_eksternal') ||
                                        $authUser?->hasRoleType('moderator_eksternal')) &&
                                        (int) $record?->created_by === (int) $authUser?->id);
                                $canReview =
                                    $canSubmitReview &&
                                    $statusTl === 'diusulkan_tuntas' &&
                                    $review->status !== 'selesai_tuntas';
                                $disabledReviewLabel = $canSubmitReview ? 'Menunggu TL Lengkap' : 'Hanya Lihat';

                                $allowedDirektoratIds = ($butir?->butirDirektorats ?? collect())
                                    ->pluck('direktorat_id')
                                    ->map(fn($id) => (int) $id)
                                    ->toArray();

                                $tindakLanjutItems = ($butir?->tindakLanjuts ?? collect())
                                    ->sortBy([
                                        fn($tl) => $tl->unitKerja?->direktorat?->nama_direktorat ?? 'ZZZ',
                                        fn($tl) => $tl->unitKerja?->kode_unit ?? 'ZZZ',
                                        fn($tl) => $tl->id,
                                    ]);

                                $detailTindakLanjutPayload = [
                                    'id' => $butir?->id,
                                    'id_eksternal' => $record?->id_eksternal,
                                    'id_butir_eksternal' => $butir?->id_butir_eksternal,
                                    'agenda_eksternal' => $butir?->agenda_eksternal,
                                    'keputusan_eksternal' => $butir?->keputusan_eksternal,
                                    'tindak_lanjuts' => $tindakLanjutItems
                                        ->map(function ($tl) use ($allowedDirektoratIds, $butir) {
                                            $unitLabel =
                                                ($tl->unitKerja?->kode_unit ?? '-') .
                                                ' - ' .
                                                ($tl->unitKerja?->nama_unit ?? '-');

                                            $tlDirektoratId = $tl->unitKerja?->direktorat_id;
                                            $direktoratLabel =
                                                $tlDirektoratId &&
                                                in_array((int) $tlDirektoratId, $allowedDirektoratIds, true)
                                                    ? $tl->unitKerja?->direktorat?->nama_direktorat ?? '-'
                                                    : '-';

                                            return [
                                                'id' => $tl->id,
                                                'unit_label' => $unitLabel,
                                                'initial' => $tl->unitKerja?->kode_unit ?? '-',
                                                'jenis_pic' => 'PIC Unit',
                                                'id_butir_eksternal' => $butir?->id_butir_eksternal,
                                                'isi_butir_singkat' => \Illuminate\Support\Str::limit(
                                                    $butir?->keputusan_eksternal ?? $butir?->agenda_eksternal ?? '-',
                                                    80,
                                                ),
                                                'direktorat' => $direktoratLabel,
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
                                                'dokumen_url' => $tl->dokumen ? asset('storage/' . $tl->dokumen) : null,
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
                                        {{ $record?->id_eksternal ?? '-' }}
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

                                    <p class="mt-1 text-xs text-slate-700">
                                        Instansi: {{ $record?->nama_instansi_pengundang ?? '-' }}
                                    </p>

                                    <div class="mt-4 rounded-xl border border-slate-100 bg-white p-4">
                                        <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                                            Butir Rapat EKSTERNAL
                                        </p>

                                        <p class="mt-2 text-xs font-bold" style="color: #2377b9;">
                                            {{ $butir?->id_butir_eksternal ?? '-' }}
                                        </p>

                                        <p class="mt-3 text-xs text-slate-700">
                                            Tanggal EKSTERNAL:
                                            <span class="font-bold">
                                                {{ $butir?->tanggal_eksternal ? \Carbon\Carbon::parse($butir->tanggal_eksternal)->format('d/m/Y') : '-' }}
                                            </span>
                                        </p>

                                        <p class="mt-3 text-xs font-bold uppercase tracking-wide text-slate-500">
                                            Agenda
                                        </p>
                                        <p class="mt-1 whitespace-pre-line text-xs text-slate-800">
                                            {{ $butir?->agenda_eksternal ?? '-' }}
                                        </p>

                                        <p class="mt-3 text-xs font-bold uppercase tracking-wide text-slate-500">
                                            Keputusan Rapat
                                        </p>
                                        <p class="mt-1 whitespace-pre-line text-xs text-slate-800">
                                            {{ $butir?->keputusan_eksternal ?? '-' }}
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
                                    @if ($tindakLanjutItems->isNotEmpty())
                                        <div class="space-y-3">
                                            @foreach ($tindakLanjutItems->take(2) as $tl)
                                                <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                                                    <p class="text-xs font-bold uppercase tracking-wide"
                                                        style="color: #2377b9;">
                                                        {{ $tl->unitKerja?->kode_unit ?? '-' }}
                                                        -
                                                        {{ $tl->unitKerja?->nama_unit ?? '-' }}
                                                    </p>

                                                    <p class="mt-2 max-w-lg text-xs leading-relaxed text-slate-800">
                                                        {{ \Illuminate\Support\Str::limit($tl->tindak_lanjut ?? '-', 120) }}
                                                    </p>

                                                    <p class="mt-2 text-xs text-slate-500">
                                                        Deliverable:
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

                                            @if ($tindakLanjutItems->count() > 2)
                                                <p class="text-xs font-semibold text-slate-500">
                                                    + {{ $tindakLanjutItems->count() - 2 }} tindak lanjut lainnya
                                                </p>
                                            @endif
                                        </div>
                                    @else
                                        <p class="text-xs text-slate-400">Belum ada tindak lanjut.</p>
                                    @endif
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

                                            <a href="{{ route('eksternal.reviu.dokumen', $review->id) }}"
                                                class="mt-2 inline-flex rounded-lg px-3 py-2 text-xs font-bold text-white hover:opacity-90"
                                                style="background-color: #2377b9;">
                                                Download Dokumen Reviu
                                            </a>
                                        </div>
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

                                        @if ($review->status === 'selesai_tuntas')
                                            <button type="button" disabled
                                                class="cursor-not-allowed rounded-lg bg-slate-200 px-4 py-2 text-xs font-bold text-slate-400">
                                                Sudah Direviu
                                            </button>
                                        @elseif (!$canReview)
                                            <button type="button" disabled
                                                class="cursor-not-allowed rounded-lg bg-slate-200 px-4 py-2 text-xs font-bold text-slate-400">
                                                {{ $disabledReviewLabel }}
                                            </button>
                                        @else
                                            <button type="button"
                                                @click="selectedReview = {
                                                    id: {{ $review->id }},
                                                    id_butir_eksternal: @js($review->id_butir_eksternal),
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
                                        Belum ada butir Rapat EKSTERNAL yang memiliki tindak lanjut.
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

                @include('layouts.partials.compact-pagination', ['paginator' => $reviews])
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
                            Detail Tindak Lanjut EKSTERNAL
                        </h2>
                        <p class="mt-1 text-sm font-semibold text-slate-500" x-text="detailButir?.id_eksternal ?? '-'"></p>
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
                                            x-text="tindakLanjut.unit_label"></span>
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

                                <div class="grid gap-4 text-sm md:grid-cols-[160px_minmax(0,1fr)]">
                                    <p class="font-bold text-slate-600">Tipe PIC</p>
                                    <p class="text-slate-700" x-text="selectedDetailTl.jenis_pic"></p>

                                    <p class="font-bold text-slate-600">Butir EKSTERNAL</p>
                                    <p class="text-slate-700" x-text="selectedDetailTl.id_butir_eksternal"></p>

                                    <p class="font-bold text-slate-600">Isi Butir Singkat</p>
                                    <p class="text-slate-700" x-text="selectedDetailTl.isi_butir_singkat"></p>

                                    <p class="font-bold text-slate-600">Direktorat</p>
                                    <p class="text-slate-700" x-text="selectedDetailTl.direktorat"></p>

                                    <p class="font-bold text-slate-600">Tindak Lanjut</p>
                                    <p class="whitespace-pre-line leading-relaxed text-slate-700"
                                        x-text="selectedDetailTl.tindak_lanjut ?? '-'"></p>

                                    <p class="font-bold text-slate-600">Deliverable</p>
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

        {{-- Modal Reviu --}}
        <div x-show="openModal" x-transition.opacity
            class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/60 px-4 py-8"
            style="display: none;">
            <div @click.outside="openModal = false" x-transition
                class="w-full max-w-3xl overflow-hidden rounded-2xl bg-white shadow-2xl">

                <div class="flex items-start justify-between border-b border-slate-100 px-6 py-5">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-wide" style="color: #2377b9;">
                            Form Reviu Tindak Lanjut Butir Rapat EKSTERNAL
                        </p>

                        <h2 class="mt-1 text-2xl font-bold text-slate-800">
                            <span x-text="selectedReview?.id_butir_eksternal"></span>
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

                <form method="POST" :action="`/eksternal/reviu/${selectedReview?.id}`" enctype="multipart/form-data"
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
