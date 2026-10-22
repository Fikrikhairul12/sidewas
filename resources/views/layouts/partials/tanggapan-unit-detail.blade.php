        {{-- Modal Detail Tanggapan --}}
        <div x-show="openDetailModal" x-transition.opacity
            class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/60 px-4 py-8"
            style="display: none;">
            <div @click.outside="openDetailModal = false" x-transition
                class="w-full max-w-6xl overflow-hidden rounded-2xl bg-white shadow-2xl">

                <div class="flex items-start justify-between border-b border-slate-100 px-6 py-5">
                    <div>
                        <h2 class="text-2xl font-bold text-slate-800">
                            Detail Tanggapan {{ $moduleLabel }}
                        </h2>
                        <p class="mt-1 text-sm font-semibold text-slate-500" x-text="detailButir?.id_surat ?? '-'"></p>
                        <p class="mt-1 text-xs text-slate-500" x-text="detailRound?.round_label ?? detailButir?.round_label"></p>
                        <div x-show="detailRounds.length > 1" class="mt-3">
                            <label class="mb-1 block text-xs font-semibold text-slate-600" for="tanggapan-round">Putaran Tanggapan</label>
                            <select id="tanggapan-round" data-tanggapan-round x-model="selectedDetailRoundId" @change="selectDetailRound($event.target.value)"
                                class="rounded-xl border-slate-300 text-sm">
                                <template x-for="round in detailRounds" :key="round.id">
                                    <option :value="round.id" x-text="round.round_label + (round.is_closed ? ' (Close)' : (round.is_current ? ' (Aktif)' : ' (Terkunci)'))"></option>
                                </template>
                            </select>
                        </div>
                    </div>

                    <button type="button" @click="openDetailModal = false"
                        class="rounded-xl p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="grid max-h-[70vh] overflow-y-auto lg:grid-cols-[260px_minmax(0,1fr)] lg:overflow-hidden">
                    <div class="border-b border-slate-100 p-5 lg:border-b-0 lg:border-r">
                        <div class="relative">
                            <svg class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400"
                                fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="m21 21-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z" />
                            </svg>
                            <input type="text" x-model="detailSearch" placeholder="Cari unit / isi tanggapan..."
                                class="w-full rounded-xl border-slate-300 pl-10 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>

                        <div class="mt-4 max-h-[52vh] space-y-3 overflow-y-auto pr-1">
                            <template x-for="pic in filteredDetailPics" :key="pic.id">
                                <button type="button" @click="selectDetailPic(pic)"
                                    class="flex w-full items-center gap-3 rounded-xl border px-4 py-3 text-left transition hover:bg-blue-50"
                                    :class="String(selectedDetailPic?.id) === String(pic.id)
                                        ? 'border-blue-300 bg-blue-50'
                                        : 'border-slate-200 bg-white'">
                                    <span
                                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                                        :class="pic.sudah_menanggapi ? 'bg-blue-100 text-blue-700' :
                                            'bg-slate-100 text-slate-500'"
                                        x-text="pic.initial"></span>

                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm font-bold text-slate-800"
                                            x-text="`${pic.initial} - ${pic.jenis_pic}`"></span>
                                        <span class="mt-1 block text-xs text-slate-500"
                                            x-text="pic.submitted_after_close ? 'Tanggapan setelah penutupan' : (pic.sudah_menanggapi ? 'Sudah mengisi tanggapan' : 'Belum mengisi tanggapan')"></span>
                                    </span>

                                    <svg x-show="pic.sudah_menanggapi" class="h-5 w-5 shrink-0 text-green-500"
                                        fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                </button>
                            </template>

                            <div x-show="filteredDetailPics.length === 0"
                                class="rounded-xl border border-dashed border-slate-200 px-4 py-8 text-center text-sm text-slate-400">
                                Unit/tanggapan tidak ditemukan.
                            </div>
                        </div>
                    </div>

                    <div class="min-w-0 space-y-5 p-6 lg:max-h-[70vh] lg:overflow-y-auto">
                        <p x-show="detailButir?.closed_at" data-surat-closed class="rounded-xl border border-blue-200 bg-blue-50 p-3 text-sm text-blue-800">
                            <strong x-text="'Close: ' + (detailButir?.closed_status_label ?? '')"></strong>
                            <span class="block" x-text="'Ditutup pada ' + (detailButir?.closed_at ?? '')"></span>
                            Unit yang belum mengisi dapat melengkapi putaran terakhir. Kelengkapan ini tidak mengubah bahan atau keputusan reviu.
                        </p>
                        <p x-show="detailRound?.read_only" data-round-readonly class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                            Putaran lama terkunci. Tanggapan baru hanya dapat diisi pada putaran aktif.
                        </p>
                        <p x-show="detailRound?.assignments_unknown" class="rounded-xl bg-slate-50 p-3 text-sm text-slate-500">
                            Penugasan unit pada putaran lama tidak tercatat. Unit yang belum mengisi tidak dapat dipastikan.
                        </p>
                        <x-butir-preview content-expression="detailButir?.butir_content" id-expression="detailButir?.id_butir"
                            :expanded="true" context="Detail Tanggapan" />
                        <template x-if="selectedDetailPic">
                            <div class="space-y-5">
                                <div>
                                    <p class="text-lg font-bold" style="color: #2377b9;"
                                        x-text="selectedDetailPic.unit_label"></p>
                                    <p x-show="!selectedDetailPic.sudah_menanggapi" class="mt-2 text-sm text-slate-500">Belum memberikan tanggapan pada putaran ini.</p>
                                    <p x-show="selectedDetailPic.submitted_after_close" data-after-close class="mt-2 rounded-lg bg-amber-50 p-3 text-sm text-amber-800">
                                        <strong>Tanggapan setelah penutupan</strong>
                                        <span class="block" x-text="selectedDetailPic.submitted_after_close_at"></span>
                                        Kelengkapan terlambat, tidak termasuk bahan keputusan reviu.
                                    </p>
                                </div>

                                <div class="grid gap-4 text-sm md:grid-cols-[130px_minmax(0,1fr)]">
                                    <p class="font-bold text-slate-600">Tipe PIC</p>
                                    <p class="text-slate-700" x-text="selectedDetailPic.jenis_pic"></p>

                                    <p class="font-bold text-slate-600">Butir {{ $moduleLabel }}</p>
                                    <p class="text-slate-700" x-text="selectedDetailPic.id_butir"></p>

                                    <p class="font-bold text-slate-600">Tanggapan</p>
                                    <p class="whitespace-pre-line leading-relaxed text-slate-700"
                                        x-text="selectedDetailPic.tanggapan ?? '-'"></p>

                                    <p class="font-bold text-slate-600">Deliverables</p>
                                    <p class="whitespace-pre-line leading-relaxed text-slate-700"
                                        x-text="selectedDetailPic.deliverables ?? '-'"></p>

                                    <p class="font-bold text-slate-600">Dokumen PIC</p>
                                    <div>
                                        <template x-if="selectedDetailPic.dokumen_url">
                                            <a :href="selectedDetailPic.dokumen_url" target="_blank"
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
                                        <p class="text-slate-400" x-show="!selectedDetailPic.dokumen_url">-</p>
                                    </div>

                                    <p class="font-bold text-slate-600">Diinput oleh</p>
                                    <p class="text-slate-700" x-text="selectedDetailPic.creator ?? '-'"></p>

                                    <p class="font-bold text-slate-600">Tanggal Input</p>
                                    <p class="text-slate-700" x-text="selectedDetailPic.created_at ?? '-'"></p>
                                    <p class="font-bold text-slate-600">Diubah oleh</p>
                                    <p class="text-slate-700" x-text="selectedDetailPic.updater ?? '-'"></p>
                                    <p class="font-bold text-slate-600">Terakhir Diubah</p>
                                    <p class="text-slate-700" x-text="selectedDetailPic.updated_at ?? '-'"></p>

                                    <button type="button" data-edit-tanggapan x-show="selectedDetailPic.can_edit"
                                        @click="openEditModalFor(selectedDetailPic)"
                                        class="mt-4 rounded-lg bg-amber-500 px-4 py-2 text-xs font-bold text-white">
                                        Edit Tanggapan
                                    </button>
                                </div>
                            </div>
                        </template>

                        <div x-show="!selectedDetailPic"
                            class="rounded-xl border border-dashed border-slate-200 px-4 py-14 text-center text-sm text-slate-400">
                            <span x-text="detailRound?.read_only ? 'Belum ada tanggapan tersimpan pada putaran ini.' : 'Belum ada PIC pada butir ini.'"></span>
                        </div>
                        <template x-for="item in (detailRound?.compilations ?? [])" :key="'kompilasi-' + item.id">
                            <section data-history-kompilasi class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <h3 class="font-bold text-slate-700">Hasil Kompilasi</h3>
                                <p class="mt-1 text-xs text-slate-500" x-text="item.creator + ' · ' + item.created_at"></p>
                                <x-rich-content content-expression="item.content" class="mt-3" />
                                <p class="mt-3 whitespace-pre-line text-sm" x-text="'Deliverables: ' + (item.deliverables || '-')"></p>
                                <a x-show="item.document_url" :href="item.document_url" class="mt-3 inline-block text-sm text-blue-700 underline">Dokumen Kompilasi</a>
                            </section>
                        </template>
                        <template x-for="item in (detailRound?.legacy_followups ?? [])" :key="'tindak-lanjut-lama-' + item.id">
                            <section data-history-tindak-lanjut class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <h3 class="font-bold text-slate-700">Riwayat Tindak Lanjut Lama</h3>
                                <p class="mt-1 text-xs text-slate-500" x-text="item.creator + ' · ' + item.created_at"></p>
                                <p class="mt-3 whitespace-pre-line text-sm" x-text="item.content || '-'"></p>
                                <p class="mt-3 whitespace-pre-line text-sm" x-text="'Deliverables: ' + (item.deliverables || '-')"></p>
                                <a x-show="item.document_url" :href="item.document_url" class="mt-3 inline-block text-sm text-blue-700 underline">Dokumen Tindak Lanjut Lama</a>
                            </section>
                        </template>
                        <template x-for="item in (detailRound?.reviews ?? [])" :key="'reviu-' + item.id">
                            <section data-history-reviu class="rounded-xl border border-slate-200 p-4">
                                <h3 class="font-bold text-slate-700">Hasil Reviu</h3>
                                <p class="mt-1 text-sm font-semibold text-blue-700" x-text="item.status_label"></p>
                                <p class="mt-1 text-xs text-slate-500" x-text="item.creator + ' · ' + item.created_at"></p>
                                <x-rich-content content-expression="item.content" class="mt-3" />
                                <p class="mt-3 whitespace-pre-line text-sm" x-text="'Deliverables: ' + (item.deliverables || '-')"></p>
                                <a x-show="item.document_url" :href="item.document_url" class="mt-3 inline-block text-sm text-blue-700 underline">Dokumen Hasil Reviu</a>
                            </section>
                        </template>
                    </div>
                </div>

                <div class="flex justify-end gap-3 border-t border-slate-100 px-6 py-4">
                    <button type="button" @click="openDetailModal = false"
                        class="rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Tutup
                    </button>

                    <button type="button" data-unit-next @click="selectNextDetailPic()"
                        :disabled="filteredDetailPics.length < 2"
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

        <div x-show="openEditModal" x-transition.opacity
            class="fixed inset-0 z-[60] flex items-start justify-center overflow-y-auto bg-slate-900/60 px-4 py-8"
            style="display: none;">
            <div @click.outside="openEditModal = false" class="w-full max-w-2xl rounded-2xl bg-white p-6 shadow-2xl">
                <h2 class="text-xl font-bold text-slate-800">Edit Tanggapan {{ $moduleLabel }}</h2>
                <x-butir-preview class="mt-4" content-expression="editTanggapan?.butir_content" id-expression="editTanggapan?.id_butir" context="Rujukan tanggapan" />
                <form data-tanggapan-edit method="POST" enctype="multipart/form-data" :action="editTanggapan?.update_url" class="mt-5 space-y-4">
                    @csrf
                    @method('PATCH')
                    <label class="block text-sm font-semibold text-slate-700">Tanggapan</label>
                    <textarea name="tanggapan" rows="5" required x-model="editTanggapan.tanggapan" class="w-full rounded-xl border-slate-300"></textarea>
                    <label class="block text-sm font-semibold text-slate-700">Deliverables</label>
                    <textarea name="deliverables" rows="3" required x-model="editTanggapan.deliverables" class="w-full rounded-xl border-slate-300"></textarea>
                    @if ($module === 'djsn')
                        <label class="block text-sm font-semibold text-slate-700">Pengajuan Ubah Tanggal Jatuh Tempo</label>
                        <input type="date" name="ubah_tgl" x-model="editTanggapan.ubah_tgl" class="w-full rounded-xl border-slate-300">
                    @endif
                    <input type="file" name="dokumen" class="w-full rounded-xl border border-slate-300 p-3 text-sm">
                    <div class="flex justify-end gap-3">
                        <button type="button" @click="openEditModal = false" class="rounded-xl border px-4 py-2">Batal</button>
                        <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 font-semibold text-white">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>

