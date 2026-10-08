import assert from 'node:assert/strict';
import { existsSync, readFileSync } from 'node:fs';
import { createServer } from 'node:http';
import { resolve, sep } from 'node:path';
import puppeteer from 'puppeteer';

let input = '';
for await (const chunk of process.stdin) { input += chunk; }
const fixture = JSON.parse(input);
const buildRoot = resolve('public/build');
const server = createServer((request, response) => {
    if (request.url === '/') {
        response.setHeader('Content-Type', 'text/html; charset=utf-8');
        response.end(`<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="/build/${fixture.css}"><script type="module" src="/build/${fixture.js}"></script></head><body><button id="open" onclick="window.dispatchEvent(new CustomEvent('open-modal', {detail: 'produk-hukum-report'}))">Download Rekap</button>${fixture.html}</body></html>`);
        return;
    }
    const file = resolve(buildRoot, decodeURIComponent(request.url).replace(/^\/build\//, ''));
    if (!file.startsWith(buildRoot + sep) || !existsSync(file)) { response.writeHead(404).end(); return; }
    response.setHeader('Content-Type', file.endsWith('.css') ? 'text/css' : 'application/javascript');
    response.end(readFileSync(file));
});
await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
const executablePath = [process.env.PUPPETEER_EXECUTABLE_PATH, puppeteer.executablePath(),
    'C:/Program Files/Google/Chrome/Application/chrome.exe', 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',
    '/usr/bin/chromium', '/usr/bin/google-chrome',
].find(path => path && existsSync(path));
const product = id => ({ id, kode_produk_hukum: `PH-${id}`, judul: id === 1 ? 'Judul <script>window.injected=true</script>' : `Peraturan pilihan ${id}`, nomor_peraturan_keputusan: `NOMOR/${id}`, tahun_peraturan: 2026, status_peraturan: id % 2 ? 'draft' : 'berlaku', sifat_dokumen: 'publik' });
const downloads = [];
let browser;
try {
    browser = await puppeteer.launch({ headless: true, executablePath });
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.setRequestInterception(true);
    page.on('request', request => {
        const url = new URL(request.url());
        if (url.pathname.endsWith('/rekap/pilihan')) {
            const keyword = url.searchParams.get('keyword');
            const currentPage = Number(url.searchParams.get('page') || 1);
            const data = keyword ? [product(30)] : currentPage === 1 ? Array.from({ length: 20 }, (_, index) => product(index + 1)) : [product(21)];
            request.respond({ status: keyword === 'gagal' ? 500 : 200, contentType: 'application/json', headers: { 'Access-Control-Allow-Origin': '*' }, body: JSON.stringify({ data, current_page: currentPage, last_page: keyword ? 1 : 2, total: keyword ? 1 : 21 }) });
        } else if (url.pathname.endsWith('/rekap/download')) {
            if (request.method() === 'OPTIONS') {
                request.respond({ status: 204, headers: { 'Access-Control-Allow-Origin': '*', 'Access-Control-Allow-Headers': '*', 'Access-Control-Allow-Methods': '*' } });
                return;
            }
            const payload = JSON.parse(request.postData());
            downloads.push(payload);
            request.respond({ status: 200, contentType: payload.format === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', headers: { 'Access-Control-Allow-Origin': '*' }, body: '%PDF-1.7 test' });
        } else { request.continue(); }
    });
    await page.setViewport({ width: 1366, height: 900 });
    await page.goto(`http://127.0.0.1:${server.address().port}`, { waitUntil: 'networkidle0' });
    await page.click('#open');
    await page.waitForSelector('dialog[open]');
    const count = () => page.$eval('[aria-live="polite"] strong', element => Number(element.textContent));
    const search = async text => {
        await page.$eval('#report-search', element => { element.value = ''; element.dispatchEvent(new Event('input', { bubbles: true })); });
        await page.type('#report-search', text);
        await page.keyboard.press('Enter');
        await page.waitForNetworkIdle({ idleTime: 400 });
    };
    assert.equal(await count(), 36);
    await page.select('#report-status', 'tidak_berlaku');
    assert.equal(await count(), 2);
    await page.click('button[type="submit"]');
    await page.waitForFunction(() => document.querySelector('[role="status"][x-text="success"]').textContent.includes('berhasil'));
    assert.deepEqual(downloads[0], { mode: 'status', format: 'pdf', status: 'tidak_berlaku' });
    await page.click('input[name="report-mode"][value="manual"]');
    await page.waitForSelector('input[type="checkbox"][value="1"]');
    assert.equal(await page.evaluate(() => window.injected), undefined);
    assert.equal(await page.$eval('button[type="submit"]', element => element.disabled), true);
    await page.click('input[type="checkbox"][value="1"]');
    await page.click('button[x-text="selectedOnly ? \'Kembali ke pencarian\' : \'Lihat pilihan\'"]');
    assert.equal(await page.$$eval('input[type="checkbox"]', elements => elements.length), 1);
    await page.click('button[x-text="selectedOnly ? \'Kembali ke pencarian\' : \'Lihat pilihan\'"]');
    await page.click('button[\\@click="search(page + 1)"]');
    await page.waitForSelector('input[type="checkbox"][value="21"]');
    await page.click('input[type="checkbox"][value="21"]');
    await search('tata kelola');
    await page.waitForSelector('input[type="checkbox"][value="30"]');
    await page.click('input[type="checkbox"][value="30"]');
    assert.equal(await count(), 3);
    await page.click('input[name="report-mode"][value="status"]');
    assert.equal(await count(), 2);
    await page.click('input[name="report-mode"][value="manual"]');
    assert.equal(await count(), 3);
    await page.click('button[x-text="selectedOnly ? \'Kembali ke pencarian\' : \'Lihat pilihan\'"]');
    await page.click('input[type="checkbox"][value="1"]');
    assert.equal(await count(), 2);
    await page.click('input[name="report-format"][value="xlsx"]');
    await page.click('button[type="submit"]');
    await page.waitForFunction(() => document.querySelector('[role="status"][x-text="success"]').textContent.includes('Excel berhasil'));
    assert.deepEqual(downloads[1], { mode: 'manual', format: 'xlsx', product_ids: [21, 30] });
    if (process.env.PRODUK_HUKUM_REPORT_QA) {
        await page.screenshot({ path: resolve(process.env.PRODUK_HUKUM_REPORT_QA, 'report-desktop.png') });
    }
    await page.setViewport({ width: 390, height: 844 });
    const bounds = await page.$eval('dialog', element => ({ width: element.clientWidth, scrollWidth: element.scrollWidth, height: element.offsetHeight }));
    assert.ok(bounds.width <= 390 && bounds.scrollWidth <= bounds.width && bounds.height <= 844);
    if (process.env.PRODUK_HUKUM_REPORT_QA) {
        await page.screenshot({ path: resolve(process.env.PRODUK_HUKUM_REPORT_QA, 'report-mobile.png') });
    }
    await page.click('button[x-text="selectedOnly ? \'Kembali ke pencarian\' : \'Lihat pilihan\'"]');
    await search('gagal');
    assert.equal(await count(), 2);
    assert.match(await page.$eval('[x-text="searchError"]', element => element.textContent), /gagal dimuat/);
    await page.focus('[aria-label="Tutup rekap"]');
    await page.keyboard.press('Escape');
    await page.waitForFunction(() => !document.querySelector('dialog').open && document.body.style.overflow !== 'hidden');
    assert.equal(await page.evaluate(() => document.body.style.overflow), '');
    assert.deepEqual(errors, []);
    console.log('Report browser checks passed');
} finally {
    if (browser) { await browser.close(); }
    server.close();
}
