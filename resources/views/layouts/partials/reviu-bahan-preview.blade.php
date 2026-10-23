<div data-bahan-preview class="mb-6 space-y-4">
    <section aria-labelledby="preview-tanggapan-title" class="rounded-xl border border-blue-100 bg-blue-50 p-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h3 id="preview-tanggapan-title" class="font-bold text-slate-800" x-text="'Preview ' + (selected.preview?.kind_label ?? 'Tanggapan') + ' Unit Kerja'">Preview Tanggapan Unit Kerja</h3>
            <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-slate-600">Hanya baca</span>
        </div>
        <p class="mt-2 break-words text-xs text-slate-600" data-preview-context
            x-text="'Surat: ' + (selected.preview?.letter_number ?? '-') + ' · Butir: ' + (selected.preview?.butir_id ?? '-') + ' · ' + (selected.preview?.round_label ?? '-')"></p>
        <p x-show="selected.preview?.is_snapshot" class="mt-2 text-xs font-semibold text-blue-700">Salinan bahan saat kompilasi. Isian atau perubahan setelah kompilasi tidak disertakan.</p>
        <p x-show="selected.preview?.missing_units?.length" class="mt-2 text-xs text-slate-600" x-text="'Belum mengisi saat kompilasi: ' + (selected.preview?.missing_units ?? []).join(', ')"></p>
        <p data-preview-counter aria-live="polite" aria-atomic="true" x-show="previewResponses.length > 0"
            class="mt-3 text-sm font-semibold text-blue-700" x-text="previewCounter"></p>
        <template x-if="previewResponse">
            <article class="mt-3 rounded-lg border border-blue-100 bg-white p-4">
                <p data-preview-unit class="break-words font-semibold text-slate-800" x-text="previewResponse.unit"></p>
                <dl class="mt-2 grid gap-x-4 gap-y-1 text-xs text-slate-600 sm:grid-cols-[auto_1fr]">
                    <dt>Pemberi tanggapan</dt><dd data-preview-author x-text="previewResponse.author"></dd>
                    <dt>Tanggal pengisian</dt><dd data-preview-date x-text="previewResponse.date"></dd>
                </dl>
                <div id="bahan-tanggapan-preview" data-response-preview-body tabindex="0" aria-label="Isi tanggapan unit kerja"
                    class="mt-3 max-h-64 overflow-y-auto rounded-lg border border-slate-100 p-3 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <x-rich-content content-expression="previewResponse.content" />
                    <p class="mt-3 whitespace-pre-line break-words text-sm text-slate-600" x-text="'Deliverables: ' + (previewResponse.deliverables || '-')"></p>
                </div>
            </article>
        </template>
        <p data-preview-empty x-show="previewResponses.length === 0" class="mt-3 rounded-lg bg-white p-4 text-sm text-slate-500">
            <span x-text="selected.preview?.is_snapshot ? 'Tidak ada isian unit dalam salinan kompilasi ini.' : 'Belum ada tanggapan pada putaran ini.'"></span>
        </p>
        <div data-preview-navigation x-show="previewResponses.length > 1" class="mt-3 flex flex-wrap justify-between gap-3">
            <button type="button" data-preview-previous @click="previousPreview()" :disabled="previewIndex === 0" aria-controls="bahan-tanggapan-preview"
                class="rounded-lg border border-blue-200 bg-white px-4 py-2 text-sm font-semibold text-blue-700 disabled:cursor-not-allowed disabled:opacity-40">Sebelumnya</button>
            <button type="button" data-preview-next @click="nextPreview()" :disabled="previewIndex >= previewResponses.length - 1" aria-controls="bahan-tanggapan-preview"
                class="rounded-lg border border-blue-200 bg-white px-4 py-2 text-sm font-semibold text-blue-700 disabled:cursor-not-allowed disabled:opacity-40">Berikutnya</button>
        </div>
        <template x-if="selected.preview?.late_responses?.length">
            <details data-preview-late class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                <summary class="cursor-pointer font-semibold" x-text="'Tanggapan setelah penutupan (' + selected.preview.late_responses.length + ')'"></summary>
                <p class="mt-2">Kelengkapan ini tidak termasuk bahan keputusan reviu.</p>
                <template x-for="response in selected.preview.late_responses" :key="response.id">
                    <article class="mt-3 rounded-lg bg-white p-3">
                        <p class="font-semibold" x-text="response.unit"></p>
                        <p class="mt-1 text-xs" x-text="response.author + ' · ' + response.after_close_at"></p>
                        <div class="mt-3 max-h-48 overflow-y-auto" tabindex="0" aria-label="Tanggapan setelah penutupan">
                            <x-rich-content content-expression="response.content" />
                        </div>
                    </article>
                </template>
            </details>
        </template>
    </section>
    @if ($showCompilation)
        <section data-compilation-preview aria-labelledby="preview-kompilasi-title" class="rounded-xl border border-slate-200 bg-slate-50 p-4">
            <h3 id="preview-kompilasi-title" class="font-bold text-slate-800">Preview Hasil Kompilasi</h3>
            <template x-if="selected.preview?.compilation">
                <div>
                    <p class="mt-2 break-words text-xs text-slate-600" x-text="selected.preview.compilation.butir_id + ' · ' + selected.preview.compilation.round_label"></p>
                    <dl class="mt-2 grid gap-x-4 gap-y-1 text-xs text-slate-600 sm:grid-cols-[auto_1fr]">
                        <dt>Penyusun kompilasi</dt><dd data-compilation-author x-text="selected.preview.compilation.author"></dd>
                        <dt>Tanggal kompilasi</dt><dd data-compilation-date x-text="selected.preview.compilation.date"></dd>
                    </dl>
                    <div data-compilation-preview-body tabindex="0" aria-label="Isi hasil kompilasi"
                        class="mt-3 max-h-64 overflow-y-auto rounded-lg border border-slate-100 bg-white p-3 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <x-rich-content content-expression="selected.preview.compilation.content" />
                        <p class="mt-3 whitespace-pre-line break-words text-sm text-slate-600" x-text="'Deliverables: ' + (selected.preview.compilation.deliverables || '-')"></p>
                    </div>
                </div>
            </template>
            <p x-show="!selected.preview?.compilation" class="mt-3 text-sm text-slate-500">Belum ada hasil kompilasi pada putaran ini.</p>
        </section>
    @endif
</div>
