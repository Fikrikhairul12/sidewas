import DOMPurify from 'dompurify';

export const SNP_RICH_PREFIX = '<!--snp-rich:v1-->';
const imagePattern = /^\/(snp|ragab|rawas|djsn|eksternal)\/perekaman\/[1-9]\d*\/gambar\/[a-f0-9-]{36}\.(png|jpg)$/;

export function snpButirHtml(value = '', pasted = false) {
    value = String(value ?? '');
    if (!value.startsWith(SNP_RICH_PREFIX)) {
        const escaped = document.createElement('div');
        escaped.textContent = value;
        return escaped.innerHTML.replace(/\n/g, '<br>\n');
    }
    const fragment = DOMPurify.sanitize(value.slice(SNP_RICH_PREFIX.length), {
        ALLOWED_TAGS: ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 'span', 'ul', 'ol', 'li', 'img'],
        ALLOWED_ATTR: ['style', 'src', 'alt', 'data-width', 'start'],
        ALLOW_DATA_ATTR: false,
        RETURN_DOM_FRAGMENT: true,
    });
    fragment.querySelectorAll('*').forEach((element) => {
        let size = element.style.fontSize;
        const alignment = element.style.textAlign;
        if (pasted) {
            if (size.endsWith('pt')) size = `${Math.round(parseFloat(size) * 4 / 3)}px`;
            const marks = [];
            if (element.style.fontWeight === 'bold' || Number(element.style.fontWeight) >= 600) marks.push('strong');
            if (element.style.fontStyle === 'italic') marks.push('em');
            if (element.style.textDecoration.includes('underline')) marks.push('u');
            marks.forEach((tag) => {
                const wrapper = document.createElement(tag);
                wrapper.append(...element.childNodes);
                element.append(wrapper);
            });
        }
        element.removeAttribute('style');
        if (['12px', '14px', '16px', '18px', '20px', '24px'].includes(size)) element.style.fontSize = size;
        if (element.tagName === 'P' && ['left', 'center', 'right', 'justify'].includes(alignment)) element.style.textAlign = alignment;
        if (element.tagName === 'IMG') {
            if (!imagePattern.test(element.getAttribute('src') ?? '')) {
                element.remove();
                return;
            }
            const width = [25, 50, 75, 100].includes(Number(element.dataset.width)) ? Number(element.dataset.width) : 100;
            element.dataset.width = String(width);
            element.style.width = `${width}%`;
            element.style.height = 'auto';
        }
    });
    const container = document.createElement('div');
    container.append(fragment);
    return container.innerHTML;
}

export function snpButirPlain(value = '') {
    if (!String(value ?? '').startsWith(SNP_RICH_PREFIX)) return String(value ?? '');
    const container = document.createElement('div');
    container.innerHTML = snpButirHtml(value).replace(/<br\s*\/?>|<\/(p|li|ul|ol)>/gi, '\n');
    container.querySelectorAll('img').forEach((image) => image.replaceWith(document.createTextNode('[Gambar]')));
    return container.textContent.trim();
}

export function renderSnpButir(element, value) {
    element.classList.toggle('snp-rich-content', String(value ?? '').startsWith(SNP_RICH_PREFIX));
    if (String(value ?? '').startsWith(SNP_RICH_PREFIX)) element.innerHTML = snpButirHtml(value);
    else element.textContent = value || 'Belum ada isi butir.';
}

window.snpButirHtml = snpButirHtml;
window.snpButirPlain = snpButirPlain;
