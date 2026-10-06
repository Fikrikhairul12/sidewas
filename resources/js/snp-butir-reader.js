import { renderSnpButir } from './snp-butir-content';

export function readSnpButir(detail) {
    window.dispatchEvent(new CustomEvent('snp-read-butir', { detail }));
}

export function initializeSnpButirReader() {
    const dialog = document.getElementById('snpButirReader');

    if (!dialog || dialog.dataset.initialized) {
        return;
    }

    dialog.dataset.initialized = 'true';
    const content = dialog.querySelector('[data-snp-reader-content]');
    const scrollArea = dialog.querySelector('[data-snp-reader-scroll]');
    const title = dialog.querySelector('#snpButirReaderTitle');
    const context = dialog.querySelector('#snpButirReaderContext');
    const navigation = dialog.querySelector('[data-snp-reader-navigation]');
    const select = dialog.querySelector('select');
    const previous = dialog.querySelector('[data-snp-reader-previous]');
    const next = dialog.querySelector('[data-snp-reader-next]');
    const position = dialog.querySelector('[data-snp-reader-position]');
    let items = [];
    let selectedIndex = 0;
    let trigger = null;
    let previousOverflow = '';
    let label = 'Isi Butir SNP';
    let format = 'rich';

    const render = () => {
        const item = items[selectedIndex];
        title.textContent = item.id || label;
        if (format === 'plain') {
            content.classList.remove('snp-rich-content');
            content.textContent = item.content || 'Belum ada isi butir.';
        } else {
            renderSnpButir(content, item.content);
        }
        select.value = String(selectedIndex);
        previous.disabled = selectedIndex === 0;
        next.disabled = selectedIndex === items.length - 1;
        navigation.hidden = items.length < 2;
        position.textContent = items.length > 1 ? `Butir ${selectedIndex + 1} dari ${items.length}` : 'Isi lengkap';
        scrollArea.scrollTop = 0;
    };

    window.addEventListener('snp-read-butir', (event) => {
        const detail = event.detail ?? {};
        label = detail.label || 'Isi Butir SNP';
        format = detail.format || 'rich';
        const candidates = Array.isArray(detail.items) && detail.items.length ? detail.items : [detail];
        items = candidates.map((item) => ({
            id: String(item.id_butir_snp ?? item.id_butir_ragab ?? item.id_butir_rawas ?? item.id_butir_djsn ?? item.id_butir_eksternal ?? item.id ?? ''),
            content: String(item.butir_snp ?? item.keputusan_ragab ?? item.keputusan_rawas ?? item.butir_djsn ?? item.keputusan_eksternal ?? item.butir_ragab ?? item.butir_rawas ?? item.butir_eksternal ?? item.content ?? ''),
        }));
        selectedIndex = Math.max(0, items.findIndex((item) => item.id === String(detail.id ?? '')));
        context.textContent = detail.context || label;
        select.replaceChildren(...items.map((item, index) => new Option(item.id || `Butir ${index + 1}`, index)));
        render();

        if (!dialog.open) {
            trigger = document.activeElement;
            previousOverflow = document.body.style.overflow;
            document.body.style.overflow = 'hidden';
            dialog.showModal();
        }
    });

    select.addEventListener('change', () => {
        selectedIndex = Number(select.value);
        render();
    });
    previous.addEventListener('click', () => {
        selectedIndex = Math.max(0, selectedIndex - 1);
        render();
    });
    next.addEventListener('click', () => {
        selectedIndex = Math.min(items.length - 1, selectedIndex + 1);
        render();
    });
    dialog.querySelector('[data-snp-reader-close]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', (event) => event.stopPropagation());
    dialog.addEventListener('keydown', (event) => {
        event.stopPropagation();
        if (event.key !== 'Tab') {
            return;
        }

        const focusable = [...dialog.querySelectorAll('button, select, [tabindex="0"]')]
            .filter((element) => !element.disabled && element.getClientRects().length);
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });
    dialog.addEventListener('close', () => {
        document.body.style.overflow = previousOverflow;
        if (trigger?.isConnected) {
            trigger.focus({ preventScroll: true });
        }
    });
}

document.addEventListener('DOMContentLoaded', initializeSnpButirReader);
