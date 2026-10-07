const customFields = ['butir_ids[]', 'fields[]', 'tanggapan_unit_kerja_ids[]', 'tindak_lanjut_unit_kerja_ids[]'];

const savedSelection = storageKey => {
    try {
        const saved = JSON.parse(sessionStorage.getItem(storageKey));
        return saved && typeof saved.url === 'string' && Array.isArray(saved.recordIds) ? saved : null;
    } catch {
        return null;
    }
};

const selectedValues = (form, name) => [...form.querySelectorAll(`input[name="${name}"]:checked`)].map(input => input.value);

const restoreCheckboxes = (form, name, values) => {
    if (!Array.isArray(values)) return;
    const selected = new Set(values.map(String));
    form.querySelectorAll(`input[name="${name}"]`).forEach(input => { input.checked = selected.has(input.value); });
};

document.addEventListener('DOMContentLoaded', () => {
    const reportPage = document.querySelector('[data-report-index]');
    const returnLink = document.querySelector('[data-report-return]');
    const module = reportPage?.dataset.reportModule ?? returnLink?.dataset.reportModule;
    if (!['snp', 'ragab', 'rawas', 'djsn', 'eksternal'].includes(module)) return;
    const storageKey = `sidewas:${module}-report-navigation`;
    if (returnLink) {
        const saved = savedSelection(storageKey);
        if (saved) {
            try {
                const index = new URL(returnLink.dataset.reportReturn, location.href);
                const destination = new URL(saved.url, location.href);
                if (destination.origin === location.origin && destination.pathname === index.pathname) {
                    returnLink.href = destination.href;
                }
            } catch {
                // Keep the report link when the saved address is unavailable.
            }
        }
    }

    if (!reportPage) return;
    const reportForm = reportPage.querySelector('#reportForm');
    const customForm = reportPage.querySelector('#customReportForm');
    let customSelection = null;
    const closeReportModals = () => {
        reportPage.querySelectorAll('#reportFormatModal, #customReportModal').forEach(modal => {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        });
    };
    const restoreSelection = () => {
        const saved = savedSelection(storageKey);
        if (saved?.url === location.pathname + location.search) {
            restoreCheckboxes(reportForm, 'record_ids[]', saved.recordIds);
            reportForm.querySelector('.record-report-checkbox')?.dispatchEvent(new Event('change'));
            customSelection = saved.custom;
        }
        closeReportModals();
    };
    restoreSelection();
    window.addEventListener('pageshow', restoreSelection);

    document.getElementById('openCustomReportModalBtn')?.addEventListener('click', () => {
        queueMicrotask(() => {
            const saved = savedSelection(storageKey);
            const currentIds = selectedValues(reportForm, 'record_ids[]').sort();
            if (!customSelection || JSON.stringify(currentIds) !== JSON.stringify(saved?.recordIds.map(String).sort())) return;
            restoreCheckboxes(customForm, 'butir_ids[]', customSelection['butir_ids[]']);
            customForm.querySelector('input[name="butir_ids[]"]')?.dispatchEvent(new Event('change'));
            customFields.slice(1).forEach(name => restoreCheckboxes(customForm, name, customSelection[name]));
            customSelection = null;
        });
    });

    document.addEventListener('submit', event => {
        if (event.defaultPrevented || !event.submitter?.matches('[data-report-pdf-preview]')) return;
        if (event.target !== reportForm && event.target !== customForm) return;
        const saved = {
            url: location.pathname + location.search,
            recordIds: selectedValues(reportForm, 'record_ids[]'),
            custom: event.target === customForm
                ? Object.fromEntries(customFields.map(name => [name, selectedValues(customForm, name)]))
                : null,
        };
        try {
            sessionStorage.setItem(storageKey, JSON.stringify(saved));
        } catch {
            // Navigation still works when browser storage is unavailable.
        }
        closeReportModals();
    });
});
