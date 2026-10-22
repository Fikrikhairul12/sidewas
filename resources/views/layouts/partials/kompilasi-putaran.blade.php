<x-app-layout>
    <div data-workflow-page x-data="reviuBahanPage({ deliverables: '', ubah_tgl: '' })" @keydown.escape.window="openModal = false" class="space-y-6">
        <div class="rounded-2xl border border-blue-100 bg-white p-6 shadow-sm">
            <p class="text-sm font-semibold text-blue-700">{{ $moduleLabel }}</p>
            <h1 class="mt-2 text-3xl font-bold text-slate-800">Kompilasi Tanggapan {{ strtoupper($module) }}</h1>
            <p class="mt-2 text-sm text-slate-500">Kompilasi dapat dikirim ke reviu tanpa menunggu seluruh unit kerja mengisi tanggapan.</p>
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
            'action' => route($module.'.kompilasi.index'), 'statusOptions' => $statusOptions,
            'keywordPlaceholder' => 'Cari nomor surat, ID butir, perihal, atau tanggapan...',
        ])
        <div class="overflow-hidden rounded-2xl border border-blue-100 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            @foreach (['Informasi Surat', 'Tanggapan Unit', 'Hasil Kompilasi', 'Aksi'] as $heading)
                                <th class="px-6 py-4 text-left text-xs font-bold uppercase text-slate-600">{{ $heading }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($butirs as $butir)
                            @php
                                $compilation = $workflow->current($butir->kompilasis, $butir)->firstWhere('tahap_kompilasi', 'tanggapan');
                                $unitIds = $butir->butirPics->whereIn('jenis_pic', ['utama', 'pendukung'])->pluck('unit_kerja_id')->filter()->unique();
                                $responses = $workflow->current($butir->tanggapanAktif, $butir);
                                $answered = $responses->pluck('unit_kerja_id')->filter()->unique();
                                $payload = [
                                    'preview' => $previewBahan[$butir->id],
                                    'action' => route($module.'.kompilasi.store', $butir), 'putaran_id' => $workflow->roundId($butir),
                                    'butir_id' => $butir->{'id_butir_'.$module}, 'butir' => $butir->{'butir_'.$module},
                                    'round_label' => $workflow->roundLabel($butir), 'record_id' => $butir->record->id,
                                    'hasil_kompilasi' => $compilation?->hasil_kompilasi ?? '', 'deliverables' => $compilation?->deliverables ?? '',
                                    'ubah_tgl' => $compilation?->ubah_tgl?->format('Y-m-d') ?? '',
                                    'image_upload_url' => route($module.'.kompilasi.images.store', ['butir' => $butir, 'putaran_id' => $workflow->roundId($butir)], false),
                                ];
                            @endphp
                            <tr>
                                <td class="px-6 py-5 align-top">
                                    <p class="font-bold text-blue-700">{{ $butir->record->nomor_surat }}</p>
                                    @if ($butir->record->closed_at)
                                        <p class="mt-2 text-xs font-semibold text-blue-700">Close: {{ \App\Enums\SuratStatus::labelFor($butir->record->status) }} · {{ $butir->record->closed_at->format('d/m/Y H:i') }}.</p>
                                        <p class="mt-1 text-xs text-slate-500">Bahan kompilasi terkunci. Tanggapan setelah penutupan tersedia melalui Detail di halaman Tanggapan.</p>
                                    @endif
                                    <p class="mt-2 text-xs text-slate-600">{{ $butir->{'id_butir_'.$module} }} · {{ $workflow->roundLabel($butir) }}</p>
                                    <x-butir-preview class="mt-3" :content="$butir->{'butir_'.$module}" :butir-id="$butir->{'id_butir_'.$module}" />
                                </td>
                                <td class="px-6 py-5 align-top text-sm text-slate-700">
                                    <p class="font-semibold">{{ $answered->intersect($unitIds)->count() }} dari {{ $unitIds->count() }} unit telah menanggapi</p>
                                    @forelse ($responses as $response)
                                        <details class="mt-3">
                                            <summary class="cursor-pointer font-semibold">{{ $response->unitKerja?->nama_unit ?? 'Unit pengisi belum teridentifikasi (data lama)' }}</summary>
                                            <p class="mt-2 text-xs text-slate-500">{{ $response->creator?->name ?? '-' }} · {{ $response->created_at?->format('d/m/Y H:i') ?? '-' }}</p>
                                            <p class="mt-2 whitespace-pre-line">{{ $response->tanggapan }}</p>
                                            <p class="mt-2">Deliverables: {{ $response->deliverables ?? '-' }}</p>
                                        </details>
                                    @empty
                                        <p class="mt-3 text-slate-500">Belum ada tanggapan pada putaran ini.</p>
                                    @endforelse
                                </td>
                                <td class="px-6 py-5 align-top text-sm">
                                    <p class="font-semibold text-slate-600">{{ $statusOptions[$compilation?->status ?? 'belum_dikompilasi'] ?? $compilation?->status }}</p>
                                    <x-rich-content :content="$compilation?->hasil_kompilasi" class="mt-3" />
                                    <p class="mt-3 whitespace-pre-line">{{ $compilation?->deliverables ?? '-' }}</p>
                                    @if ($compilation?->dokumen)
                                        <a class="mt-3 inline-block text-blue-700 underline" href="{{ route($module.'.kompilasi.dokumen', $compilation) }}">Dokumen Kompilasi</a>
                                    @endif
                                </td>
                                <td class="px-6 py-5 align-top">
                                    @if ($workflow->canCompile(auth()->user(), $butir))
                                        <button type="button" data-kompilasi-putaran @click="openMaterialModal(@js($payload))" class="rounded-xl bg-amber-500 px-4 py-2 text-sm font-bold text-white">Kompilasi</button>
                                    @else
                                        <span class="text-xs text-slate-500">Hanya dapat dibaca</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-6 py-10 text-center text-slate-500">Belum ada butir yang dapat ditampilkan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t p-4">@include('layouts.partials.compact-pagination', ['paginator' => $butirs])</div>
        </div>
        <div x-show="openModal" x-transition.opacity style="display: none;" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/60 px-4 py-8">
            <div class="w-full max-w-3xl rounded-2xl bg-white p-6 shadow-2xl" role="dialog" aria-modal="true" aria-label="Kompilasi tanggapan">
                <div class="mb-5 flex justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-bold text-slate-800">Kompilasi Tanggapan</h2>
                        <p data-workflow-context class="mt-1 text-sm text-slate-500" x-text="selected.butir_id + ' · ' + selected.round_label"></p>
                        <p class="mt-2 text-sm text-slate-500">Setelah dikirim, hasil kompilasi dikunci sebagai bahan reviu.</p>
                    </div>
                    <button type="button" @click="openModal = false" aria-label="Tutup modal" class="text-2xl text-slate-500">&times;</button>
                </div>
                @include('layouts.partials.reviu-bahan-preview', ['showCompilation' => false])
                <form method="POST" :action="selected.action" enctype="multipart/form-data" data-kompilasi-form>
                    @csrf
                    <input type="hidden" name="tahap_kompilasi" value="tanggapan">
                    <input type="hidden" name="putaran_id" :value="selected.putaran_id ?? ''">
                    <x-butir-preview class="mb-5" content-expression="selected.butir" id-expression="selected.butir_id" />
                    <label class="mb-2 block text-sm font-semibold">Hasil Kompilasi</label>
                    <x-snp-butir-editor name="hasil_kompilasi" label="Hasil Kompilasi" :module="$module"
                        content-expression="selected.hasil_kompilasi" record-expression="selected.record_id"
                        key-expression="openModal ? selected.image_upload_url : 'closed'" upload-url-expression="selected.image_upload_url" />
                    <label class="mb-2 mt-5 block text-sm font-semibold">Deliverables Kompilasi</label>
                    <textarea name="deliverables" rows="3" x-model="selected.deliverables" class="w-full rounded-xl border-slate-300"></textarea>
                    <label class="mb-2 mt-5 block text-sm font-semibold">Pengajuan Ubah Tanggal Jatuh Tempo</label>
                    <input type="date" name="ubah_tgl" x-model="selected.ubah_tgl" class="w-full rounded-xl border-slate-300">
                    <label class="mb-2 mt-5 block text-sm font-semibold">Dokumen Kompilasi</label>
                    <input type="file" name="dokumen" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" class="w-full rounded-xl border p-3">
                    <p class="mt-1 text-xs text-slate-500">PDF, Word, Excel, JPG, PNG. Maksimal 5 MB.</p>
                    <div class="mt-6 flex justify-end gap-3">
                        <button type="button" @click="openModal = false" class="rounded-xl border px-4 py-2">Batal</button>
                        <button type="submit" class="rounded-xl bg-blue-700 px-4 py-2 font-semibold text-white">Simpan &amp; Kirim ke Reviu Dewas</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
