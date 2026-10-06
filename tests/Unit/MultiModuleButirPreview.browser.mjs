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
    if (request.url.startsWith('/?module=')) {
        const module = new URL(request.url, 'http://localhost').searchParams.get('module');
        response.setHeader('Content-Type', 'text/html; charset=utf-8');
        response.end(`<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="/build/${fixture.css}"><script type="module" src="/build/${fixture.js}"></script></head><body>${fixture.fixtures[module].html}</body></html>`);
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
const executablePath = [process.env.PUPPETEER_EXECUTABLE_PATH, puppeteer.executablePath(), 'C:/Program Files/Google/Chrome/Application/chrome.exe', '/usr/bin/chromium'].find(path => path && existsSync(path));
let browser;
try {
    browser = await puppeteer.launch({ headless: true, executablePath });
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    const open = () => page.waitForFunction(() => document.getElementById('snpButirReader').open);
    const close = async () => {
        await page.keyboard.press('Escape');
        await page.waitForFunction(() => !document.getElementById('snpButirReader').open && document.body.style.overflow !== 'hidden');
    };
    for (const [module, data] of Object.entries(fixture.fixtures)) {
        await page.setViewport({ width: 1366, height: 900 });
        await page.goto(`http://127.0.0.1:${server.address().port}/?module=${module}`, { waitUntil: 'networkidle0' });
        assert.deepEqual(errors, [], `${module} must initialize its actual edit modal without Alpine errors`);
        await page.$eval('#workflowForm', form => {
            window.submissions = 0;
            form.addEventListener('submit', event => { event.preventDefault(); window.submissions++; });
        });
        for (const section of data.sections) {
            await page.click(`#${section} .snp-butir-read-button`);
            await open();
            assert.equal(await page.$eval('[data-snp-reader-content]', element => element.textContent), fixture.content, `${module} ${section} must receive full text`);
            assert.equal(await page.$eval('[data-snp-reader-content]', element => element.children.length), 0);
            assert.equal(await page.$eval('#snpButirReaderTitle', element => element.textContent), data.id);
            assert.equal(await page.evaluate(() => window.injected), undefined);
            await close();
            assert.equal(await page.$eval('#parentForm', element => getComputedStyle(element).display !== 'none'), true);
            assert.equal(await page.$eval('#draft', element => element.value), 'Draf belum disimpan.');
            assert.equal(await page.$eval('#pic', element => element.value), '2');
            assert.equal(await page.$eval('input[name="butir_id"]', element => element.value), '1');
            assert.equal(await page.evaluate(() => window.submissions), 0);
            assert.equal(await page.evaluate(id => document.activeElement.closest(`#${id}`) !== null, section), true);
        }
        const expanded = data.sections.find(section => section.startsWith('perekaman-2'));
        await page.click(`#${expanded} .snp-butir-read-button`);
        await open();
        await page.click('[data-snp-reader-next]');
        assert.equal(await page.$eval('[data-snp-reader-content]', element => element.textContent), 'Isi kedua.\nBaris kedua.');
        await page.click('[data-snp-reader-previous]');
        assert.equal(await page.$eval('[data-snp-reader-content]', element => element.textContent), fixture.content);
        const desktop = await page.$eval('#snpButirReader', element => ({ width: element.offsetWidth, height: element.offsetHeight }));
        assert.ok(desktop.width >= 1000 && desktop.height >= 800);
        if (process.env.BUTIR_PREVIEW_SCREENSHOTS && module === 'ragab') {
            await page.screenshot({ path: resolve(process.env.BUTIR_PREVIEW_SCREENSHOTS, 'butir-reader-desktop.png') });
        }
        await page.setViewport({ width: 375, height: 812 });
        const mobile = await page.$eval('#snpButirReader', element => ({ width: element.clientWidth, scrollWidth: element.scrollWidth, height: element.offsetHeight }));
        assert.ok(mobile.width <= 375 && mobile.scrollWidth <= mobile.width && mobile.height <= 812);
        if (process.env.BUTIR_PREVIEW_SCREENSHOTS && module === 'ragab') {
            await page.screenshot({ path: resolve(process.env.BUTIR_PREVIEW_SCREENSHOTS, 'butir-reader-mobile.png') });
        }
        await close();
        await page.setViewport({ width: 1366, height: 900 });
        await page.click('#openCustomReportModalBtn');
        await page.click('.custom-butir-checkbox');
        const selections = await page.$$eval('.custom-butir-checkbox', elements => elements.map(element => element.checked));
        const fields = await page.$$eval('.custom-field-checkbox', elements => elements.map(element => element.checked));
        await page.click('#customReportButirList .snp-butir-read-button');
        await open();
        assert.equal(await page.$eval('[data-snp-reader-content]', element => element.textContent), fixture.content);
        await page.click('[data-snp-reader-next]');
        assert.equal(await page.$eval('[data-snp-reader-content]', element => element.textContent), 'Isi kedua.\nBaris kedua.');
        await close();
        assert.deepEqual(await page.$$eval('.custom-butir-checkbox', elements => elements.map(element => element.checked)), selections);
        assert.deepEqual(await page.$$eval('.custom-field-checkbox', elements => elements.map(element => element.checked)), fields);
        assert.equal(await page.$eval('#customReportModal', element => element.classList.contains('hidden')), false);
        await page.click('#closeCustomReportModalBtn');
        await page.click('#openActualEdit');
        await page.waitForSelector('#actualEdit form', { visible: true });
        assert.equal(await page.$eval('#actualEdit input[name="nomor_surat"]', element => element.value), 'Surat asli');
        assert.equal(await page.$eval('#actualEdit textarea[name="perihal_surat"]', element => element.value), 'Perihal asli');
        assert.equal(await page.$eval('#actualEdit select[name="status"]', element => element.value), 'dalam_proses');
        assert.equal(await page.$eval('#actualEdit form', element => new URL(element.action).pathname), '/existing-workflow/1');
        assert.equal(await page.$eval('#actualEdit input[name="_method"]', element => element.value), 'PATCH');
        await page.$eval('#actualEdit textarea[name="perihal_surat"]', element => {
            element.value = 'Draf edit belum disimpan.';
            element.dispatchEvent(new Event('input', { bubbles: true }));
        });
        await page.evaluate(() => {
            window.dispatchEvent(new CustomEvent('snp-read-butir', { detail: { id: 'EDIT.01', content: 'Isi rujukan edit.', format: 'plain' } }));
        });
        await open();
        await close();
        assert.equal(await page.$eval('#actualEdit textarea[name="perihal_surat"]', element => element.value), 'Draf edit belum disimpan.');
        assert.equal(await page.$eval('#actualEdit form', element => element.getClientRects().length > 0), true);
    }
    assert.deepEqual(errors, []);
    console.log('Passed: 17 page previews, full text, draft preservation, navigation, responsive reader and unchanged report selection.');
} finally {
    if (browser) await browser.close();
    server.close();
}
