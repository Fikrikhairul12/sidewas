<x-app-layout>
    <div data-workflow-page x-data="reviuBahanPage({ status: 'belum_ditanggapi', deliverables: '', status_pengajuan_tgl: 'pending' })" @keydown.escape.window="openModal = false" class="space-y-6">
        <div class="rounded-2xl border border-blue-100 bg-white p-6 shadow-sm">
            <p class="text-sm font-semibold text-blue-700">{{ $moduleLabel }}</p>
            <h1 class="mt-2 text-3xl font-bold text-slate-800">Reviu Tanggapan{{ \App\Services\IsianUnitTahapService::enabled() ? ' & Tindak Lanjut' : '' }} {{ strtoupper($module) }}</h1>
            <p class="mt-2 text-sm text-slate-500">Reviu dilakukan oleh komite berwenang atas kompilasi pada tahap dan putaran yang sedang diproses.</p>
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
                                $materials = $previewBahan[$review->id];
                                $revised = \App\Services\IsianUnitTahapService::enabled();
                                $compilation = $materials['compilation'] !== null ? $review->kompilasiPutaran : null;
                                $legacySource = $review->tahap_review === 'tindak_lanjut' ? $review->tindakLanjut : $review->tanggapan;
                                $number = $review->putaran?->nomor_putaran;
                                $roundLabel = $materials['round_label'] ?? ($number ? 'Putaran '.$number : 'Putaran awal');
                                $butirContent = $materials['butir_content'] ?? $review->butir?->{'butir_'.$module} ?? '';
                                $payload = [
                                    'preview' => $materials,
                                    'id' => $review->id, 'action' => route($module.'.reviu.update', $review),
                                    'butir_id' => $review->{'id_butir_'.$module}, 'butir' => $butirContent,
                                    'round_label' => $roundLabel, 'record_id' => $review->butir?->record?->id,
                                    'status' => $review->status, 'hasil_review' => $review->hasil_review ?? '', 'deliverables' => $review->deliverables ?? '',
                                    'compilation' => $materials['compilation']['content'] ?? '', 'compilation_deliverables' => $materials['compilation']['deliverables'] ?? '',
                                    'ubah_tgl' => $compilation?->ubah_tgl?->format('d/m/Y'),
                                    'status_pengajuan_tgl' => $compilation?->status_pengajuan_tgl ?? 'pending',
                                    'can_decide_date' => $compilation?->ubah_tgl && $compilation->status_pengajuan_tgl === 'pending' && $review->tahap_review === 'tindak_lanjut',
                                    'date_proposal_reason' => $compilation?->alasan_pengajuan_tgl ?? '',
                                    'jatuh_tempo_tl' => app(\App\Services\SuratTanggalService::class)->suggestedDate(),
                                    'suggested_date' => app(\App\Services\SuratTanggalService::class)->suggestedDate(),
                                    'alasan_jatuh_tempo' => '', 'alasan_keputusan_tgl' => '',
                                    'image_upload_url' => route($module.'.reviu.images.store', $review, false),
                                ];
                            @endphp
                            <tr>
                                <td class="px-6 py-5 align-top">
                                    <p class="font-bold text-blue-700">{{ $materials['letter_number'] ?? $review->butir?->record?->nomor_surat }}</p>
                                    <p class="mt-2 text-xs text-slate-600">{{ $review->{'id_butir_'.$module} }} · {{ $roundLabel }}</p>
                                    @if ($review->putaran?->archived_at)
                                        <p class="mt-2 text-xs font-semibold text-slate-500">Putaran lama (terkunci)</p>
                                    @endif
                                    <p class="mt-2 text-xs text-slate-600">Status Surat: {{ \App\Enums\SuratStatus::labelFor($review->butir?->record?->status) }}</p>
                                    @if ($review->butir?->record?->closed_at)
                                        <p class="mt-2 text-xs font-semibold text-blue-700">Close · {{ $review->butir->record->closed_at->format('d/m/Y H:i') }}. Bahan dan keputusan reviu terkunci.</p>
                                    @endif
                                    <p class="mt-2 text-xs text-slate-600">Komite: {{ $review->komite?->nama_komite ?? 'Belum ditetapkan' }}</p>
                                    <x-butir-preview class="mt-3" :content="$butirContent" :butir-id="$review->{'id_butir_'.$module}" />
                                    @if ($revised)<x-surat-tenggat :record="$review->butir->record" :stage="$review->tahapProses" />@endif
                                </td>
                                <td class="px-6 py-5 align-top text-sm">
                                    @if ($compilation)
                                        <p class="font-semibold text-slate-600">Hasil Kompilasi</p>
                                        @if ($revised)<p class="mt-2 text-xs text-blue-700">Salinan bahan saat kompilasi</p>@endif
                                        <x-rich-content :content="$materials['compilation']['content']" class="mt-3" />
                                        <p class="mt-3 whitespace-pre-line">Deliverables: {{ $materials['compilation']['deliverables'] ?? '-' }}</p>
                                        @if ($compilation->ubah_tgl)
                                            <p class="mt-3 text-xs text-slate-600">Pengajuan jatuh tempo: {{ $compilation->ubah_tgl->format('d/m/Y') }} · {{ $compilation->status_pengajuan_tgl }}</p>
                                        @endif
                                        @if (($revised ? data_get($compilation->salinan_konteks, 'kompilasi.dokumen.path') : $compilation->dokumen) && auth()->user()->{'canReview'.ucfirst($module).'ByKomite'}($review->komite_id))
                                            <a href="{{ route($module.'.reviu.dokumen', $review) }}" class="mt-3 inline-block text-blue-700 underline">Dokumen Kompilasi</a>
                                        @endif
                                    @elseif ($revised)
                                        <p class="font-semibold text-amber-700">Salinan bahan perlu rekonsiliasi</p>
                                        <p class="mt-2 text-xs text-slate-500">Riwayat tetap tersimpan. Reviu dinonaktifkan sampai hubungan tahap dan kompilasi dapat diverifikasi.</p>
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
                                    @if ($revised)
                                        @foreach ($review->riwayatTanggal->where('jenis', 'penetapan_tindak_lanjut') as $penetapan)
                                            <p class="mt-2 text-xs text-slate-600">Jatuh Tempo Tindak Lanjut Putaran Berikutnya: {{ $penetapan->tanggal_baru->format('d/m/Y') }} 23.59 WIB. {{ $penetapan->alasan }}</p>
                                        @endforeach
                                    @endif
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
                        <h2 class="text-xl font-bold text-slate-800">Reviu {{ \App\Services\IsianUnitTahapService::enabled() ? 'Tanggapan & Tindak Lanjut' : 'Tanggapan' }}</h2>
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
                    <template x-if="selected.ubah_tgl{{ \App\Services\IsianUnitTahapService::enabled() ? ' && selected.can_decide_date' : '' }}">
                        <div class="mt-5">
                            <label class="mb-2 block text-sm font-semibold" x-text="'Pengajuan Jatuh Tempo: ' + selected.ubah_tgl"></label>
                            <select name="status_pengajuan_tgl" x-model="selected.status_pengajuan_tgl" class="w-full rounded-xl border-slate-300">
                                <option value="pending">Pending</option><option value="disetujui">Disetujui</option><option value="ditolak">Ditolak</option>
                            </select>
                            @if (\App\Services\IsianUnitTahapService::enabled())
                                <p class="mt-2 text-xs text-slate-500" x-text="'Alasan pengajuan: ' + selected.date_proposal_reason"></p>
                                <label class="mt-3 block text-sm font-semibold">Alasan keputusan tanggal
                                    <textarea name="alasan_keputusan_tgl" x-model="selected.alasan_keputusan_tgl" :required="['disetujui', 'ditolak'].includes(selected.status_pengajuan_tgl)" maxlength="2000" rows="2" class="mt-2 w-full rounded-xl border-slate-300"></textarea>
                                </label>
                            @endif
                        </div>
                    </template>
                    <label for="status-reviu" class="mb-2 mt-5 block text-sm font-semibold">Status Reviu</label>
                    <select id="status-reviu" name="status" x-model="selected.status" required class="w-full rounded-xl border-slate-300">
                        @foreach ($statusOptions as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                    </select>
                    @if (\App\Services\IsianUnitTahapService::enabled())
                        <template x-if="selected.status === 'tindak_lanjut_belum_sesuai'">
                            <div class="mt-5 rounded-xl border border-blue-100 bg-blue-50 p-4" data-next-deadline>
                                <label class="block text-sm font-semibold">Jatuh Tempo Tindak Lanjut Putaran Berikutnya
                                    <input type="date" name="jatuh_tempo_tl" x-model="selected.jatuh_tempo_tl" required min="{{ now('Asia/Jakarta')->addDay()->toDateString() }}" class="mt-2 w-full rounded-xl border-slate-300">
                                </label>
                                <p class="mt-2 text-xs text-slate-600">Saran sistem: tanggal reviu +14 hari kalender. Batas berlaku hingga pukul 23.59 WIB.</p>
                                <label class="mt-3 block text-sm font-semibold">Alasan penetapan tanggal
                                    <textarea name="alasan_jatuh_tempo" x-model="selected.alasan_jatuh_tempo" :required="selected.jatuh_tempo_tl !== selected.suggested_date" rows="2" maxlength="2000" class="mt-2 w-full rounded-xl border-slate-300"></textarea>
                                </label>
                                <p class="mt-1 text-xs text-slate-600">Wajib diisi jika tanggal berbeda dari saran sistem.</p>
                            </div>
                        </template>
                    @endif
                    <div class="mt-6 flex justify-end gap-3">
                        <button type="button" @click="openModal = false" class="rounded-xl border px-4 py-2">Batal</button>
                        <button type="submit" class="rounded-xl bg-blue-700 px-4 py-2 font-semibold text-white">Simpan Reviu</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
