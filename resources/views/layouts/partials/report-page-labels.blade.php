<script>
    window.butirReportReady = false;
    Promise.all([document.fonts.ready, ...Array.from(document.images, image => image.decode().catch(() => {}))]).then(() => {
        const previewZoom = document.documentElement.style.zoom;
        document.documentElement.style.zoom = '100%';
        const table = document.querySelector('table');
        const headerHeight = table?.tHead?.getBoundingClientRect().height ?? 0;
        const pageHeight = 8.5 * 96 - 22 * 96 / 25.4 - 16;
        const lastPages = new Map();
        let page = 0;
        let usedHeight = headerHeight;
        for (const row of table?.tBodies[0]?.rows ?? []) {
            const height = row.getBoundingClientRect().height;
            if (usedHeight + height > pageHeight - 2 && usedHeight > headerHeight) {
                page++;
                usedHeight = headerHeight;
                row.style.breakBefore = 'page';
            }
            row.dataset.reportPage = page;
            usedHeight += height;
            for (const label of row.querySelectorAll('[data-report-label-id]')) {
                const id = label.dataset.reportLabelId;
                const previous = lastPages.get(id);
                const continued = previous !== undefined && previous !== page;
                label.style.visibility = continued || (previous === undefined && label.dataset.reportShowFirst === '1') ? 'visible' : 'hidden';
                label.querySelector('.report-continuation').style.visibility = continued ? 'visible' : 'hidden';
                lastPages.set(id, page);
            }
        }
        document.documentElement.style.zoom = previewZoom;
        window.butirReportReady = true;
    });
</script>
