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
    if (fixture.images?.[request.url]) {
        response.setHeader('Content-Type', 'image/png');
        response.end(Buffer.from(fixture.images[request.url], 'base64'));
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
        const canCreate = ['pending', 'new-round', 'closed-pending'].includes(data.mode);
        await page.goto(`http://127.0.0.1:${server.address().port}/${index}`, { waitUntil: 'networkidle0' });
        await page.waitForFunction(() => window.Alpine && document.querySelector('[x-data^="tanggapan"]')?._x_dataStack);
        assert.deepEqual(errors, [], `${data.mode}: page must initialize without Alpine errors`);
        assert.equal((await page.$$('[data-beri-tanggapan]')).length, canCreate ? 1 : 0);
        assert.equal((await page.$$('tbody .snp-butir-read-button')).length, 0);
        await page.click('[data-tanggapan-detail]');
        await page.waitForSelector('[x-show="openDetailModal"]', { visible: true });
        await page.waitForFunction(() => getComputedStyle(document.querySelector('[x-show="openDetailModal"]')).opacity === '1');
        assert.ok(await page.$eval('[x-show="openDetailModal"] .snp-rich-content', node => node.textContent.includes('Isi butir lama')));
        if (data.mode.startsWith('closed-')) {
            await page.waitForSelector('[data-surat-closed]', { visible: true });
            assert.ok(await page.$eval('[data-surat-closed]', node => node.innerText.includes('Close:')));
        }
        if (data.historyRounds) {
            await page.waitForSelector('[data-tanggapan-round]', { visible: true });
            assert.equal(await page.$eval('[data-tanggapan-round]', node => node.value), String(data.currentRoundId));
            assert.equal((await page.$$('[data-tanggapan-round] option')).length, 3);
            for (const round of data.historyRounds) {
                await page.select('[data-tanggapan-round]', String(round.id));
                await page.waitForFunction(id => String(window.Alpine.$data(document.querySelector('[x-show="openDetailModal"]')).detailRound.id) === String(id), {}, round.id);
                await page.waitForSelector('[data-round-readonly]', { visible: true });
                await page.waitForFunction(text => document.querySelector('[x-show="openDetailModal"]').innerText.includes(text), {}, round.response);
                assert.equal(await page.$eval('[data-edit-tanggapan]', node => getComputedStyle(node).display), 'none');
                assert.ok(await page.$eval('[data-history-kompilasi] strong', node => node.textContent.includes('Kompilasi lama')));
                assert.ok(await page.$eval('[data-history-reviu] em', node => node.textContent.includes('Perbaikan lama')));
                await page.waitForFunction(() => [...document.querySelectorAll('[data-history-kompilasi] img')].every(node => node.complete && node.naturalWidth > 0));
                assert.ok((await page.$$('[data-history-kompilasi] img[data-width="50"]')).length > 0);
                if (fixture.screenshot) await page.screenshot({ path: fixture.screenshot });
            }
            await page.select('[data-tanggapan-round]', String(data.currentRoundId));
            await page.waitForSelector('[data-round-readonly]', { hidden: true });
            assert.equal((await page.$$('[data-history-kompilasi]')).length, 0);
            assert.equal((await page.$$('[data-history-reviu]')).length, 0);
        }
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
        if (['pending', 'closed-pending', 'closed-late'].includes(data.mode)) {
            await page.click('[data-unit-next]');
            assert.equal(await page.$eval('[x-show="openDetailModal"]', node => node.innerText.includes('B - Unit B')), true);
        }
        if (data.mode === 'closed-late') {
            await page.waitForSelector('[data-after-close]', { visible: true });
            assert.ok(await page.$eval('[data-after-close]', node => node.innerText.includes('tidak termasuk bahan keputusan reviu')));
            assert.equal(await page.$eval('[data-edit-tanggapan]', node => getComputedStyle(node).display), 'none');
            if (fixture.screenshot) await page.screenshot({ path: fixture.screenshot });
        }
        if (canCreate) {
            await page.$eval('[x-show="openDetailModal"]', node => {
                window.Alpine.$data(node).openDetailModal = false;
            });
            await page.waitForSelector('[x-show="openDetailModal"]', { hidden: true });
            await page.click('[data-beri-tanggapan]');
            await page.waitForSelector('[x-show="openModal"]', { visible: true });
            if (data.mode === 'closed-pending') {
                await page.waitForSelector('[data-after-close-form]', { visible: true });
                assert.ok(await page.$eval('[data-after-close-form]', node => node.innerText.includes('Tanggapan setelah penutupan')));
            }
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
