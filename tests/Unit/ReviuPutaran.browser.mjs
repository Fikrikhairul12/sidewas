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
const executablePath = [process.env.PUPPETEER_EXECUTABLE_PATH, await puppeteer.executablePath(), 'C:/Program Files/Google/Chrome/Application/chrome.exe', 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', '/usr/bin/chromium'].find(path => path && existsSync(path));
let browser;
try {
    browser = await puppeteer.launch({ headless: true, executablePath });
    const page = await browser.newPage();
    page.setDefaultTimeout(6000);
    await page.setViewport({ width: 1366, height: 1000 });
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.setRequestInterception(true);
    page.on('request', request => request.url().startsWith(`http://127.0.0.1:${server.address().port}/`) || request.url().startsWith('data:') ? request.continue() : request.abort());
    for (const [index, data] of fixture.pages.entries()) {
        await page.goto(`http://127.0.0.1:${server.address().port}/${index}`, { waitUntil: 'networkidle0' });
        await page.waitForFunction(() => document.querySelector('[data-workflow-page]')?._x_dataStack && typeof window.snpButirEditor === 'function');
        await page.click(`[data-${data.kind}-putaran]`);
        await page.waitForSelector('[role="dialog"]', { visible: true });
        await page.waitForFunction(() => getComputedStyle(document.querySelector('[x-show="openModal"]')).opacity === '1');
        await page.waitForFunction(() => document.querySelector('.tiptap') && !document.querySelector('[data-snp-editor]')._x_dataStack[0].busy);
        const form = `[data-${data.kind}-form]`;
        assert.ok((await page.$eval(form, node => node.action)).endsWith(data.action));
        assert.equal((await page.$$(`${form} textarea[name="deliverables"]`)).length, 1);
        assert.equal((await page.$$(`${form} [aria-label="Format ${data.kind === 'kompilasi' ? 'Hasil Kompilasi' : 'Hasil Reviu'}"]`)).length, 1);
        await page.click(`${form} [aria-label="Tebal"]`);
        await page.type(`${form} .tiptap`, ' Isi Berformat');
        await page.type(`${form} textarea[name="deliverables"]`, 'Teks <literal>', { delay: 10 });
        const field = data.kind === 'kompilasi' ? 'hasil_kompilasi' : 'hasil_review';
        await page.waitForFunction((selector, name) => document.querySelector(`${selector} input[name="${name}"]`).value.includes('<strong>'), {}, form, field);
        if (data.kind === 'reviu') {
            assert.deepEqual(await page.$$eval('#status-reviu option', nodes => nodes.map(node => node.value)), fixture.statuses);
            await page.select('#status-reviu', 'tindak_lanjut_belum_sesuai');
            assert.ok(await page.$eval('[data-compilation-preview]', node => node.innerText.includes('Kompilasi sumber')));
        }
        const posted = await page.$eval(form, node => {
            let result;
            node.addEventListener('submit', event => { event.preventDefault(); result = Object.fromEntries(new FormData(node).entries()); });
            node.requestSubmit();
            return result;
        });
        assert.ok(posted[field].includes('<strong>'));
        assert.equal(posted.deliverables, 'Teks <literal>');
        if (data.kind === 'kompilasi') {
            assert.equal(posted.tahap_kompilasi, data.stageType ?? 'tanggapan');
            if (data.stageId !== undefined) assert.equal(posted.tahap_id, String(data.stageId ?? ''));
            if (data.roundId !== undefined) assert.equal(posted.putaran_id, String(data.roundId ?? ''));
        }
        else assert.equal(posted.status, 'tindak_lanjut_belum_sesuai');
        assert.deepEqual(errors, [], 'The modal and toolbar must run without Alpine errors');
        if (fixture.screenshot && data.kind === 'reviu') await page.screenshot({ path: fixture.screenshot });
        if (data.screenshot) await page.screenshot({ path: data.screenshot });
    }
} finally {
    await browser?.close();
    await new Promise(done => server.close(done));
}
