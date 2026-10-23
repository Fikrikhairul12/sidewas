<x-app-layout :compact-header="true">
    <div x-data="isianUnitTahapPage()" @keydown.escape.window="createOpen = false; detailOpen = false; editOpen = false" class="space-y-6">
        <div class="rounded-2xl border border-blue-100 bg-white p-6 shadow-sm">
            <p class="text-sm font-semibold uppercase tracking-wide text-blue-700">{{ strtoupper($module) }}</p>
            <h1 class="mt-2 text-3xl font-bold text-slate-800">{{ $typeLabel }} {{ strtoupper($module) }}</h1>
            <p class="mt-2 text-sm text-slate-500">
                @if ($type === \App\Enums\TahapProses::TanggapanAwal)
                    Setiap unit mengisi tanggapan awal satu kali per butir. Perbaikan berikutnya dilakukan melalui Tindak Lanjut.
                @else
                    Setiap unit mengisi tindak lanjut baru pada putaran perbaikan yang aktif.
                @endif
                Edit isian melalui Detail.
            </p>
        </div>

        @if (session('success'))
            <p role="status" class="rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-700">{{ session('success') }}</p>
        @endif
        @if ($errors->any())
            <div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        @include('layouts.'.$module.'.partials.filter-lanjutan', [
            'action' => route($indexRoute),
            'statusOptions' => ['belum' => 'Belum Mengisi', 'sudah' => 'Sudah Mengisi'],
            'keywordPlaceholder' => 'Cari nomor surat, ID butir, perihal, atau isi butir...',
        ])

        <div class="overflow-hidden rounded-2xl border border-blue-100 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50 text-left text-xs font-bold uppercase text-slate-600">
                        <tr>
                            <th class="px-6 py-4">Informasi Surat</th>
                            <th class="px-6 py-4">Butir</th>
                            <th class="px-6 py-4">Isian Unit</th>
                            <th class="px-6 py-4">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse ($entries as $entry)
                            @php
                                $butir = $entry['butir'];
                                $state = $entry['state'];
                                $selectedStage = collect($state['history'])->firstWhere('is_current', true) ?? ($state['history'][0] ?? null);
                                $createPayload = \Illuminate\Support\Arr::except($state['detail'], ['stages']) + [
                                    'tahap_id' => $state['stage_id'],
                                    'post_url' => $type === \App\Enums\TahapProses::TanggapanAwal ? route($module.'.tanggapan.store', $butir) : route($module.'.tindak-lanjut.store'),
                                    'pics' => $state['available_pics']->map(fn ($pic) => ['id' => $pic->id, 'name' => $pic->unitKerja?->nama_unit ?? 'Unit #'.$pic->unit_kerja_id])->all(),
                                ];
                            @endphp
                            <tr data-isian-butir="{{ $butir->id }}">
                                <td class="px-6 py-5 align-top text-sm">
                                    <p class="font-bold text-blue-700">{{ $butir->record->nomor_surat }}</p>
                                    <p class="mt-2 text-slate-500">{{ $butir->record->perihal_surat }}</p>
                                    <p class="mt-2 font-semibold">{{ \App\Enums\SuratStatus::labelFor($butir->record->status) }}</p>
                                    <p class="mt-2 text-xs text-slate-500">{{ $selectedStage['label'] ?? $typeLabel }}</p>
                                    <x-surat-tenggat :record="$butir->record" :stage="$butir->record->tahapanProses->firstWhere('id', $state['stage_id'])" />
                                </td>
                                <td class="max-w-md px-6 py-5 align-top text-sm">
                                    <p class="mb-2 font-semibold">{{ $butir->{'id_butir_'.$module} }}</p>
                                    <x-butir-preview :content="$butir->{'butir_'.$module}" :butir-id="$butir->{'id_butir_'.$module}" :show-read-button="false" />
                                </td>
                                <td class="px-6 py-5 align-top text-sm">
                                    @forelse (collect($selectedStage['items'] ?? [])->take(2) as $item)
                                        <div class="mb-3">
                                            <p class="font-semibold">{{ $item['unit_label'] }}</p>
                                            <p class="mt-1 text-slate-600">{{ \Illuminate\Support\Str::limit($item['isi'], 110) }}</p>
                                            @if ($item['not_compiled'])
                                                <p class="mt-1 text-xs text-amber-800">Tidak termasuk kompilasi</p>
                                            @endif
                                            @if ($item['late'])<p class="mt-1 text-xs font-semibold text-amber-800">Terlambat saat pengisian</p>@endif
                                        </div>
                                    @empty
                                        <p class="text-slate-500">Belum ada isian unit pada tahap ini.</p>
                                    @endforelse
                                    @if ($state['closed'])
                                        <p class="mt-2 text-xs font-semibold text-blue-700">Close. Kelengkapan hanya untuk unit yang belum mengisi tahap terakhir.</p>
                                    @endif
                                    @if ($state['needs_reconciliation'])
                                        <p class="mt-2 text-xs text-amber-800">Riwayat perlu rekonsiliasi sebelum pengisian.</p>
                                    @elseif (! $state['is_current'])
                                        <p class="mt-2 text-xs text-slate-500">Riwayat terkunci. Pengisian mengikuti tahap aktif surat.</p>
                                    @endif
                                </td>
                                <td class="space-y-2 px-6 py-5 align-top">
                                    <button type="button" data-isian-detail="{{ $butir->id }}" @click="openDetail(@js($state['detail']))"
                                        class="rounded-xl bg-green-600 px-4 py-2 text-sm font-semibold text-white">Detail</button>
                                    @if ($state['available_pics']->isNotEmpty())
                                        <button type="button" data-beri-isian="{{ $butir->id }}" @click="openCreate(@js($createPayload))"
                                            class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white">{{ $actionLabel }}</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-6 py-10 text-center text-sm text-slate-500">
                                {{ $type === \App\Enums\TahapProses::TindakLanjut ? 'Belum ada putaran Tindak Lanjut yang tersedia.' : 'Belum ada butir yang tersedia.' }}
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-100 px-6 py-4">{{ $entries->links() }}</div>
        </div>

        <div data-isian-create-modal x-show="createOpen" x-transition.opacity style="display: none;" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/60 px-4 py-8">
            <div @click.outside="createOpen = false" class="w-full max-w-2xl rounded-2xl bg-white p-6 shadow-2xl">
                <div class="flex items-start justify-between">
                    <h2 class="text-xl font-bold text-slate-800">{{ $actionLabel }} {{ strtoupper($module) }}</h2>
                    <button type="button" @click="createOpen = false" class="rounded-lg px-3 py-1" aria-label="Tutup">✕</button>
                </div>
                <p class="mt-2 text-sm text-slate-500" x-text="createButir?.nomor_surat"></p>
                <x-butir-preview class="mt-4" content-expression="createButir?.butir_content" id-expression="createButir?.butir_key" :expanded="true" :show-read-button="false" />
                <form method="POST" enctype="multipart/form-data" :action="createButir?.post_url" class="mt-5 space-y-4" data-isian-create>
                    @csrf
                    <input type="hidden" name="butir_id" :value="createButir?.butir_id">
                    <input type="hidden" name="tahap_id" :value="createButir?.tahap_id ?? ''">
                    <label class="block text-sm font-semibold text-slate-700">Unit Pengisi
                        <select name="butir_pic_id" required x-model="createData.butir_pic_id" class="mt-2 w-full rounded-xl border-slate-300">
                            <option value="">Pilih unit kerja</option>
                            <template x-for="pic in (createButir?.pics ?? [])" :key="pic.id"><option :value="pic.id" x-text="pic.name"></option></template>
                        </select>
                    </label>
                    <label class="block text-sm font-semibold text-slate-700">{{ $typeLabel }}
                        <textarea name="isi" rows="5" required x-model="createData.isi" class="mt-2 w-full rounded-xl border-slate-300"></textarea>
                    </label>
                    <label class="block text-sm font-semibold text-slate-700">Deliverables
                        <textarea name="deliverables" rows="3" required x-model="createData.deliverables" class="mt-2 w-full rounded-xl border-slate-300"></textarea>
                    </label>
                    <label class="block text-sm font-semibold text-slate-700">Dokumen Pendukung
                        <input type="file" name="dokumen" class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm">
                    </label>
                    <p class="text-xs text-slate-500">PDF, Word, Excel, JPG, atau PNG. Maksimal 5 MB.</p>
                    <div class="flex justify-end gap-3">
                        <button type="button" @click="createOpen = false" class="rounded-xl border px-4 py-2">Batal</button>
                        <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 font-semibold text-white">Simpan</button>
                    </div>
                </form>
            </div>
        </div>

        <div data-isian-detail-modal x-show="detailOpen" x-transition.opacity style="display: none;" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/60 px-4 py-8">
            <div @click.outside="detailOpen = false" class="w-full max-w-6xl rounded-2xl bg-white p-6 shadow-2xl">
                <div class="flex items-start justify-between">
                    <div><h2 class="text-xl font-bold text-slate-800">Detail {{ $typeLabel }} {{ strtoupper($module) }}</h2><p class="mt-2 text-sm text-slate-500" x-text="detail?.nomor_surat"></p></div>
                    <button type="button" @click="detailOpen = false" class="rounded-lg px-3 py-1" aria-label="Tutup">✕</button>
                </div>
                <div class="mt-5 max-h-[70vh] space-y-5 overflow-y-auto">
                    <x-butir-preview content-expression="detail?.butir_content" id-expression="detail?.butir_key" :expanded="true" :show-read-button="false" />
                    <label class="block text-sm font-semibold text-slate-700">Tahap / Putaran
                        <select data-isian-stage x-model="stageIndex" @change="unitIndex = 0" class="mt-2 w-full max-w-full rounded-xl border-slate-300 sm:w-auto">
                            <template x-for="(stage, index) in (detail?.stages ?? [])" :key="stage.id ?? 'awal'">
                                <option :value="index" x-text="stage.label + (stage.is_closed ? ' (Close)' : (stage.is_current ? ' (Aktif)' : ' (Riwayat)'))"></option>
                            </template>
                        </select>
                    </label>
                    <p x-show="detail?.closed_label" class="rounded-xl bg-blue-50 p-3 text-sm text-blue-700">
                        <strong x-text="'Close: ' + (detail?.closed_label ?? '')"></strong>
                        <span class="block">Unit yang belum mengisi tahap terakhir dapat melengkapi tanpa mengubah keputusan.</span>
                    </p>
                    <p x-show="selectedStage?.read_only" data-isian-locked class="rounded-xl bg-slate-50 p-3 text-sm text-slate-600">Isian yang sudah tersimpan pada tahap ini terkunci.</p>
                    <p x-show="selectedStage?.needs_reconciliation" class="rounded-xl bg-amber-50 p-3 text-sm text-amber-800">Identitas putaran lama perlu direkonsiliasi.</p>
                    <x-surat-tenggat-detail expression="selectedStage?.dates" />
                    <div class="flex flex-wrap gap-2">
                        <template x-for="(item, index) in (selectedStage?.items ?? [])" :key="item.key">
                            <button type="button" @click="unitIndex = index" class="rounded-xl border px-4 py-2 text-sm" :class="Number(unitIndex) === index ? 'bg-blue-50 font-semibold' : 'bg-white'" x-text="item.unit_label"></button>
                        </template>
                    </div>
                    <template x-if="selectedItem">
                        <section class="space-y-4 break-words rounded-xl border border-slate-200 p-5">
                            <h3 class="font-bold text-blue-700" x-text="selectedItem.unit_label"></h3>
                            <p x-show="selectedItem.after_close" data-isian-after-close class="rounded-lg bg-amber-50 p-3 text-sm text-amber-800">Setelah penutupan</p>
                            <p x-show="selectedItem.late" data-isian-late class="text-sm font-semibold text-amber-800" x-text="'Terlambat saat pengisian. Batas yang berlaku: ' + (selectedItem.deadline_when_submitted ?? '-') + ' 23.59 WIB.'"></p>
                            <p x-show="selectedItem.not_compiled" data-isian-not-compiled class="text-sm font-semibold text-amber-800">Tidak termasuk kompilasi</p>
                            <p x-show="selectedItem.changed_after_compilation" class="text-sm text-amber-800">Diubah setelah kompilasi</p>
                            <div><p class="text-sm font-semibold text-slate-600">{{ $typeLabel }}</p><p class="mt-2 whitespace-pre-line text-sm leading-relaxed" x-text="selectedItem.isi"></p></div>
                            <div><p class="text-sm font-semibold text-slate-600">Deliverables</p><p class="mt-2 whitespace-pre-line text-sm leading-relaxed" x-text="selectedItem.deliverables"></p></div>
                            <p class="text-xs text-slate-500" x-text="'Diisi oleh ' + selectedItem.creator + ' pada ' + selectedItem.created_at"></p>
                            <p class="text-xs text-slate-500" x-text="'Terakhir diubah oleh ' + selectedItem.updater + ' pada ' + selectedItem.updated_at"></p>
                            <a x-show="selectedItem.document_url" :href="selectedItem.document_url" target="_blank" rel="noopener" class="inline-block rounded-xl border px-4 py-2 text-sm text-blue-700">Download Dokumen</a>
                            <button x-show="selectedItem.can_edit" type="button" data-edit-via-detail @click="openEdit(selectedItem)" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white">Edit Isian</button>
                        </section>
                    </template>
                    <p x-show="!selectedItem" class="text-sm text-slate-500">Belum ada isian unit pada tahap ini.</p>
                    <p x-show="(selectedStage?.missing_units ?? []).length" class="text-sm text-slate-600" x-text="'Belum mengisi: ' + (selectedStage?.missing_units ?? []).join(', ')"></p>
                    <div class="flex items-center justify-between gap-3">
                        <button data-isian-unit-prev type="button" @click="moveUnit(-1)" :disabled="(selectedStage?.items ?? []).length < 2" class="rounded-xl border px-4 py-2 text-sm disabled:opacity-50">Unit Sebelumnya</button>
                        <span class="text-xs text-slate-500" x-text="selectedItem ? 'Isian ' + (Number(unitIndex) + 1) + ' dari ' + selectedStage.items.length : '0 isian'"></span>
                        <button data-isian-unit-next type="button" @click="moveUnit(1)" :disabled="(selectedStage?.items ?? []).length < 2" class="rounded-xl border px-4 py-2 text-sm disabled:opacity-50">Unit Berikutnya</button>
                    </div>
                </div>
            </div>
        </div>

        <div data-isian-edit-modal x-show="editOpen" x-transition.opacity style="display: none;" class="fixed inset-0 z-[60] flex items-start justify-center overflow-y-auto bg-slate-900/60 px-4 py-8">
            <div @click.outside="editOpen = false" class="w-full max-w-2xl rounded-2xl bg-white p-6 shadow-2xl">
                <h2 class="text-xl font-bold text-slate-800">Edit {{ $typeLabel }} melalui Detail</h2>
                <p class="mt-2 text-sm text-slate-500" x-text="editEntry.unit_label"></p>
                <form data-isian-edit method="POST" enctype="multipart/form-data" :action="editEntry.update_url" class="mt-5 space-y-4">
                    @csrf
                    @method('PATCH')
                    <label class="block text-sm font-semibold text-slate-700">Isi<textarea name="isi" rows="5" required x-model="editEntry.isi" class="mt-2 w-full rounded-xl border-slate-300"></textarea></label>
                    <label class="block text-sm font-semibold text-slate-700">Deliverables<textarea name="deliverables" rows="3" required x-model="editEntry.deliverables" class="mt-2 w-full rounded-xl border-slate-300"></textarea></label>
                    <label class="block text-sm font-semibold text-slate-700">Dokumen Pengganti<input type="file" name="dokumen" class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm"></label>
                    <div class="flex justify-end gap-3">
                        <button type="button" @click="editOpen = false" class="rounded-xl border px-4 py-2">Batal</button>
                        <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 font-semibold text-white">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script>
        function isianUnitTahapPage() {
            return {
                createOpen: false, detailOpen: false, editOpen: false,
                createButir: null, createData: {butir_pic_id: '', isi: '', deliverables: ''},
                detail: null, stageIndex: 0, unitIndex: 0, editEntry: {},
                get selectedStage() { return this.detail?.stages?.[Number(this.stageIndex)] ?? null; },
                get selectedItem() { return this.selectedStage?.items?.[Number(this.unitIndex)] ?? null; },
                openCreate(payload) {
                    this.createButir = payload;
                    this.createData = {butir_pic_id: payload.pics.length === 1 ? String(payload.pics[0].id) : '', isi: '', deliverables: ''};
                    const previous = @js(old());
                    if (String(previous.butir_id) === String(payload.butir_id)
                        && String(previous.tahap_id ?? '') === String(payload.tahap_id ?? '')
                        && payload.pics.some(pic => String(pic.id) === String(previous.butir_pic_id))) {
                        this.createData = {butir_pic_id: String(previous.butir_pic_id), isi: previous.isi ?? '', deliverables: previous.deliverables ?? ''};
                    }
                    this.createOpen = true;
                },
                openDetail(payload) {
                    this.detail = payload;
                    const current = payload.stages.findIndex(stage => stage.is_current);
                    this.stageIndex = current < 0 ? 0 : current;
                    this.unitIndex = 0;
                    this.detailOpen = true;
                },
                moveUnit(offset) {
                    const count = this.selectedStage?.items?.length ?? 0;
                    if (count > 1) this.unitIndex = (Number(this.unitIndex) + offset + count) % count;
                },
                openEdit(item) {
                    if (!item?.can_edit) return;
                    this.editEntry = {...item};
                    this.editOpen = true;
                },
            };
        }
    </script>
</x-app-layout>
