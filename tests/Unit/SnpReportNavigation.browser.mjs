import assert from 'node:assert/strict';
import { existsSync, mkdirSync, mkdtempSync, readFileSync, rmSync } from 'node:fs';
import { createServer } from 'node:http';
import { join, resolve, sep } from 'node:path';
import puppeteer from 'puppeteer';

let input = '';
for await (const chunk of process.stdin) input += chunk;
const fixture = JSON.parse(input);
const buildRoot = resolve('public/build');
const requests = [];
const testRoot = resolve('storage/framework/testing');
mkdirSync(testRoot, { recursive: true });
const downloads = mkdtempSync(join(testRoot, 'snp-report-navigation-'));
const html = name => `<!doctype html><html><head><meta charset="utf-8"><link rel="stylesheet" href="/build/${fixture.css}"><script type="module" src="/build/${fixture.js}"></script></head><body>${fixture.fixtures[name].replaceAll('http://localhost', '')}</body></html>`;
const server = createServer(async (request, response) => {
    const path = new URL(request.url, 'http://localhost').pathname;
    if (request.method === 'POST') {
        let body = '';
        for await (const chunk of request) body += chunk;
        requests.push({ path, parameters: new URLSearchParams(body) });
        if (path.includes('download') || path.includes('excel')) {
            const type = path.includes('excel') ? 'excel' : 'pdf';
            response.setHeader('Content-Type', type === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            response.setHeader('Content-Disposition', `attachment; filename="report-snp.${type === 'pdf' ? 'pdf' : 'xlsx'}"`);
            response.end(Buffer.from(fixture[type], 'base64'));
        } else {
            response.setHeader('Content-Type', 'text/html; charset=utf-8');
            response.end(html(path.endsWith('custom') ? 'custom' : 'regular'));
        }
        return;
    }
    if (path === '/snp/report' || path === '/ragab/report/cetak') {
        response.setHeader('Content-Type', 'text/html; charset=utf-8');
        response.end(html(path.startsWith('/snp') ? 'index' : 'other'));
        return;
    }
    const file = resolve(buildRoot, decodeURIComponent(path).replace(/^\/build\//, ''));
    if (!file.startsWith(buildRoot + sep) || !existsSync(file)) {
        response.writeHead(404).end();
        return;
    }
    response.setHeader('Content-Type', file.endsWith('.css') ? 'text/css' : 'application/javascript');
    response.end(readFileSync(file));
});
await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
const origin = `http://127.0.0.1:${server.address().port}`;
const reportUrl = `${origin}/snp/report?keyword=SURAT&status=dalam_proses&page=2`;
const executablePath = [process.env.PUPPETEER_EXECUTABLE_PATH, puppeteer.executablePath(), 'C:/Program Files/Google/Chrome/Application/chrome.exe', '/usr/bin/chromium'].find(path => path && existsSync(path));
let browser;
try {
    browser = await puppeteer.launch({ headless: true, executablePath });
    const context = await browser.createBrowserContext({ downloadBehavior: { policy: 'allow', downloadPath: downloads } });
    const page = await context.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    page.on('dialog', dialog => dialog.accept());
    await page.setViewport({ width: 1366, height: 900 });
    await page.goto(reportUrl, { waitUntil: 'networkidle0' });
    const tabCount = (await context.pages()).length;
    const navigate = selector => Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }), page.click(selector)]);
    const checked = (form, name) => page.$$eval(`${form} input[name="${name}"]:checked`, inputs => inputs.map(input => input.value));
    const download = async (selector, path, extension) => {
        await Promise.all([page.waitForResponse(response => new URL(response.url()).pathname === path), page.click(selector)]);
        await page.waitForFunction(() => document.readyState === 'complete');
        for (let attempt = 0; attempt < 100 && !existsSync(join(downloads, `report-snp.${extension}`)); attempt++) {
            await new Promise(resolve => setTimeout(resolve, 50));
        }
        assert.ok(existsSync(join(downloads, `report-snp.${extension}`)), 'Download completes');
        const bytes = readFileSync(join(downloads, `report-snp.${extension}`));
        assert.ok(bytes.subarray(0, 4).equals(extension === 'pdf' ? Buffer.from('%PDF') : Buffer.from([0x50, 0x4b, 0x03, 0x04])));
        rmSync(join(downloads, `report-snp.${extension}`));
        assert.equal((await context.pages()).length, tabCount, 'Download retains the current tab');
    };

    await page.click('#reportForm input[value="1"]');
    await page.click('#openReportFormatModalBtn');
    await navigate('[data-snp-pdf-preview][form="reportForm"]');
    assert.equal(new URL(page.url()).pathname, '/snp/report/cetak');
    assert.equal((await context.pages()).length, tabCount, 'Regular preview reuses the current tab');
    assert.equal(await page.$eval('[data-snp-report-return]', link => link.href), reportUrl);
    await download('form[action$="/download"] button', '/snp/report/download', 'pdf');
    assert.equal(new URL(page.url()).pathname, '/snp/report/cetak');
    assert.deepEqual(requests.at(-1).parameters.getAll('record_ids[]'), ['1']);
    await navigate('[data-snp-report-return]');
    assert.equal(page.url(), reportUrl);
    assert.deepEqual(await checked('#reportForm', 'record_ids[]'), ['1']);
    assert.equal(await page.$eval('input[name="keyword"]', input => input.value), 'SURAT');
    assert.equal(await page.$eval('select[name="status"]', input => input.value), 'dalam_proses');
    assert.equal(await page.$eval('#reportFormatModal', modal => modal.classList.contains('hidden')), true);

    await page.click('#openReportFormatModalBtn');
    await navigate('[data-snp-pdf-preview][form="reportForm"]');
    await page.goBack({ waitUntil: 'networkidle0' });
    assert.equal(page.url(), reportUrl);
    assert.deepEqual(await checked('#reportForm', 'record_ids[]'), ['1'], 'Browser Back also restores selection');
    assert.equal(await page.$eval('#reportFormatModal', modal => modal.classList.contains('hidden')), true);

    await page.click('#openCustomReportModalBtn');
    await page.click('#customReportForm input[name="butir_ids[]"][value="11"]');
    await page.click('#customReportForm input[name="tanggapan_unit_kerja_ids[]"][value="8"]');
    await page.click('#customReportForm input[name="tindak_lanjut_unit_kerja_ids[]"][value="7"]');
    await page.$$eval('#customReportForm input[name="fields[]"]', inputs => inputs.forEach(input => { input.checked = ['id_butir', 'isi_butir'].includes(input.value); }));
    await download('#customReportForm button[formaction$="cetak-excel-custom"]', '/snp/report/cetak-excel-custom', 'xlsx');
    assert.equal(page.url(), reportUrl, 'Custom Excel remains a direct download');
    await navigate('#customReportForm [data-snp-pdf-preview]');
    assert.equal(new URL(page.url()).pathname, '/snp/report/cetak-custom');
    assert.equal((await context.pages()).length, tabCount, 'Custom preview reuses the current tab');
    const selection = requests.at(-1).parameters;
    for (const [name, values] of Object.entries({ 'record_ids[]': ['1'], 'butir_ids[]': ['12'], 'fields[]': ['id_butir', 'isi_butir'], 'tanggapan_unit_kerja_ids[]': ['7'], 'tindak_lanjut_unit_kerja_ids[]': ['8'] })) {
        assert.deepEqual(selection.getAll(name), values);
    }
    await download('form[action$="/download-custom"] button', '/snp/report/download-custom', 'pdf');
    for (const name of ['record_ids[]', 'butir_ids[]', 'fields[]', 'tanggapan_unit_kerja_ids[]', 'tindak_lanjut_unit_kerja_ids[]']) {
        assert.deepEqual(requests.at(-1).parameters.getAll(name), selection.getAll(name));
    }
    await navigate('[data-snp-report-return]');
    assert.equal(page.url(), reportUrl);
    assert.deepEqual(await checked('#reportForm', 'record_ids[]'), ['1']);
    await page.click('#openCustomReportModalBtn');
    for (const name of ['butir_ids[]', 'fields[]', 'tanggapan_unit_kerja_ids[]', 'tindak_lanjut_unit_kerja_ids[]']) {
        assert.deepEqual(await checked('#customReportForm', name), selection.getAll(name), 'Custom selections survive returning from preview');
    }
    const requestCount = requests.length;
    await page.$$eval('#customReportForm input[name="fields[]"]', inputs => inputs.forEach(input => { input.checked = false; }));
    await page.click('#customReportForm [data-snp-pdf-preview]');
    assert.equal(requests.length, requestCount, 'Invalid custom reports are not submitted');
    assert.equal(page.url(), reportUrl);

    await page.goto(`${origin}/ragab/report/cetak`, { waitUntil: 'networkidle0' });
    assert.equal(await page.$('[data-snp-report-return]'), null);
    assert.equal(await page.$eval('a', link => new URL(link.href).pathname), '/ragab/report', 'Other modules keep their own return links');
    await page.goto(`${origin}/snp/report`, { waitUntil: 'networkidle0' });
    assert.deepEqual(await checked('#reportForm', 'record_ids[]'), [], 'Reset does not restore selections from a different filter');
    await page.evaluate(() => sessionStorage.setItem('sidewas:snp-report-navigation', '{broken'));
    await page.reload({ waitUntil: 'networkidle0' });
    assert.deepEqual(await checked('#reportForm', 'record_ids[]'), []);
    assert.deepEqual(errors, []);
    console.log('SNP regular/custom preview, browser Back, selection restoration, PDF/Excel downloads and module isolation passed.');
} finally {
    if (browser) await browser.close();
    await new Promise(resolve => server.close(resolve));
    assert.ok(resolve(downloads).startsWith(testRoot + sep));
    rmSync(downloads, { recursive: true, force: true });
}
