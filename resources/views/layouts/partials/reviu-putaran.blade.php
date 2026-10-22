<x-app-layout>
    <div data-workflow-page x-data="reviuBahanPage({ status: 'belum_ditanggapi', deliverables: '', status_pengajuan_tgl: 'pending' })" @keydown.escape.window="openModal = false" class="space-y-6">
        <div class="rounded-2xl border border-blue-100 bg-white p-6 shadow-sm">
            <p class="text-sm font-semibold text-blue-700">{{ $moduleLabel }}</p>
            <h1 class="mt-2 text-3xl font-bold text-slate-800">Reviu Tanggapan {{ strtoupper($module) }}</h1>
            <p class="mt-2 text-sm text-slate-500">Reviu dilakukan oleh komite berwenang atas kompilasi tanggapan pada putaran yang sedang diproses.</p>
        </div>
        @if (session('success'))
            <p role="status" class="rounded-xl bg-green-50 p-4 text-green-700">{{ session('success') }}</p>
        @endif
        @if ($errors->any())
            <div role="alert" class="rounded-xl bg-red-50 p-4 text-red-700">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif
        @include('layouts.'.$module.'.partials.filter-lanjutan', [
            'action' => route($module.'.reviu.index'), 'statusOptions' => $statusOptions,
            'keywordPlaceholder' => 'Cari nomor surat, ID butir, kompilasi, atau hasil reviu...',
        ])
        <div class="overflow-hidden rounded-2xl border border-blue-100 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50"><tr>
                        @foreach (['Informasi Surat', 'Bahan Reviu', 'Hasil Reviu', 'Aksi'] as $heading)
                            <th class="px-6 py-4 text-left text-xs font-bold uppercase text-slate-600">{{ $heading }}</th>
                        @endforeach
                    </tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($reviews as $review)
                            @php
                                $compilation = $previewBahan[$review->id]['compilation'] !== null ? $review->kompilasiPutaran : null;
                                $legacySource = $review->tahap_review === 'tindak_lanjut' ? $review->tindakLanjut : $review->tanggapan;
                                $number = $review->putaran?->nomor_putaran;
                                $roundLabel = $number ? 'Putaran '.$number : 'Putaran awal';
                                $payload = [
                                    'preview' => $previewBahan[$review->id],
                                    'id' => $review->id, 'action' => route($module.'.reviu.update', $review),
                                    'butir_id' => $review->{'id_butir_'.$module}, 'butir' => $review->butir?->{'butir_'.$module} ?? '',
                                    'round_label' => $roundLabel, 'record_id' => $review->butir?->record?->id,
                                    'status' => $review->status, 'hasil_review' => $review->hasil_review ?? '', 'deliverables' => $review->deliverables ?? '',
                                    'compilation' => $compilation?->hasil_kompilasi ?? '', 'compilation_deliverables' => $compilation?->deliverables ?? '',
                                    'ubah_tgl' => $compilation?->ubah_tgl?->format('d/m/Y'),
                                    'status_pengajuan_tgl' => $compilation?->status_pengajuan_tgl ?? 'pending',
                                    'image_upload_url' => route($module.'.reviu.images.store', $review, false),
                                ];
                            @endphp
                            <tr>
                                <td class="px-6 py-5 align-top">
                                    <p class="font-bold text-blue-700">{{ $review->butir?->record?->nomor_surat }}</p>
                                    <p class="mt-2 text-xs text-slate-600">{{ $review->{'id_butir_'.$module} }} · {{ $roundLabel }}</p>
                                    @if ($review->putaran?->archived_at)
                                        <p class="mt-2 text-xs font-semibold text-slate-500">Putaran lama (terkunci)</p>
                                    @endif
                                    <p class="mt-2 text-xs text-slate-600">Status Surat: {{ \App\Enums\SuratStatus::labelFor($review->butir?->record?->status) }}</p>
                                    @if ($review->butir?->record?->closed_at)
                                        <p class="mt-2 text-xs font-semibold text-blue-700">Close · {{ $review->butir->record->closed_at->format('d/m/Y H:i') }}. Bahan dan keputusan reviu terkunci.</p>
                                    @endif
                                    <p class="mt-2 text-xs text-slate-600">Komite: {{ $review->komite?->nama_komite ?? 'Belum ditetapkan' }}</p>
                                    <x-butir-preview class="mt-3" :content="$review->butir?->{'butir_'.$module} ?? ''" :butir-id="$review->{'id_butir_'.$module}" />
                                </td>
                                <td class="px-6 py-5 align-top text-sm">
                                    @if ($compilation)
                                        <p class="font-semibold text-slate-600">Hasil Kompilasi</p>
                                        <x-rich-content :content="$compilation->hasil_kompilasi" class="mt-3" />
                                        <p class="mt-3 whitespace-pre-line">Deliverables: {{ $compilation->deliverables ?? '-' }}</p>
                                        @if ($compilation->ubah_tgl)
                                            <p class="mt-3 text-xs text-slate-600">Pengajuan jatuh tempo: {{ $compilation->ubah_tgl->format('d/m/Y') }} · {{ $compilation->status_pengajuan_tgl }}</p>
                                        @endif
                                        @if ($compilation->dokumen && auth()->user()->{'canReview'.ucfirst($module).'ByKomite'}($review->komite_id))
                                            <a href="{{ route($module.'.reviu.dokumen', $review) }}" class="mt-3 inline-block text-blue-700 underline">Dokumen Kompilasi</a>
                                        @endif
                                    @else
                                        <p class="font-semibold text-slate-500">Riwayat alur lama · {{ $review->tahap_review === 'tindak_lanjut' ? 'Tindak Lanjut' : 'Tanggapan' }}</p>
                                        <p class="mt-3 whitespace-pre-line">{{ $review->tahap_review === 'tindak_lanjut' ? $review->tindakLanjut?->tindak_lanjut : $review->tanggapan?->tanggapan }}</p>
                                        <p class="mt-3 whitespace-pre-line">Deliverables: {{ $legacySource?->deliverables ?? '-' }}</p>
                                        @if ($legacySource?->dokumen && auth()->user()->{'canReview'.ucfirst($module).'ByKomite'}($review->komite_id))
                                            <a href="{{ route($module.'.reviu.dokumen', $review) }}" class="mt-3 inline-block text-blue-700 underline">Dokumen Bahan Reviu Lama</a>
                                        @endif
                                    @endif
                                </td>
                                <td class="px-6 py-5 align-top text-sm">
                                    <p class="font-semibold text-blue-700">{{ \App\Enums\ReviuStatus::labelFor($review->status) }}</p>
                                    <x-rich-content :content="$review->hasil_review" class="mt-3" />
                                    <p class="mt-3 whitespace-pre-line">Deliverables: {{ $review->deliverables ?? '-' }}</p>
                                    <p class="mt-3 text-xs text-slate-500">Disimpan oleh: {{ $review->updater?->name ?? $review->creator?->name ?? '-' }} · {{ $review->updated_at?->format('d/m/Y H:i') ?? '-' }}</p>
                                    @if ($review->dokumen && auth()->user()->{'canReview'.ucfirst($module).'ByKomite'}($review->komite_id))
                                        <a href="{{ route($module.'.reviu.hasil-dokumen', $review) }}" class="mt-3 inline-block text-blue-700 underline">Dokumen Hasil Reviu</a>
                                    @endif
                                    @if ($module === 'snp' && $review->dokumen_memo && auth()->user()->canReviewSnpByKomite($review->komite_id))
                                        <a href="{{ route('snp.reviu.dokumen-memo', $review) }}" class="mt-3 inline-block text-blue-700 underline">Dokumen Memo Reviu</a>
                                    @endif
                                </td>
                                <td class="px-6 py-5 align-top">
                                    @if ($workflow->canReview(auth()->user(), $review))
                                        <button type="button" data-reviu-putaran @click="openMaterialModal(@js($payload))" class="rounded-xl bg-amber-500 px-4 py-2 text-sm font-bold text-white">Detail / Reviu</button>
                                    @else
                                        <span class="text-xs text-slate-500">Hanya dapat dibaca</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-6 py-10 text-center text-slate-500">Belum ada kompilasi yang dikirim ke reviu.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t p-4">@include('layouts.partials.compact-pagination', ['paginator' => $reviews])</div>
        </div>
        <div x-show="openModal" x-transition.opacity style="display: none;" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/60 px-4 py-8">
            <div class="w-full max-w-3xl rounded-2xl bg-white p-6 shadow-2xl" role="dialog" aria-modal="true" aria-label="Reviu tanggapan">
                <div class="mb-5 flex justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-bold text-slate-800">Reviu Tanggapan</h2>
                        <p data-workflow-context class="mt-1 text-sm text-slate-500" x-text="selected.butir_id + ' · ' + selected.round_label"></p>
                    </div>
                    <button type="button" @click="openModal = false" aria-label="Tutup modal" class="text-2xl text-slate-500">&times;</button>
                </div>
                @include('layouts.partials.reviu-bahan-preview', ['showCompilation' => true])
                <form method="POST" :action="selected.action" enctype="multipart/form-data" data-reviu-form>
                    @csrf
                    @method('PATCH')
                    <x-butir-preview class="mb-5" content-expression="selected.butir" id-expression="selected.butir_id" />
                    <label class="mb-2 block text-sm font-semibold">Hasil Reviu</label>
                    <x-snp-butir-editor name="hasil_review" label="Hasil Reviu" :module="$module"
                        content-expression="selected.hasil_review" record-expression="selected.record_id"
                        key-expression="openModal ? selected.image_upload_url : 'closed'" upload-url-expression="selected.image_upload_url" />
                    <label class="mb-2 mt-5 block text-sm font-semibold">Deliverables Reviu</label>
                    <textarea name="deliverables" rows="3" x-model="selected.deliverables" class="w-full rounded-xl border-slate-300"></textarea>
                    <label class="mb-2 mt-5 block text-sm font-semibold">Dokumen Reviu</label>
                    <input type="file" name="dokumen" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" class="w-full rounded-xl border p-3">
                    @if ($module === 'snp')
                        <label class="mb-2 mt-5 block text-sm font-semibold">Dokumen Memo Reviu</label>
                        <input type="file" name="dokumen_memo" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" class="w-full rounded-xl border p-3">
                    @endif
                    <p class="mt-1 text-xs text-slate-500">PDF, Word, Excel, JPG, PNG. Maksimal 5 MB per berkas.</p>
                    <template x-if="selected.ubah_tgl">
                        <div class="mt-5">
                            <label class="mb-2 block text-sm font-semibold" x-text="'Pengajuan Jatuh Tempo: ' + selected.ubah_tgl"></label>
                            <select name="status_pengajuan_tgl" x-model="selected.status_pengajuan_tgl" class="w-full rounded-xl border-slate-300">
                                <option value="pending">Pending</option><option value="disetujui">Disetujui</option><option value="ditolak">Ditolak</option>
                            </select>
                        </div>
                    </template>
                    <label for="status-reviu" class="mb-2 mt-5 block text-sm font-semibold">Status Reviu</label>
                    <select id="status-reviu" name="status" x-model="selected.status" required class="w-full rounded-xl border-slate-300">
                        @foreach ($statusOptions as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                    </select>
                    <div class="mt-6 flex justify-end gap-3">
                        <button type="button" @click="openModal = false" class="rounded-xl border px-4 py-2">Batal</button>
                        <button type="submit" class="rounded-xl bg-blue-700 px-4 py-2 font-semibold text-white">Simpan Reviu</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
