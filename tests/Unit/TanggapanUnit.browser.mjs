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
    const pageIndex = /^\/(\d+)$/.exec(request.url)?.[1];
    if (pageIndex !== undefined) {
        response.setHeader('Content-Type', 'text/html; charset=utf-8');
        response.end(fixture.pages[Number(pageIndex)].html.replace('</head>', `<link rel="stylesheet" href="/build/${fixture.css}"><script type="module" src="/build/${fixture.js}"></script></head>`));
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
    process.env.PUPPETEER_EXECUTABLE_PATH, await puppeteer.executablePath(),
    'C:/Program Files/Google/Chrome/Application/chrome.exe',
    'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', '/usr/bin/chromium',
].find(candidate => candidate && existsSync(candidate));
let browser;
try {
    browser = await puppeteer.launch({ headless: true, executablePath });
    const page = await browser.newPage();
    page.setDefaultTimeout(5000);
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.setViewport({ width: 1366, height: 900 });
    await page.setRequestInterception(true);
    page.on('request', request => {
        if (request.url().startsWith(`http://127.0.0.1:${server.address().port}/`) || request.url().startsWith('data:')) request.continue();
        else request.abort();
    });
    for (const [index, data] of fixture.pages.entries()) {
        await page.goto(`http://127.0.0.1:${server.address().port}/${index}`, { waitUntil: 'networkidle0' });
        await page.waitForFunction(() => window.Alpine && document.querySelector('[x-data^="tanggapan"]')?._x_dataStack);
        assert.deepEqual(errors, [], `${data.mode}: page must initialize without Alpine errors`);
        assert.equal((await page.$$('[data-beri-tanggapan]')).length, ['pending', 'new-round'].includes(data.mode) ? 1 : 0);
        assert.equal((await page.$$('tbody .snp-butir-read-button')).length, 0);
        await page.click('[data-tanggapan-detail]');
        await page.waitForSelector('[x-show="openDetailModal"]', { visible: true });
        assert.ok(await page.$eval('[x-show="openDetailModal"] .snp-rich-content', node => node.textContent.includes('Isi butir lama')));
        if (data.mode === 'answered') {
            await page.waitForSelector('[data-edit-tanggapan]', { visible: true });
            await page.click('[data-edit-tanggapan]');
            await page.waitForSelector('[x-show="openEditModal"]', { visible: true });
            assert.equal(await page.$eval('[data-tanggapan-edit] textarea[name="tanggapan"]', node => node.value), 'Jawaban unit A');
            assert.ok((await page.$eval('[data-tanggapan-edit]', node => node.action)).endsWith(`/${fixture.module}/tanggapan/${data.responseId}`));
            assert.equal((await page.$$('[data-tanggapan-edit] .tiptap')).length, 0);
        } else {
            assert.equal(await page.$eval('[data-edit-tanggapan]', node => getComputedStyle(node).display), 'none');
        }
        if (data.mode === 'pending') {
            await page.click('[data-unit-next]');
            assert.equal(await page.$eval('[x-show="openDetailModal"]', node => node.innerText.includes('B - Unit B')), true);
        }
        if (['pending', 'new-round'].includes(data.mode)) {
            await page.$eval('[x-show="openDetailModal"]', node => {
                window.Alpine.$data(node).openDetailModal = false;
            });
            await page.waitForSelector('[x-show="openDetailModal"]', { hidden: true });
            await page.click('[data-beri-tanggapan]');
            await page.waitForSelector('[x-show="openModal"]', { visible: true });
            await page.waitForFunction(() => document.querySelectorAll('[data-tanggapan-create] select option').length > 1);
            const options = await page.$$eval('[data-tanggapan-create] select option', nodes => nodes.filter(node => node.value).map(node => Number(node.value)));
            assert.deepEqual(options, [data.picId]);
            assert.equal(await page.$eval('[data-tanggapan-create] [name="putaran_id"]', node => node.value), String(data.roundId ?? ''));
            const fields = await page.$eval('[data-tanggapan-create]', (form, picId) => {
                form.querySelector('select').value = picId;
                form.querySelector('[name="tanggapan"]').value = 'Draf baru';
                return Object.fromEntries(new FormData(form).entries());
            }, String(data.picId));
            assert.equal(fields.butir_pic_id, String(data.picId));
            assert.equal(fields.tanggapan, 'Draf baru');
        }
        assert.deepEqual(errors, [], `${data.mode}: modal navigation must have no JavaScript errors`);
    }
} finally {
    await browser?.close();
    await new Promise(resolve => server.close(resolve));
}
