export function produkHukumReport(config) {
    let searchRequest = 0;
    return {
        mode: 'status',
        status: 'semua',
        format: 'pdf',
        keyword: '',
        selected: {},
        selectedOnly: false,
        products: [],
        page: 1,
        lastPage: 1,
        total: 0,
        loading: false,
        downloading: false,
        error: '',
        searchError: '',
        success: '',
        previousOverflow: '',
        open() {
            if (this.$refs.dialog.open) { return; }
            this.previousOverflow = document.body.style.overflow;
            document.body.style.overflow = 'hidden';
            this.$refs.dialog.showModal();
        },
        close() { this.$refs.dialog.close(); },
        closed() { document.body.style.overflow = this.previousOverflow; },
        get selectedProducts() { return Object.values(this.selected); },
        get displayedProducts() { return this.selectedOnly ? this.selectedProducts : this.products; },
        get count() {
            if (this.mode === 'manual') { return this.selectedProducts.length; }
            return this.status === 'semua'
                ? Object.values(config.counts).reduce((sum, value) => sum + value, 0)
                : config.counts[this.status] || 0;
        },
        async changeMode(mode) {
            this.mode = mode;
            this.error = '';
            this.success = '';
            if (mode === 'manual' && this.products.length === 0) { await this.search(); }
        },
        toggle(product) {
            const selected = { ...this.selected };
            if (selected[product.id]) { delete selected[product.id]; }
            else { selected[product.id] = product; }
            this.selected = selected;
            this.error = '';
            this.success = '';
        },
        selectPage() {
            this.selected = { ...this.selected, ...Object.fromEntries(this.products.map(product => [product.id, product])) };
            this.error = '';
        },
        statusLabel(value) {
            return { berlaku: 'Berlaku', tidak_berlaku: 'Tidak Berlaku', draft: 'Draf' }[value] || value;
        },
        async search(page = 1) {
            const request = ++searchRequest;
            this.loading = true;
            this.searchError = '';
            try {
                const url = new URL(config.optionsUrl, window.location.origin);
                url.searchParams.set('keyword', this.keyword.trim());
                url.searchParams.set('page', page);
                const response = await fetch(url, { headers: { Accept: 'application/json' } });
                if (!response.ok) { throw new Error('Daftar peraturan gagal dimuat. Silakan coba lagi.'); }
                const result = await response.json();
                if (request !== searchRequest) { return; }
                this.products = result.data;
                this.page = result.current_page;
                this.lastPage = result.last_page;
                this.total = result.total;
            } catch (error) {
                if (request === searchRequest) {
                    this.products = [];
                    this.searchError = error.message;
                }
            } finally {
                if (request === searchRequest) { this.loading = false; }
            }
        },
        async download() {
            if (this.downloading || this.count === 0) { return; }
            this.downloading = true;
            this.error = '';
            this.success = '';
            const payload = { mode: this.mode, format: this.format };
            if (this.mode === 'status') { payload.status = this.status; }
            else { payload.product_ids = this.selectedProducts.map(product => product.id); }
            try {
                const response = await fetch(config.downloadUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': config.csrf },
                    body: JSON.stringify(payload),
                });
                if (!response.ok) {
                    const result = await response.json().catch(() => ({}));
                    throw new Error(Object.values(result.errors || {}).flat()[0]
                        || (response.status === 419 ? 'Sesi berakhir. Muat ulang halaman lalu coba kembali.' : 'Rekap gagal dibuat. Silakan coba lagi.'));
                }
                const expectedType = payload.format === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
                if (!response.headers.get('Content-Type')?.includes(expectedType)) {
                    throw new Error('Sesi berakhir. Muat ulang halaman lalu coba kembali.');
                }
                const url = URL.createObjectURL(await response.blob());
                const link = document.createElement('a');
                link.href = url;
                link.download = response.headers.get('Content-Disposition')?.match(/filename="?([^";]+)"?/)?.[1]
                    || `Rekap_Produk_Hukum.${payload.format}`;
                document.body.appendChild(link);
                link.click();
                link.remove();
                setTimeout(() => URL.revokeObjectURL(url), 30000);
                this.success = payload.format === 'pdf' ? 'PDF berhasil diunduh dan siap dicetak.' : 'Excel berhasil diunduh.';
            } catch (error) {
                this.error = error.message;
            } finally {
                this.downloading = false;
            }
        },
    };
}
