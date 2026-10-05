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
const buildRoot = resolve('public/build');
const server = createServer((request, response) => {
    if (request.url === '/') {
        response.setHeader('Content-Type', 'text/html; charset=utf-8');
        response.end(`<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="/build/${fixture.css}"><script type="module" src="/build/${fixture.js}"></script></head><body>${fixture.html}</body></html>`);
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
    page.on('pageerror', error => errors.push(error.message));
    await page.setViewport({ width: 1366, height: 900 });
    await page.goto(`http://127.0.0.1:${server.address().port}`, { waitUntil: 'networkidle0' });
    const readerOpen = () => page.waitForFunction(() => document.getElementById('snpButirReader').open);
    const readerClosed = () => page.waitForFunction(() => !document.getElementById('snpButirReader').open && document.body.style.overflow !== 'hidden');

    await page.click('#staticPreview button');
    await readerOpen();
    assert.equal(await page.$eval('[data-snp-reader-content]', element => element.textContent), fixture.content);
    assert.equal(await page.$eval('[data-snp-reader-content]', element => element.children.length), 0);
    assert.equal(await page.evaluate(() => window.injected), undefined);
    assert.equal(await page.$eval('[data-snp-reader-navigation]', element => element.hidden), true);
    const typography = await page.$eval('[data-snp-reader-content]', element => {
        const style = getComputedStyle(element);
        return { whiteSpace: style.whiteSpace, size: style.fontSize, transform: style.textTransform };
    });
    assert.deepEqual(typography, { whiteSpace: 'pre-wrap', size: '16px', transform: 'none' });
    for (let index = 0; index < 4; index++) {
        await page.keyboard.press('Tab');
        assert.equal(await page.evaluate(() => document.getElementById('snpButirReader').contains(document.activeElement)), true);
    }
    await page.keyboard.press('Escape');
    await readerClosed();
    assert.equal(await page.$eval('#parentForm', element => getComputedStyle(element).display !== 'none'), true);
    assert.equal(await page.$eval('#draft', element => element.value), 'Draf tanggapan belum disimpan.');
    assert.equal(await page.$eval('#pic', element => element.value), 'unit-2');
    assert.equal(await page.evaluate(() => document.activeElement.closest('#staticPreview') !== null), true);

    await page.click('#dynamicPreview button');
    await readerOpen();
    assert.equal(await page.$eval('#snpButirReaderTitle', element => element.textContent), 'SNP.02');
    await page.click('[data-snp-reader-previous]');
    assert.equal(await page.$eval('[data-snp-reader-content]', element => element.textContent), fixture.content);
    await page.$eval('[data-snp-reader-scroll]', element => { element.scrollTop = 400; });
    await page.click('[data-snp-reader-next]');
    assert.equal(await page.$eval('[data-snp-reader-scroll]', element => element.scrollTop), 0);
    await page.select('#snpButirReaderSelect', '0');

    const desktop = await page.$eval('#snpButirReader', element => ({ width: element.offsetWidth, height: element.offsetHeight }));
    assert.ok(desktop.width >= 1000 && desktop.height >= 800);
    if (process.env.SNP_READER_SCREENSHOTS) {
        await page.screenshot({ path: resolve(process.env.SNP_READER_SCREENSHOTS, 'snp-reader-desktop.png') });
    }
    await page.setViewport({ width: 375, height: 812 });
    const mobile = await page.$eval('#snpButirReader', element => ({ width: element.clientWidth, scrollWidth: element.scrollWidth, height: element.offsetHeight }));
    assert.ok(mobile.width <= 375 && mobile.scrollWidth <= mobile.width && mobile.height <= 812);
    await page.$eval('[data-snp-reader-scroll]', element => { element.scrollTop = element.scrollHeight; });
    const closeButton = await page.$eval('[data-snp-reader-close]', element => ({ top: element.getBoundingClientRect().top, bottom: element.getBoundingClientRect().bottom }));
    assert.ok(closeButton.top >= 0 && closeButton.bottom < 812);
    if (process.env.SNP_READER_SCREENSHOTS) {
        await page.screenshot({ path: resolve(process.env.SNP_READER_SCREENSHOTS, 'snp-reader-mobile.png') });
    }
    await page.click('[data-snp-reader-close]');
    await readerClosed();
    await page.setViewport({ width: 1366, height: 900 });

    await page.click('#openCustomReportModalBtn');
    await page.click('.custom-butir-checkbox');
    const selectionBefore = await page.$$eval('.custom-butir-checkbox', elements => elements.map(element => element.checked));
    await page.click('#customReportButirList .snp-butir-read-button');
    await readerOpen();
    assert.equal(await page.$eval('[data-snp-reader-content]', element => element.textContent), fixture.content);
    await page.keyboard.press('Escape');
    await readerClosed();
    assert.deepEqual(await page.$$eval('.custom-butir-checkbox', elements => elements.map(element => element.checked)), selectionBefore);
    assert.equal(await page.$eval('#customReportModal', element => element.classList.contains('hidden')), false);
    await page.click('#closeCustomReportModalBtn');

    await page.click('[data-pengajuan-detail-trigger]');
    await page.click('#pengajuanDetailBacaButir');
    await readerOpen();
    assert.equal(await page.$eval('#snpButirReaderContext', element => element.textContent), 'Pengajuan edit SNP · Isi yang diajukan');
    await page.keyboard.press('Escape');
    await readerClosed();
    assert.equal(await page.$eval('#pengajuanDetailModal', element => element.classList.contains('hidden')), false);
    await page.click('[data-pengajuan-detail-close]');
    await page.click('#otherModulePending');
    assert.equal(await page.$eval('#pengajuanDetailBacaButir', element => getComputedStyle(element).display), 'none');
    await page.click('[data-pengajuan-detail-close]');

    for (const detailPage of ['perekaman', 'tanggapan', 'reviu', 'tindak-lanjut']) {
        const scope = `#detail-${detailPage}`;
        await page.click(`${scope} [data-open-detail]`);
        await page.waitForFunction(selector => {
            const element = document.querySelector(`${selector} .snp-butir-preview--expanded`);
            return element && element.getClientRects().length;
        }, {}, scope);
        assert.equal(await page.$eval(`${scope} .snp-butir-preview__text`, element => element.textContent), fixture.content);
        const readButtonTop = await page.$eval(`${scope} .snp-butir-read-button`, element => element.getBoundingClientRect().top);
        assert.ok(readButtonTop > 0 && readButtonTop < 400, 'Enlarge action must be visible before scrolling the content');
        await page.click(`${scope} .snp-butir-read-button`);
        await readerOpen();
        assert.equal(await page.$eval('[data-snp-reader-content]', element => element.textContent), fixture.content);
        await page.keyboard.press('Escape');
        await readerClosed();
        assert.equal(await page.$eval(`${scope} [x-show="openDetailModal"]`, element => getComputedStyle(element).display !== 'none'), true);
        if (process.env.SNP_READER_SCREENSHOTS && detailPage === 'tanggapan') {
            await page.screenshot({ path: resolve(process.env.SNP_READER_SCREENSHOTS, 'snp-tanggapan-detail.png') });
        }
        await page.click(`${scope} [x-show="openDetailModal"] button`);
        await page.waitForFunction(selector => getComputedStyle(document.querySelector(`${selector} [x-show="openDetailModal"]`)).display === 'none', {}, scope);
    }
    await page.click('[data-open-kompilasi]');
    await page.waitForFunction(() => document.querySelector('#kompilasiForm [x-show="openModal"]').getAnimations({ subtree: true }).length === 0 && getComputedStyle(document.querySelector('#kompilasiForm [x-show="openModal"]')).display !== 'none');
    await page.type('#kompilasiForm textarea[name="hasil_kompilasi"]', 'Draf kompilasi masih dikerjakan.');
    await page.click('#kompilasiForm .snp-butir-read-button');
    await readerOpen();
    assert.equal(await page.$eval('[data-snp-reader-content]', element => element.textContent), fixture.content);
    await page.click('[data-snp-reader-close]');
    await readerClosed();
    assert.equal(await page.$eval('#kompilasiForm textarea[name="hasil_kompilasi"]', element => element.value), 'Draf kompilasi masih dikerjakan.');
    assert.equal(await page.$eval('#kompilasiForm [x-show="openModal"]', element => getComputedStyle(element).display !== 'none'), true);
    assert.deepEqual(errors, []);
    console.log('Passed: complete text, escaped markup, focus, form preservation, navigation, responsive reading, report selection, and pending-edit context.');
} finally {
    if (browser) {
        await browser.close();
    }
    server.close();
}
