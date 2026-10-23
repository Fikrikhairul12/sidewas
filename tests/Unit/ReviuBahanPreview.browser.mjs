import assert from 'node:assert/strict';
import { existsSync, readFileSync } from 'node:fs';
import { createServer } from 'node:http';
import { resolve, sep } from 'node:path';
import puppeteer from 'puppeteer';

let input = '';
for await (const chunk of process.stdin) input += chunk;
const fixture = JSON.parse(input);
const buildRoot = resolve('public/build');
const server = createServer((request, response) => {
    const index = /^\/(\d+)$/.exec(request.url)?.[1];
    if (index !== undefined) {
        response.setHeader('Content-Type', 'text/html; charset=utf-8');
        response.end(fixture.pages[Number(index)].html.replace('</head>', `<link rel="stylesheet" href="/build/${fixture.css}"><script type="module" src="/build/${fixture.js}"></script></head>`));
        return;
    }
    if (fixture.images[request.url]) {
        response.setHeader('Content-Type', 'image/png');
        response.end(Buffer.from(fixture.images[request.url], 'base64'));
        return;
    }
    const path = resolve(buildRoot, decodeURIComponent(request.url).replace(/^\/build\//, ''));
    if (!path.startsWith(buildRoot + sep) || !existsSync(path)) return response.writeHead(404).end();
    response.setHeader('Content-Type', path.endsWith('.css') ? 'text/css' : 'application/javascript');
    response.end(readFileSync(path));
});
await new Promise(done => server.listen(0, '127.0.0.1', done));
const executablePath = [process.env.PUPPETEER_EXECUTABLE_PATH, await puppeteer.executablePath(), 'C:/Program Files/Google/Chrome/Application/chrome.exe', 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', '/usr/bin/chromium'].find(path => path && existsSync(path));
let browser;
try {
    browser = await puppeteer.launch({ headless: true, executablePath });
    const page = await browser.newPage();
    page.setDefaultTimeout(7000);
    await page.setViewport({ width: 1366, height: 1000 });
    const errors = [];
    const writes = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.setRequestInterception(true);
    page.on('request', request => {
        if (request.method() !== 'GET') writes.push(request.method() + ' ' + request.url());
        if (request.url().startsWith(`http://127.0.0.1:${server.address().port}/`) || request.url().startsWith('data:')) request.continue();
        else request.abort();
    });
    for (const [index, data] of fixture.pages.entries()) {
        const responseLabel = data.kindLabel ?? 'Tanggapan';
        await page.goto(`http://127.0.0.1:${server.address().port}/${index}`, { waitUntil: 'networkidle0' });
        await page.waitForFunction(() => document.querySelector('[data-workflow-page]')?._x_dataStack);
        await page.click(`[data-${data.kind}-putaran]`);
        await page.waitForSelector('[role="dialog"]', { visible: true });
        await page.waitForFunction(() => document.querySelector('.tiptap') && !document.querySelector('[data-snp-editor]')._x_dataStack[0].busy);
        const form = `[data-${data.kind}-form]`;
        const field = data.kind === 'kompilasi' ? 'hasil_kompilasi' : 'hasil_review';
        const selectedContext = await page.$eval('[data-workflow-page]', node => {
            const selected = window.Alpine.$data(node).selected;
            return { butirId: selected.butir_id, previewButirId: selected.preview.butir_id, action: selected.action };
        });
        assert.equal(selectedContext.butirId, data.butirId, JSON.stringify(selectedContext));
        assert.ok(await page.$eval('[data-workflow-context]', (node, id) => node.textContent.includes(id), data.butirId), JSON.stringify(await page.$eval('[data-workflow-context]', node => ({ text: node.textContent, expression: node.getAttribute('x-text'), selected: window.Alpine.$data(node).selected.butir_id }))));
        assert.ok((await page.$eval(form, node => node.action)).endsWith(data.action), JSON.stringify(selectedContext));
        assert.ok(await page.$eval('[data-bahan-preview]', (node, selector) => !!(node.compareDocumentPosition(document.querySelector(selector)) & Node.DOCUMENT_POSITION_FOLLOWING), form));
        assert.equal((await page.$$('[data-bahan-preview] input, [data-bahan-preview] textarea, [data-bahan-preview] [contenteditable="true"]')).length, 0);
        assert.ok(await page.$eval('[data-preview-context]', (node, id) => node.textContent.includes(id), data.butirId));
        assert.ok(await page.$eval('[data-preview-context]', (node, label) => node.textContent.includes(label), data.roundLabel ?? 'Putaran awal'));
        if (data.count === 0) {
            await page.waitForSelector('[data-preview-empty]', { visible: true });
            await page.waitForSelector('[data-preview-navigation]', { hidden: true });
        } else {
            await page.waitForFunction(counter => document.querySelector('[data-preview-counter]').textContent === counter, {}, `${responseLabel} 1 dari ${data.count}`);
            assert.equal(await page.$eval('[data-preview-unit]', node => node.textContent), 'A - Unit A');
            assert.equal(await page.$eval('[data-preview-author]', node => node.textContent), 'Pengisi A');
            assert.equal(await page.$eval('[data-preview-date]', node => node.textContent), data.dates[0]);
            if (data.count === 1) {
                await page.waitForSelector('[data-preview-navigation]', { hidden: true });
                assert.ok(await page.$eval('[data-response-preview-body]', node => node.textContent.includes('<b>literal</b>')));
                assert.equal((await page.$$('[data-response-preview-body] b')).length, 0);
            }
        }
        if (data.kind === 'reviu') {
            assert.ok(await page.$eval('[data-compilation-preview]', node => node.innerText.includes('Kompilasi referensi')));
            assert.equal(await page.$eval('[data-compilation-author]', node => node.textContent), fixture.compiler);
            assert.equal(await page.$eval('[data-compilation-date]', node => node.textContent), data.compilationDate);
        } else {
            assert.equal((await page.$$('[data-compilation-preview]')).length, 0);
        }
        await page.click(`${form} [aria-label="Tebal"]`);
        await page.type(`${form} .tiptap`, ' Isi formulir yang dipertahankan');
        await page.type(`${form} textarea[name="deliverables"]`, ' Deliverables belum disimpan');
        if (data.kind === 'kompilasi') await page.$eval(`${form} [name="ubah_tgl"]`, node => { node.value = '2027-02-10'; node.dispatchEvent(new Event('input', { bubbles: true })); });
        else await page.select('#status-reviu', 'tindak_lanjut_belum_sesuai');
        await (await page.$(`${form} input[name="dokumen"]`)).uploadFile(fixture.upload);
        await page.waitForFunction((selector, name) => document.querySelector(`${selector} input[name="${name}"]`).value.includes('Isi formulir yang dipertahankan'), {}, form, field);
        const before = await page.$eval(form, node => [...new FormData(node)].map(([name, value]) => [name, value instanceof File ? [value.name, value.size, value.type] : value]));
        const compilationBefore = data.kind === 'reviu' ? await page.$eval('[data-compilation-preview]', node => node.innerHTML) : null;
        if (data.count === 3) {
            await page.waitForSelector('[data-preview-navigation]', { visible: true });
            assert.equal(await page.$eval('[data-preview-previous]', node => node.disabled), true);
            assert.equal(await page.$eval('[data-preview-next]', node => node.disabled), false);
            assert.ok(await page.$eval('[data-response-preview-body] strong', node => node.textContent.includes('Tanggapan panjang')));
            assert.ok(await page.$eval('[data-response-preview-body]', node => node.scrollHeight > node.clientHeight && node.clientHeight <= 258));
            await page.waitForFunction(() => [...document.querySelectorAll('[data-bahan-preview] img')].every(node => node.complete && node.naturalWidth > 0));
            assert.ok((await page.$$('[data-response-preview-body] img[data-width="50"]')).length > 0);
            await page.$eval('[data-response-preview-body]', node => { node.scrollTop = 180; });
            for (const number of [2, 3]) {
                await page.click('[data-preview-next]');
                await page.waitForFunction(counter => document.querySelector('[data-preview-counter]').textContent === counter, {}, `${responseLabel} ${number} dari 3`);
                assert.equal(await page.$eval('[data-preview-author]', node => node.textContent), `Pengisi ${String.fromCharCode(64 + number)}`);
                assert.equal(await page.$eval('[data-preview-date]', node => node.textContent), data.dates[number - 1]);
                assert.equal(await page.$eval('[data-response-preview-body]', node => node.scrollTop), 0);
            }
            assert.equal(await page.$eval('[data-preview-next]', node => node.disabled), true);
            await page.click('[data-preview-previous]');
            await page.click('[data-preview-previous]');
            await page.waitForFunction(counter => document.querySelector('[data-preview-counter]').textContent === counter, {}, `${responseLabel} 1 dari 3`);
            assert.equal(await page.$eval('[data-preview-previous]', node => node.disabled), true);
            if (data.kind === 'reviu') assert.equal(await page.$eval('[data-compilation-preview]', node => node.innerHTML), compilationBefore);
        }
        const after = await page.$eval(form, node => [...new FormData(node)].map(([name, value]) => [name, value instanceof File ? [value.name, value.size, value.type] : value]));
        assert.deepEqual(after, before, 'Preview navigation must preserve all editor, textarea, date, status and uploaded file values');
        assert.ok(await page.$eval('[data-workflow-context]', (node, id) => node.textContent.includes(id), data.butirId), JSON.stringify(await page.$eval('[data-workflow-context]', node => ({ text: node.textContent, selected: window.Alpine.$data(node).selected.butir_id }))));
        assert.ok((await page.$eval(form, node => node.action)).endsWith(data.action), 'Form action must still match the previewed butir');
        assert.equal(await page.evaluate(() => window.previewInjected), undefined);
        assert.deepEqual(writes, [], 'Reading and navigating preview must not submit or save');
        assert.deepEqual(errors, [], 'Preview and form must run without Alpine errors');
        if (data.count === 3) {
            await page.evaluate(() => { document.querySelector('[x-show="openModal"]').scrollTop = 0; });
            await page.screenshot({ path: `${fixture.screenshot}-${data.kind}.png` });
            await page.setViewport({ width: 390, height: 844 });
            assert.ok(await page.$eval('[data-bahan-preview]', node => node.scrollWidth <= node.clientWidth + 1), 'Preview must fit a narrow modal');
            await page.setViewport({ width: 1366, height: 1000 });
            await page.click('[data-preview-next]');
            await page.click('[aria-label="Tutup modal"]');
            await page.waitForSelector('[role="dialog"]', { hidden: true });
            await page.click(`[data-${data.kind}-putaran]`);
            await page.waitForSelector('[role="dialog"]', { visible: true });
            await page.waitForFunction(counter => document.querySelector('[data-preview-counter]').textContent === counter, {}, `${responseLabel} 1 dari 3`);
            await page.waitForFunction(selector => document.querySelector(`${selector} input[name="dokumen"]`).files.length === 0, {}, form);
            assert.equal(await page.$eval(`${form} input[name="dokumen"]`, node => node.files.length), 0);
        }
    }
} finally {
    await browser?.close();
    await new Promise(done => server.close(done));
}
