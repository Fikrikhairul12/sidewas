import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import { TextStyle, FontSize } from '@tiptap/extension-text-style';
import TextAlign from '@tiptap/extension-text-align';
import Image from '@tiptap/extension-image';
import { SNP_RICH_PREFIX, snpButirHtml } from './snp-butir-content';

const ButirImage = Image.extend({
    addAttributes() {
        return {
            ...this.parent?.(),
            width: {
                default: 100,
                parseHTML: (element) => Number(element.dataset.width) || 100,
                renderHTML: ({ width }) => ({ 'data-width': width, style: `width:${width}%;height:auto;` }),
            },
        };
    },
}).configure({ inline: true, allowBase64: false });

window.snpButirEditor = function () {
    let editor;
    let canvas;
    let currentKey;
    let currentRecord;
    let originalValue = '';
    let form;
    let submitHandler;
    let anchor;
    let uploadVersion = 0;
    let savedSelection;

    return {
        value: '', ready: false, busy: false, error: '', expanded: false, revision: 0,
        init() {
            const root = this.$el;
            this.$nextTick(() => {
                form = root.closest('form');
                canvas = root.querySelector('.snp-editor__canvas');
                submitHandler = (event) => {
                    if (this.busy || !editor || (!editor.getText().trim() && !editor.getHTML().includes('<img'))) {
                        event.preventDefault();
                        this.error = this.busy ? 'Tunggu sampai gambar selesai diunggah.' : 'Isi Butir SNP wajib diisi.';
                        editor?.commands.focus();
                    }
                };
                form?.addEventListener('submit', submitHandler);
                this.ready = true;
            });
        },
        sync(key, content, record) {
            if (!this.ready) return;
            if (currentKey === String(key)) return;
            uploadVersion++;
            this.busy = false;
            this.error = '';
            currentKey = String(key);
            currentRecord = record;
            originalValue = String(content ?? '');
            savedSelection = undefined;
            this.value = originalValue;
            editor?.destroy();
            editor = new Editor({
                element: canvas,
                extensions: [
                    StarterKit.configure({ heading: false, blockquote: false, code: false, codeBlock: false, horizontalRule: false, link: false, strike: false }),
                    TextStyle, FontSize, TextAlign.configure({ types: ['paragraph'] }), ButirImage,
                ],
                content: snpButirHtml(originalValue) || '<p></p>',
                editorProps: {
                    attributes: { class: 'snp-rich-content', role: 'textbox', 'aria-label': 'Isi Butir SNP', 'aria-multiline': 'true' },
                    transformPastedHTML: (html) => snpButirHtml(SNP_RICH_PREFIX + html, true),
                    handlePaste: (_view, event) => {
                        if (event.clipboardData?.files.length) {
                            this.upload(event.clipboardData.files[0]);
                            return true;
                        }
                        return false;
                    },
                    handleDrop: (_view, event) => {
                        if (event.dataTransfer?.files.length) {
                            this.upload(event.dataTransfer.files[0]);
                            return true;
                        }
                        return false;
                    },
                },
                onUpdate: () => {
                    this.value = SNP_RICH_PREFIX + editor.getHTML();
                    this.$dispatch('snp-editor-change', this.value);
                    this.error = '';
                },
                onTransaction: () => { this.revision++; },
            });
        },
        active(name, attributes = {}) {
            this.revision;
            return !!editor?.isActive(name, attributes);
        },
        fontSize() {
            this.revision;
            return editor?.getAttributes('textStyle').fontSize || '16px';
        },
        command(command, argument) {
            if (!editor) return;
            if (command === 'clear') editor.chain().focus().unsetAllMarks().clearNodes().unsetTextAlign().run();
            else editor.chain().focus()[command](argument).run();
        },
        imageWidth(width) { editor?.chain().focus().updateAttributes('image', { width: Number(width) }).run(); },
        chooseImage() {
            savedSelection = editor?.state.selection.from;
            this.$refs.imageInput.click();
        },
        async upload(file) {
            if (!file || this.busy) return;
            if ((editor?.getHTML().match(/<img /g) ?? []).length >= 20) {
                this.error = 'Maksimal 20 gambar dalam satu butir.';
                return;
            }
            if (!['image/jpeg', 'image/png'].includes(file.type) || file.size > 2 * 1024 * 1024) {
                this.error = 'Gunakan JPG atau PNG, maksimal 2 MB per gambar.';
                return;
            }
            if (!currentRecord) { this.error = 'Pilih surat terlebih dahulu.'; return; }
            const version = uploadVersion;
            const position = savedSelection ?? editor.state.selection.from;
            savedSelection = undefined;
            this.busy = true;
            this.error = '';
            editor.setEditable(false, false);
            try {
                const data = new FormData();
                data.append('image', file);
                const response = await fetch(`/snp/perekaman/${currentRecord}/gambar`, {
                    method: 'POST', body: data, credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '' },
                });
                const result = await response.json();
                if (!response.ok) throw new Error(result.errors?.image?.[0] || result.message || 'Gambar gagal diunggah.');
                if (version !== uploadVersion) return;
                editor.chain().focus().setTextSelection(Math.min(position, editor.state.doc.content.size)).setImage({ src: result.url, alt: file.name }).run();
            } catch (error) {
                if (version === uploadVersion) this.error = error.message || 'Gambar gagal diunggah. Silakan coba lagi.';
            } finally {
                if (version === uploadVersion) {
                    this.busy = false;
                    editor.setEditable(true, false);
                    this.$refs.imageInput.value = '';
                }
            }
        },
        enlarge() {
            if (this.expanded) { this.$refs.dialog.close(); return; }
            editor?.unmount();
            anchor = document.createComment('editor-position');
            window.Alpine.mutateDom(() => {
                this.$refs.surface.before(anchor);
                this.$refs.dialog.append(this.$refs.surface);
            });
            this.expanded = true;
            this.$refs.dialog.showModal();
            editor?.mount(canvas);
            editor?.view.focus();
        },
        restore() {
            if (!anchor) return;
            editor?.unmount();
            window.Alpine.mutateDom(() => { anchor.replaceWith(this.$refs.surface); anchor = null; });
            this.expanded = false;
            editor?.mount(canvas);
            editor?.view.focus();
        },
        destroy() {
            uploadVersion++;
            this.$refs.dialog?.close();
            form?.removeEventListener('submit', submitHandler);
            editor?.destroy();
        },
    };
};
