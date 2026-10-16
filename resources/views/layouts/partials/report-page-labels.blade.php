<script>
    window.butirReportReady = false;
    Promise.all([document.fonts.ready, ...Array.from(document.images, image => image.decode().catch(() => {}))]).then(() => {
        const lastPages = new Map();
        for (const row of document.querySelectorAll('.report-page-table tbody tr')) {
            const page = row.dataset.reportPage;
            for (const label of row.querySelectorAll('[data-snp-butir-label]')) {
                const id = label.dataset.snpButirLabel;
                const previous = lastPages.get(id);
                const continued = previous !== undefined && previous !== page;
                label.style.visibility = continued || (previous === undefined && label.dataset.snpShowFirst === '1') ? 'visible' : 'hidden';
                label.querySelector('[data-snp-continuation]').style.visibility = continued ? 'visible' : 'hidden';
                lastPages.set(id, page);
            }
        }
        window.butirReportReady = true;
    });
</script>
