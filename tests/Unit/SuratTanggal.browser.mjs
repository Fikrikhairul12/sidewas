import assert from 'node:assert/strict';
import { existsSync, readFileSync } from 'node:fs';
import { createServer } from 'node:http';
import { resolve, sep } from 'node:path';
import puppeteer from 'puppeteer';

let input = '';
for await (const chunk of process.stdin) input += chunk;
const fixture = JSON.parse(input);
const root = resolve('public/build');
const server = createServer((request, response) => {
    const index = /^\/(\d+)$/.exec(request.url)?.[1];
    if (index !== undefined) {
        response.setHeader('Content-Type', 'text/html; charset=utf-8');
        response.end(fixture.pages[Number(index)].html.replace('</head>', `<link rel="stylesheet" href="/build/${fixture.css}"><script type="module" src="/build/${fixture.js}"></script></head>`));
        return;
    }
    const path = resolve(root, decodeURIComponent(request.url).replace(/^\/build\//, ''));
    if (!path.startsWith(root + sep) || !existsSync(path)) return response.writeHead(404).end();
    response.setHeader('Content-Type', path.endsWith('.css') ? 'text/css' : 'application/javascript');
    response.end(readFileSync(path));
});
await new Promise(done => server.listen(0, '127.0.0.1', done));
const executablePath = [process.env.PUPPETEER_EXECUTABLE_PATH, await puppeteer.executablePath(), 'C:/Program Files/Google/Chrome/Application/chrome.exe', 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe'].find(path => path && existsSync(path));
let browser;
try {
    browser = await puppeteer.launch({ headless: true, executablePath });
    const page = await browser.newPage();
    page.setDefaultTimeout(8000);
    await page.setViewport({ width: 1366, height: 1100 });
    const errors = [];
    const writes = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.setRequestInterception(true);
    page.on('request', request => {
        if (request.method() !== 'GET') writes.push(request.url());
        if (request.url().startsWith(`http://127.0.0.1:${server.address().port}/`) || request.url().startsWith('data:')) request.continue();
        else request.abort();
    });
    const setDate = async (selector, value) => page.$eval(selector, (node, value) => {
        node.value = value;
        node.dispatchEvent(new Event('input', { bubbles: true }));
    }, value);
    const postData = async selector => page.$eval(selector, node => {
        let result;
        node.addEventListener('submit', event => { event.preventDefault(); result = Object.fromEntries(new FormData(node)); }, { once: true });
        node.requestSubmit();
        return result;
    });
    for (const [index, data] of fixture.pages.entries()) {
        await page.goto(`http://127.0.0.1:${server.address().port}/${index}`, { waitUntil: 'networkidle0' });
        if (data.kind === 'detail') {
            await page.click('[data-isian-detail]');
            await page.waitForSelector('[data-isian-detail-modal]', { visible: true });
            await page.waitForFunction(() => getComputedStyle(document.querySelector('[data-isian-detail-modal]')).opacity === '1');
            const selector = '[data-isian-detail-modal] [data-detail-tenggat]';
            assert.ok(await page.$eval(selector, node => node.textContent.includes('30/11/2026 23.59 WIB')));
            await page.click(`${selector} summary`);
            assert.ok(await page.$eval(selector, node => node.innerText.includes('pengajuan disetujui')));
            await page.setViewport({ width: 390, height: 844 });
            assert.ok(await page.$eval(selector, node => node.scrollWidth <= node.clientWidth + 1));
            await page.setViewport({ width: 1366, height: 1100 });
        } else {
            const kind = data.kind.includes('compile') ? 'kompilasi' : 'reviu';
            const form = `[data-${kind}-form]`;
            await page.click(`[data-${kind}-putaran]`);
            await page.waitForSelector('[role="dialog"]', { visible: true });
            await page.waitForFunction(() => document.querySelector('.tiptap') && !document.querySelector('[data-snp-editor]')._x_dataStack[0].busy);
            await page.click(`${form} .tiptap`);
            await page.type(`${form} .tiptap`, 'Catatan pemeriksaan tanggal');
            if (data.kind === 'initial-compile') {
                assert.equal((await page.$$(`${form} input[name="ubah_tgl"]`)).length, 0);
            } else if (data.kind === 'open-review') {
                assert.equal((await page.$$('input[name="jatuh_tempo_tl"]')).length, 0);
                await page.select('#status-reviu', 'tindak_lanjut_belum_sesuai');
                await page.waitForSelector('[data-next-deadline]', { visible: true });
                assert.equal(await page.$eval('[name="jatuh_tempo_tl"]', node => node.value), '2026-11-15');
                await setDate('[name="jatuh_tempo_tl"]', '2026-11-01');
                assert.equal(await page.$eval('[name="jatuh_tempo_tl"]', node => node.checkValidity()), false);
                await setDate('[name="jatuh_tempo_tl"]', '2026-11-20');
                await page.waitForFunction(() => document.querySelector('[name="alasan_jatuh_tempo"]').required);
                assert.equal(await page.$eval('[name="alasan_jatuh_tempo"]', node => node.checkValidity()), false);
                await page.type('[name="alasan_jatuh_tempo"]', 'Memerlukan koordinasi lebih lanjut');
                const posted = await postData(form);
                assert.equal(posted.jatuh_tempo_tl, '2026-11-20');
                assert.equal(posted.alasan_jatuh_tempo, 'Memerlukan koordinasi lebih lanjut');
                await page.select('#status-reviu', 'tindak_lanjut_sesuai');
                await page.waitForFunction(() => !document.querySelector('[name="jatuh_tempo_tl"]'));
                assert.equal((await postData(form)).jatuh_tempo_tl, undefined);
                await page.select('#status-reviu', 'tidak_dapat_ditindaklanjuti');
                assert.equal((await postData(form)).jatuh_tempo_tl, undefined);
            } else if (data.kind === 'follow-compile') {
                await setDate('[name="ubah_tgl"]', '2026-11-30');
                await page.waitForFunction(() => document.querySelector('[name="alasan_pengajuan_tgl"]').required);
                await page.type('[name="alasan_pengajuan_tgl"]', 'Bahan tambahan diperlukan');
                const posted = await postData(form);
                assert.equal(posted.ubah_tgl, '2026-11-30');
                assert.equal(posted.alasan_pengajuan_tgl, 'Bahan tambahan diperlukan');
                assert.equal(posted.tahap_id, String(data.stageId));
            } else {
                await page.select('[name="status_pengajuan_tgl"]', 'disetujui');
                await page.waitForFunction(() => document.querySelector('[name="alasan_keputusan_tgl"]').required);
                await page.type('[name="alasan_keputusan_tgl"]', 'Bukti mendukung perpanjangan');
                const posted = await postData(form);
                assert.equal(posted.status_pengajuan_tgl, 'disetujui');
                assert.equal(posted.alasan_keputusan_tgl, 'Bukti mendukung perpanjangan');
                assert.equal(posted.jatuh_tempo_tl, undefined);
            }
        }
        assert.deepEqual(errors, [], `${data.kind}: Alpine must remain usable`);
        assert.deepEqual(writes, [], 'The browser verification must not submit data');
        if (fixture.screenshot && data.kind === 'detail') await page.screenshot({ path: fixture.screenshot });
    }
} finally {
    await browser?.close();
    await new Promise(done => server.close(done));
}
