export function reviuBahanPage(initialSelected = {}) {
    return {
        openModal: false,
        selected: initialSelected,
        previewIndex: 0,
        get previewResponses() {
            return this.selected.preview?.responses ?? [];
        },
        get previewResponse() {
            return this.previewResponses[this.previewIndex] ?? null;
        },
        get previewCounter() {
            return this.previewResponses.length ? `${this.selected.preview?.kind_label ?? 'Tanggapan'} ${this.previewIndex + 1} dari ${this.previewResponses.length}` : '';
        },
        openMaterialModal(payload) {
            this.selected = payload;
            this.previewIndex = 0;
            this.openModal = true;
            this.$nextTick(() => {
                this.$root.querySelectorAll('input[type="file"]').forEach(input => { input.value = ''; });
                this.resetPreviewScroll();
            });
        },
        previousPreview() {
            if (this.previewIndex > 0) this.previewIndex--;
            this.resetPreviewScroll();
        },
        nextPreview() {
            if (this.previewIndex < this.previewResponses.length - 1) this.previewIndex++;
            this.resetPreviewScroll();
        },
        resetPreviewScroll() {
            this.$nextTick(() => {
                this.$root.querySelector('[data-response-preview-body]')?.scrollTo({ top: 0 });
            });
        },
    };
}
