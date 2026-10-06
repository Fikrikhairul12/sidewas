<dialog id="snpButirReader" class="snp-butir-reader" aria-labelledby="snpButirReaderTitle" aria-describedby="snpButirReaderContext">
    <div class="snp-butir-reader__frame">
        <header class="snp-butir-reader__header">
            <div class="min-w-0">
                <p id="snpButirReaderContext" class="text-sm font-semibold text-sidewas-blue">Isi Butir SNP</p>
                <h2 id="snpButirReaderTitle" class="mt-1 break-words text-xl font-bold text-slate-800">Isi Butir SNP</h2>
            </div>
            <button type="button" data-snp-reader-close autofocus
                class="shrink-0 rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                Tutup
            </button>
        </header>
        <nav class="snp-butir-reader__navigation" data-snp-reader-navigation aria-label="Navigasi butir" hidden>
            <label for="snpButirReaderSelect" class="text-sm font-semibold text-slate-600">Pilih butir</label>
            <select id="snpButirReaderSelect" class="min-w-0 rounded-lg border-slate-300 text-sm"></select>
            <div class="flex gap-2">
                <button type="button" data-snp-reader-previous class="snp-butir-reader__nav-button">Sebelumnya</button>
                <button type="button" data-snp-reader-next class="snp-butir-reader__nav-button">Berikutnya</button>
            </div>
        </nav>
        <div class="snp-butir-reader__body" data-snp-reader-scroll tabindex="0" aria-label="Isi butir lengkap">
            <article class="snp-butir-reader__content" data-snp-reader-content></article>
        </div>
        <footer class="snp-butir-reader__footer">
            <span data-snp-reader-position aria-live="polite">Isi lengkap</span>
            <span>Tekan Esc untuk kembali</span>
        </footer>
    </div>
</dialog>
