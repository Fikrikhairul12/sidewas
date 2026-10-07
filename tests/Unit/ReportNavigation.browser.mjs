import assert from 'node:assert/strict';
import { existsSync, mkdirSync, mkdtempSync, readFileSync, rmSync } from 'node:fs';
import { createServer } from 'node:http';
import { join, resolve, sep } from 'node:path';
import puppeteer from 'puppeteer';

let input = '';
for await (const chunk of process.stdin) input += chunk;
const fixture = JSON.parse(input);
const module = fixture.module;
const reportPath = `/${module}/report`;
const downloadPath = `${reportPath}/${module === 'snp' ? 'download' : 'cetak'}`;
const selectionFields = fixture.fields;
const selectionNames = ['butir_ids[]', 'fields[]', 'tanggapan_unit_kerja_ids[]', 'tindak_lanjut_unit_kerja_ids[]'];
const buildRoot = resolve('public/build');
const requests = [];
const testRoot = resolve('storage/framework/testing');
mkdirSync(testRoot, { recursive: true });
const downloads = mkdtempSync(join(testRoot, `${module}-report-navigation-`));
const html = name => `<!doctype html><html><head><meta charset="utf-8"><link rel="stylesheet" href="/build/${fixture.css}"><script type="module" src="/build/${fixture.js}"></script></head><body>${fixture.fixtures[name].replaceAll('http://localhost', '')}</body></html>`;
const server = createServer(async (request, response) => {
    const path = new URL(request.url, 'http://localhost').pathname;
    if (request.method === 'POST') {
        let body = '';
        for await (const chunk of request) body += chunk;
        const parameters = new URLSearchParams(body);
        requests.push({ path, parameters });
        if (path.includes('download') || path.includes('excel') || parameters.get('_download') === '1') {
            const type = path.includes('excel') ? 'excel' : 'pdf';
            response.setHeader('Content-Type', type === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            response.setHeader('Content-Disposition', `attachment; filename="report-${module}.${type === 'pdf' ? 'pdf' : 'xlsx'}"`);
            response.end(Buffer.from(fixture[type], 'base64'));
        } else {
            response.setHeader('Content-Type', 'text/html; charset=utf-8');
            response.end(html(path.endsWith('custom') ? 'custom' : 'regular'));
        }
        return;
    }
    if (path === reportPath || path === `/${fixture.otherModule}/report/cetak`) {
        response.setHeader('Content-Type', 'text/html; charset=utf-8');
        response.end(html(path === reportPath ? 'index' : 'other'));
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
const reportUrl = `${origin}${reportPath}?keyword=SURAT&status=dalam_proses&page=2`;
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
        const file = join(downloads, `report-${module}.${extension}`);
        const expectedBytes = Buffer.from(fixture[extension === 'pdf' ? 'pdf' : 'excel'], 'base64');
        for (let attempt = 0; attempt < 100; attempt++) {
            if (existsSync(file) && readFileSync(file).equals(expectedBytes)) break;
            await new Promise(resolve => setTimeout(resolve, 50));
        }
        assert.ok(existsSync(file), `${module} ${path}: download completes`);
        const bytes = readFileSync(file);
        assert.deepEqual(bytes, expectedBytes, `${module} ${path}: download contains the complete file`);
        assert.ok(bytes.subarray(0, 4).equals(extension === 'pdf' ? Buffer.from('%PDF') : Buffer.from([0x50, 0x4b, 0x03, 0x04])), `${module} ${path}: valid file signature`);
        rmSync(file);
        assert.equal((await context.pages()).length, tabCount, 'Download retains the current tab');
    };

    await page.click('#reportForm input[value="1"]');
    await page.click('#openReportFormatModalBtn');
    await download('button[form="reportForm"][formaction$="cetak-excel"]', `${reportPath}/cetak-excel`, 'xlsx');
    assert.equal(page.url(), reportUrl, 'Regular Excel remains a direct download');
    await navigate('[data-report-pdf-preview][form="reportForm"]');
    assert.equal(new URL(page.url()).pathname, `${reportPath}/cetak`);
    assert.equal((await context.pages()).length, tabCount, 'Regular preview reuses the current tab');
    assert.equal(await page.$eval('[data-report-return]', link => link.href), reportUrl);
    await download(`form[action$="${downloadPath}"] button`, downloadPath, 'pdf');
    assert.equal(new URL(page.url()).pathname, `${reportPath}/cetak`);
    assert.deepEqual(requests.at(-1).parameters.getAll('record_ids[]'), ['1']);
    assert.equal(requests.at(-1).parameters.get('_download'), '1');
    await navigate('[data-report-return]');
    assert.equal(page.url(), reportUrl);
    assert.deepEqual(await checked('#reportForm', 'record_ids[]'), ['1']);
    assert.equal(await page.$eval('input[name="keyword"]', input => input.value), 'SURAT');
    assert.equal(await page.$eval('select[name="status"]', input => input.value), 'dalam_proses');
    assert.equal(await page.$eval('#reportFormatModal', modal => modal.classList.contains('hidden')), true);
    if (await page.$('#selectAllReportRecords')) {
        assert.deepEqual(await page.$eval('#selectAllReportRecords', input => [input.checked, input.indeterminate]), [false, true], 'Select all reflects the restored partial selection');
        await page.click('#selectAllReportRecords');
        assert.deepEqual(await checked('#reportForm', 'record_ids[]'), ['1', '2']);
        await page.click('#reportForm input[value="2"]');
    }

    await page.click('#openReportFormatModalBtn');
    await navigate('[data-report-pdf-preview][form="reportForm"]');
    await page.goBack({ waitUntil: 'networkidle0' });
    assert.equal(page.url(), reportUrl);
    assert.deepEqual(await checked('#reportForm', 'record_ids[]'), ['1'], 'Browser Back also restores selection');
    assert.equal(await page.$eval('#reportFormatModal', modal => modal.classList.contains('hidden')), true);

    await page.click('#openCustomReportModalBtn');
    await page.click('#customReportForm input[name="butir_ids[]"][value="11"]');
    if (module === 'snp') {
        await page.click('#customReportForm input[name="tanggapan_unit_kerja_ids[]"][value="8"]');
        await page.click('#customReportForm input[name="tindak_lanjut_unit_kerja_ids[]"][value="7"]');
    }
    await page.$$eval('#customReportForm input[name="fields[]"]', (inputs, fields) => inputs.forEach(input => { input.checked = fields.includes(input.value); }), selectionFields);
    await download('#customReportForm button[formaction$="cetak-excel-custom"]', `${reportPath}/cetak-excel-custom`, 'xlsx');
    assert.equal(page.url(), reportUrl, 'Custom Excel remains a direct download');
    await navigate('#customReportForm [data-report-pdf-preview]');
    assert.equal(new URL(page.url()).pathname, `${reportPath}/cetak-custom`);
    assert.equal((await context.pages()).length, tabCount, 'Custom preview reuses the current tab');
    const selection = requests.at(-1).parameters;
    for (const [name, values] of Object.entries({ 'record_ids[]': ['1'], 'butir_ids[]': ['12'], 'fields[]': selectionFields, 'tanggapan_unit_kerja_ids[]': module === 'snp' ? ['7'] : [], 'tindak_lanjut_unit_kerja_ids[]': module === 'snp' ? ['8'] : [] })) {
        assert.deepEqual(selection.getAll(name), values);
    }
    await download(`form[action$="${downloadPath}-custom"] button`, `${downloadPath}-custom`, 'pdf');
    assert.equal(requests.at(-1).parameters.get('_download'), '1');
    for (const name of ['record_ids[]', ...selectionNames]) {
        assert.deepEqual(requests.at(-1).parameters.getAll(name), selection.getAll(name));
    }
    await navigate('[data-report-return]');
    assert.equal(page.url(), reportUrl);
    assert.deepEqual(await checked('#reportForm', 'record_ids[]'), ['1']);
    await page.click('#openCustomReportModalBtn');
    for (const name of selectionNames) {
        assert.deepEqual(await checked('#customReportForm', name), selection.getAll(name), 'Custom selections survive returning from preview');
    }
    const requestCount = requests.length;
    await page.$$eval('#customReportForm input[name="fields[]"]', inputs => inputs.forEach(input => { input.checked = false; }));
    await page.click('#customReportForm [data-report-pdf-preview]');
    assert.equal(requests.length, requestCount, 'Invalid custom reports are not submitted');
    assert.equal(page.url(), reportUrl);

    const otherUrl = `${origin}/${fixture.otherModule}/report?keyword=OTHER&page=3`;
    await page.evaluate(({ otherModule, url }) => sessionStorage.setItem(`sidewas:${otherModule}-report-navigation`, JSON.stringify({ url, recordIds: ['2'] })), { otherModule: fixture.otherModule, url: new URL(otherUrl).pathname + new URL(otherUrl).search });
    await page.goto(`${origin}/${fixture.otherModule}/report/cetak`, { waitUntil: 'networkidle0' });
    assert.equal(await page.$eval('[data-report-return]', link => link.href), otherUrl, 'Other modules restore their own filters independently');
    await page.evaluate(({ module, url }) => sessionStorage.setItem(`sidewas:${module}-report-navigation`, JSON.stringify({ url, recordIds: ['1'] })), { module: fixture.otherModule, url: reportUrl });
    await page.reload({ waitUntil: 'networkidle0' });
    assert.equal(await page.$eval('[data-report-return]', link => new URL(link.href).pathname), `/${fixture.otherModule}/report`, 'Return links reject saved addresses for a different module');
    await page.goto(`${origin}${reportPath}`, { waitUntil: 'networkidle0' });
    assert.deepEqual(await checked('#reportForm', 'record_ids[]'), [], 'Reset does not restore selections from a different filter');
    await page.evaluate(module => sessionStorage.setItem(`sidewas:${module}-report-navigation`, '{broken'), module);
    await page.reload({ waitUntil: 'networkidle0' });
    assert.deepEqual(await checked('#reportForm', 'record_ids[]'), []);
    assert.deepEqual(errors, []);
    console.log(`${module} regular/custom preview, browser Back, selection restoration, PDF/Excel downloads and module isolation passed.`);
} finally {
    if (browser) await browser.close();
    await new Promise(resolve => server.close(resolve));
    assert.ok(resolve(downloads).startsWith(testRoot + sep));
    rmSync(downloads, { recursive: true, force: true });
}
