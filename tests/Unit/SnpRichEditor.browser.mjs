import assert from 'node:assert/strict';
import { existsSync, readFileSync } from 'node:fs';
import { createServer } from 'node:http';
import { resolve, sep } from 'node:path';
import puppeteer from 'puppeteer';

let input = '';
for await (const chunk of process.stdin) {
    input += chunk;
}
const fixture = JSON.parse(input);
const field = fixture.field || 'butir_snp';
const imagePrefix = `/${fixture.module || 'snp'}/perekaman/1/gambar`;
const buildRoot = resolve('public/build');
let uploads = 0;
const server = createServer((request, response) => {
    if (request.url === '/') {
        response.setHeader('Content-Type', 'text/html; charset=utf-8');
        response.end(`<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="/build/${fixture.css}"><script type="module" src="/build/${fixture.js}"></script></head><body>${fixture.html}</body></html>`);
        return;
    }
    if (request.url === imagePrefix && request.method === 'POST') {
        uploads++;
        request.resume();
        request.on('end', () => {
            response.setHeader('Content-Type', 'application/json');
            if (uploads > 1) response.writeHead(422).end(JSON.stringify({ errors: { image: ['Unggah uji ditolak.'] } }));
            else response.writeHead(201).end(JSON.stringify({ url: imagePrefix + '/12345678-1234-1234-1234-123456789abc.png' }));
        });
        return;
    }
    if (request.url.startsWith(imagePrefix + '/')) {
        response.setHeader('Content-Type', 'image/png');
        response.end(Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jJ1kAAAAASUVORK5CYII=', 'base64'));
        return;
    }
    const file = resolve(buildRoot, decodeURIComponent(request.url).replace(/^\/build\//, ''));
    if (!file.startsWith(buildRoot + sep) || !existsSync(file)) {
        response.writeHead(404).end();
        return;
    }
    response.setHeader('Content-Type', file.endsWith('.css') ? 'text/css' : 'application/javascript');
    response.end(readFileSync(file));
});
await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
const executablePath = [
    process.env.PUPPETEER_EXECUTABLE_PATH,
    await puppeteer.executablePath(),
    'C:/Program Files/Google/Chrome/Application/chrome.exe',
    'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',
    '/usr/bin/chromium',
    '/usr/bin/google-chrome',
].find(candidate => candidate && existsSync(candidate));
let browser;
try {
    browser = await puppeteer.launch({ headless: true, executablePath });
    const page = await browser.newPage();
    const errors = [];
    const assertDraft = async (stage) => {
        const result = await page.waitForFunction((field) => {
            const node = document.querySelector('.tiptap');
            if (!node?.editor) return false;
            return { text: node.textContent, html: node.editor.getHTML(), submitted: document.querySelector(`[name="${field}"]`).value };
        }, { timeout: 5000 }, field).catch(error => { throw new Error(`${stage}: ${error.message}`); });
        const state = await result.jsonValue();
        assert.ok(state.text.includes('Teks lama <literal> Draf baru'), `${stage}: ${JSON.stringify(state)}`);
        assert.equal(state.submitted, '<!--snp-rich:v1-->' + state.html, `${stage}: submitted content differs from editor`);
    };
    const controlKey = async (key) => {
        await page.keyboard.down('Control');
        await page.keyboard.press(key);
        await page.keyboard.up('Control');
        if (key === 'End') {
            await page.waitForFunction(() => document.getSelection().isCollapsed && document.querySelector('.tiptap').editor.state.selection.empty, { timeout: 2000 });
        }
    };
    page.on('pageerror', error => errors.push(error.message));
    await page.setViewport({ width: 1366, height: 900 });
    await page.goto(`http://127.0.0.1:${server.address().port}`, { waitUntil: 'networkidle0' });
    await page.waitForSelector('.tiptap', { timeout: 5000 }).catch(async error => { throw new Error(`${error.message}\n${JSON.stringify(errors)}\n${await page.$eval('.snp-editor', node => node.outerHTML)}`); });
    assert.equal(await page.$eval('.snp-editor__dialog', node => node.getAttribute('aria-label')), `Perbesar editor ${await page.$eval('.tiptap', node => node.getAttribute('aria-label'))}`);
    if (process.env.SNP_READER_SCREENSHOTS) await page.screenshot({ path: resolve(process.env.SNP_READER_SCREENSHOTS, 'snp-editor-desktop.png') });
    assert.equal(await page.$eval('.tiptap p', node => node.style.textAlign), 'center');
    assert.equal(await page.$eval('.tiptap img[src]', node => node.dataset.width), '50', await page.$eval('.tiptap', node => node.outerHTML));
    assert.equal(await page.$eval('.tiptap strong', node => node.textContent), 'Isi awal');
    await page.click('.tiptap img[src]');
    await page.click('.snp-editor__image-toolbar button:nth-of-type(3)');
    assert.equal(await page.$eval('.tiptap img[src]', node => node.dataset.width), '75');
    let value = await page.$eval(`[name="${field}"]`, node => node.value);
    assert.ok(value.includes('data-width="75"'));
    await page.click('#switch');
    assert.equal(await page.$eval('.tiptap', node => node.textContent), 'Teks lama <literal>');
    assert.equal(await page.$eval('.tiptap', node => node.querySelectorAll('literal').length), 0);
    await page.click('.tiptap');
    await controlKey('End');
    await page.keyboard.type(' Draf baru');
    await page.click('#switch');
    assert.equal(await page.$eval('.tiptap img[src]', node => node.dataset.width), '75');
    await page.click('#switch');
    assert.ok((await page.$eval('.tiptap', node => node.textContent)).endsWith('Draf baru'));
    await page.click('.tiptap');
    await controlKey('KeyA');
    await page.click('[aria-label="Tebal"]');
    await page.select('[aria-label="Ukuran huruf"]', '20px');
    assert.equal(await page.$eval('.tiptap strong', node => node.textContent), 'Teks lama <literal> Draf baru');
    assert.equal(await page.$eval('.tiptap span', node => node.style.fontSize), '20px');
    await page.click('[aria-label="Miring"]');
    await page.click('[aria-label="Garis bawah"]');
    await page.click('[aria-label="Daftar poin"]');
    assert.ok(await page.$('.tiptap ul li em u'));
    await page.click('[aria-label="Daftar nomor"]');
    assert.ok(await page.$('.tiptap ol li'));
    await page.click('.snp-editor__toolbar button:nth-of-type(8)');
    assert.equal(await page.$eval('.tiptap p', node => node.style.textAlign), 'right');
    await page.click('[title="Urungkan (Ctrl+Z)"]');
    assert.notEqual(await page.$eval('.tiptap p', node => node.style.textAlign), 'right');
    await page.click('[title="Ulangi (Ctrl+Shift+Z)"]');
    assert.equal(await page.$eval('.tiptap p', node => node.style.textAlign), 'right');
    await page.click('.snp-editor__toolbar button:last-child');
    await page.waitForFunction(() => document.querySelector('.snp-editor__dialog').open);
    if (process.env.SNP_READER_SCREENSHOTS) await page.screenshot({ path: resolve(process.env.SNP_READER_SCREENSHOTS, 'snp-editor-expanded.png') });
    assert.ok((await page.$eval('.tiptap', node => node.textContent)).includes('Draf baru'), await page.$eval('.snp-editor', node => node.outerHTML));
    await page.click('.tiptap');
    await controlKey('End');
    await page.keyboard.type(' Saat diperbesar');
    assert.ok((await page.$eval('.tiptap', node => node.textContent)).includes('Draf baru'), await page.$eval('.tiptap', node => node.outerHTML));
    await page.keyboard.press('Escape');
    await page.waitForFunction(() => !document.querySelector('.snp-editor__dialog').open);
    await assertDraft('After restoring editor');
    assert.ok((await page.$eval(`[name="${field}"]`, node => node.value)).includes('Saat diperbesar'));
    await page.click('#richPreview button');
    await page.waitForFunction(() => document.getElementById('snpButirReader').open);
    assert.equal(await page.$eval('[data-snp-reader-content]', node => !!node.querySelector('strong')?.textContent.includes('Draf baru')), true, await page.$eval('[data-snp-reader-content]', node => node.innerHTML));
    await page.keyboard.press('Escape');
    await assertDraft('After closing reader');
    await page.click('.tiptap');
    await controlKey('End');
    await page.$eval('.tiptap', node => {
        const transfer = new DataTransfer();
        transfer.setData('text/html', '<p><span style="font-size:12pt;font-weight:bold;font-style:italic">Salinan Word</span><script>window.injected=true</script><img src="https://evil.test/image.png"></p>');
        node.dispatchEvent(new ClipboardEvent('paste', { clipboardData: transfer, bubbles: true, cancelable: true }));
    });
    assert.ok((await page.$eval('.tiptap', node => node.textContent)).includes('Salinan Word'));
    await assertDraft('After pasting Word content');
    assert.equal(await page.evaluate(() => window.injected), undefined);
    assert.equal(await page.$('.tiptap img[src^="https:"]'), null);
    const fileInput = await page.$('input[type="file"]');
    await fileInput.uploadFile(fixture.uploadPath);
    await page.waitForFunction(() => document.querySelectorAll('.tiptap img[src]').length === 1, { timeout: 5000 }).catch(async error => { throw new Error(`${error.message}\n${await page.$eval('.snp-editor__error', node => node.textContent)}\n${JSON.stringify(errors)}`); });
    assert.equal(uploads, 1);
    await assertDraft('After uploading image');
    assert.ok((await page.$eval(`[name="${field}"]`, node => node.value)).includes(imagePrefix + '/'));
    await fileInput.uploadFile(fixture.uploadPath);
    await page.waitForFunction(() => document.querySelector('.snp-editor__error').textContent.includes('Unggah uji ditolak.'));
    assert.equal(await page.$$eval('.tiptap img[src]', nodes => nodes.length), 1);
    assert.equal(await page.$eval('.tiptap', node => node.getAttribute('contenteditable')), 'true');
    await page.setViewport({ width: 375, height: 812 });
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
    if (process.env.SNP_READER_SCREENSHOTS) await page.screenshot({ path: resolve(process.env.SNP_READER_SCREENSHOTS, 'snp-editor-mobile.png') });
    assert.deepEqual(errors, []);
    console.log('Rich editor, per-butir drafts, image sizing, toolbar, reader and mobile layout passed.');
} finally {
    await browser?.close();
    await new Promise(resolve => server.close(resolve));
}
