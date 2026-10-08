<div x-data="produkHukumReport(@js([
    'counts' => $statusStatistics,
    'optionsUrl' => route('produk-hukum.report.options'),
    'downloadUrl' => route('produk-hukum.report.download'),
    'csrf' => csrf_token(),
]))" @open-modal.window="if ($event.detail === 'produk-hukum-report') open()">
    <dialog x-ref="dialog" aria-labelledby="produk-hukum-report-title" @close="closed()"
        @click="if ($event.target === $refs.dialog) close()"
        class="m-auto rounded-2xl border-0 bg-white p-0 text-slate-800 shadow-2xl backdrop:bg-slate-900/60"
        style="width: min(48rem, calc(100% - 2rem)); max-height: 90dvh;">
        <form @submit.prevent="download()" class="flex flex-col" style="max-height: 90dvh;">
            <div class="flex shrink-0 items-start justify-between gap-4 border-b border-slate-100 px-5 py-4 sm:px-6">
                <div>
                    <h2 id="produk-hukum-report-title" class="text-xl font-bold">Download Rekap Produk Hukum</h2>
                    <p class="mt-1 text-sm text-slate-500">Pilih cakupan rekap, lalu unduh PDF untuk dicetak atau Excel untuk diolah.</p>
                </div>
                <button type="button" @click="close()" aria-label="Tutup rekap" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100">&#10005;</button>
            </div>
            <div class="min-h-0 space-y-5 overflow-y-auto px-5 py-5 sm:px-6">
                <fieldset>
                    <legend class="mb-2 text-sm font-semibold">Cakupan rekap</legend>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="flex cursor-pointer items-center gap-3 rounded-xl border p-3" :class="mode === 'status' ? 'border-blue-500 bg-blue-50' : 'border-slate-200'">
                            <input type="radio" name="report-mode" value="status" :checked="mode === 'status'" @change="changeMode('status')" class="text-sidewas-blue">
                            <span class="text-sm font-semibold">Berdasarkan status</span>
                        </label>
                        <label class="flex cursor-pointer items-center gap-3 rounded-xl border p-3" :class="mode === 'manual' ? 'border-blue-500 bg-blue-50' : 'border-slate-200'">
                            <input type="radio" name="report-mode" value="manual" :checked="mode === 'manual'" @change="changeMode('manual')" class="text-sidewas-blue">
                            <span class="text-sm font-semibold">Pilih peraturan sendiri</span>
                        </label>
                    </div>
                </fieldset>

                <div x-show="mode === 'status'">
                    <label for="report-status" class="mb-2 block text-sm font-semibold">Status peraturan</label>
                    <select id="report-status" x-model="status" @change="error = ''; success = ''" class="w-full rounded-xl border-slate-300 text-sm">
                        <option value="semua">Semua status</option>
                        @foreach ($statusOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="mt-2 text-xs leading-5 text-slate-500">Mencakup seluruh peraturan sesuai status, di semua halaman. Filter pencarian pada halaman utama tidak diterapkan.</p>
                </div>

                <div x-show="mode === 'manual'" class="space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-sm font-semibold"><span x-text="selectedProducts.length"></span> peraturan dipilih</p>
                        <div class="flex gap-3 text-xs font-semibold">
                            <button type="button" @click="selectedOnly = !selectedOnly" class="text-sidewas-blue" x-text="selectedOnly ? 'Kembali ke pencarian' : 'Lihat pilihan'"></button>
                            <button type="button" @click="selected = {}; error = ''; success = ''" :disabled="selectedProducts.length === 0" class="text-red-600 disabled:opacity-40">Kosongkan pilihan</button>
                        </div>
                    </div>
                    <div x-show="!selectedOnly">
                        <label for="report-search" class="sr-only">Cari peraturan</label>
                        <input id="report-search" type="search" x-model="keyword" @input.debounce.300ms="search()" @keydown.enter.prevent="search()"
                            maxlength="255" placeholder="Cari judul, kode, nomor, tahun, jenis, atau bidang..." class="w-full rounded-xl border-slate-300 text-sm">
                        <p class="mt-2 text-xs text-slate-500">Pilihan tetap tersimpan saat mencari atau berpindah halaman. Semua status dapat dipilih.</p>
                    </div>
                    <div x-show="searchError && !selectedOnly" role="alert" class="rounded-lg bg-red-50 p-3 text-sm text-red-700">
                        <span x-text="searchError"></span>
                        <button type="button" @click="search()" class="ml-2 font-bold underline">Coba lagi</button>
                    </div>
                    <p x-show="loading && !selectedOnly" role="status" class="py-5 text-center text-sm text-slate-500">Memuat peraturan...</p>
                    <div x-show="selectedOnly || (!loading && !searchError)" class="overflow-hidden rounded-xl border border-slate-200">
                        <div x-show="!selectedOnly && products.length" class="flex items-center justify-between gap-3 bg-slate-50 px-3 py-2 text-xs">
                            <span><span x-text="total"></span> hasil pencarian</span>
                            <button type="button" @click="selectPage()" class="font-semibold text-sidewas-blue">Pilih halaman ini</button>
                        </div>
                        <div class="max-h-72 divide-y divide-slate-100 overflow-y-auto" :aria-busy="loading">
                            <template x-for="product in displayedProducts" :key="product.id">
                                <label class="flex cursor-pointer items-start gap-3 px-3 py-3 hover:bg-blue-50" :class="selected[product.id] ? 'bg-blue-50/60' : ''">
                                    <input type="checkbox" :value="product.id" :checked="!!selected[product.id]" @change="toggle(product)"
                                        :aria-label="'Pilih ' + product.kode_produk_hukum" class="mt-1 rounded border-slate-300 text-sidewas-blue">
                                    <span class="min-w-0 flex-1">
                                        <span class="block break-words text-sm font-semibold leading-5" x-text="product.judul"></span>
                                        <span class="mt-1 block break-words text-xs text-slate-500" x-text="[product.kode_produk_hukum, product.nomor_peraturan_keputusan, product.tahun_peraturan].filter(Boolean).join(' · ')"></span>
                                        <span class="mt-1 block text-xs text-slate-600" x-text="statusLabel(product.status_peraturan) + ' · ' + (product.sifat_dokumen === 'rahasia' ? 'Rahasia' : 'Publik')"></span>
                                    </span>
                                </label>
                            </template>
                            <p x-show="displayedProducts.length === 0" class="px-4 py-8 text-center text-sm text-slate-500" x-text="selectedOnly ? 'Belum ada peraturan dipilih.' : 'Tidak ada peraturan yang cocok.'"></p>
                        </div>
                        <div x-show="!selectedOnly && lastPage > 1" class="flex items-center justify-between gap-2 border-t border-slate-200 px-3 py-2 text-xs">
                            <button type="button" @click="search(page - 1)" :disabled="page <= 1 || loading" class="rounded-lg border px-3 py-2 disabled:opacity-40">Sebelumnya</button>
                            <span>Halaman <span x-text="page"></span> / <span x-text="lastPage"></span></span>
                            <button type="button" @click="search(page + 1)" :disabled="page >= lastPage || loading" class="rounded-lg border px-3 py-2 disabled:opacity-40">Berikutnya</button>
                        </div>
                    </div>
                </div>

                <fieldset>
                    <legend class="mb-2 text-sm font-semibold">Format file</legend>
                    <div class="flex flex-wrap gap-5 text-sm">
                        <label class="flex items-center gap-2"><input type="radio" name="report-format" value="pdf" x-model="format" class="text-sidewas-blue"> PDF (A4 landscape)</label>
                        <label class="flex items-center gap-2"><input type="radio" name="report-format" value="xlsx" x-model="format" class="text-sidewas-blue"> Excel (.xlsx)</label>
                    </div>
                </fieldset>
                <p class="text-xs leading-5 text-slate-500">Rekap memuat judul, kode, nomor, tahun, jenis, bidang, sifat, dan status peraturan.</p>
                <p x-show="error" x-text="error" role="alert" class="rounded-xl bg-red-50 p-3 text-sm text-red-700"></p>
                <p x-show="success" x-text="success" role="status" class="rounded-xl bg-green-50 p-3 text-sm text-green-700"></p>
            </div>
            <div class="flex shrink-0 flex-wrap items-center justify-between gap-3 border-t border-slate-100 bg-slate-50 px-5 py-4 sm:px-6">
                <p class="text-sm text-slate-600" aria-live="polite"><strong x-text="count"></strong> peraturan akan diunduh</p>
                <button type="submit" :disabled="count === 0 || downloading" class="rounded-xl bg-sidewas-blue px-5 py-3 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-50"
                    x-text="downloading ? 'Menyiapkan file...' : 'Download Rekap'"></button>
            </div>
        </form>
    </dialog>
</div>
