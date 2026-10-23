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
    const path = resolve(buildRoot, decodeURIComponent(request.url).replace(/^\/build\//, ''));
    if (!path.startsWith(buildRoot + sep) || !existsSync(path)) {
        response.writeHead(404).end();
        return;
    }
    response.setHeader('Content-Type', path.endsWith('.css') ? 'text/css' : 'application/javascript');
    response.end(readFileSync(path));
});
await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
const executablePath = [
    process.env.PUPPETEER_EXECUTABLE_PATH, await puppeteer.executablePath(),
    'C:/Program Files/Google/Chrome/Application/chrome.exe',
    'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', '/usr/bin/chromium',
].find(path => path && existsSync(path));
let browser;
try {
    browser = await puppeteer.launch({ headless: true, executablePath });
    const page = await browser.newPage();
    page.setDefaultTimeout(5000);
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.setRequestInterception(true);
    page.on('request', request => {
        if (request.url().startsWith(`http://127.0.0.1:${server.address().port}/`) || request.url().startsWith('data:')) request.continue();
        else request.abort();
    });
    await page.setViewport({ width: 1366, height: 900 });
    for (const [index, data] of fixture.pages.entries()) {
        await page.goto(`http://127.0.0.1:${server.address().port}/${index}`, { waitUntil: 'networkidle0' });
        await page.waitForFunction(() => window.Alpine && document.querySelector('[x-data="isianUnitTahapPage()"]')?._x_dataStack);
        assert.deepEqual(errors, [], `${data.mode}: Alpine initialization`);
        assert.equal((await page.$$('[data-beri-isian]')).length, data.canCreate ? 1 : 0);
        assert.equal((await page.$$('tbody .snp-butir-read-button')).length, 0);
        if (data.canCreate) {
            await page.click('[data-beri-isian]');
            await page.waitForSelector('[data-isian-create-modal]', { visible: true });
            assert.equal(await page.$eval('[data-isian-create] input[name="butir_id"]', node => node.value), String(data.butirId));
            assert.equal(await page.$eval('[data-isian-create] input[name="tahap_id"]', node => node.value), String(data.stageId ?? ''));
            assert.equal(await page.$eval('[data-isian-create] select', node => node.value), String(data.picId));
            assert.equal((await page.$$('[data-isian-create] textarea')).length, 2);
            await page.keyboard.press('Escape');
            await page.waitForSelector('[data-isian-create-modal]', { hidden: true });
        }
        await page.click('[data-isian-detail]');
        await page.waitForSelector('[data-isian-detail-modal]', { visible: true });
        await page.waitForFunction(() => getComputedStyle(document.querySelector('[data-isian-detail-modal]')).opacity === '1');
        assert.ok(await page.$eval('[data-isian-detail-modal] .snp-rich-content', node => node.textContent.includes('Isi butir lama')));
        if (data.oldStageId) {
            const options = await page.$$eval('[data-isian-stage] option', nodes => nodes.map(node => ({value: node.value, text: node.textContent})));
            assert.ok(options.some(option => option.text.includes('Putaran 1')));
            assert.ok(options.some(option => option.text.includes('Putaran 2')));
            await page.select('[data-isian-stage]', '1');
            await page.waitForSelector('[data-isian-locked]', { visible: true });
            assert.equal(await page.$eval('[data-edit-via-detail]', node => getComputedStyle(node).display), 'none');
            await page.select('[data-isian-stage]', '0');
        }
        if (data.canEdit) {
            await page.waitForSelector('[data-edit-via-detail]', { visible: true });
            await page.click('[data-edit-via-detail]');
            await page.waitForSelector('[data-isian-edit-modal]', { visible: true });
            assert.ok((await page.$eval('[data-isian-edit]', node => node.action)).endsWith(data.editPath));
            assert.equal(await page.$eval('[data-isian-edit] textarea[name="isi"]', node => node.value), data.content);
            await page.keyboard.press('Escape');
            await page.waitForSelector('[data-isian-edit-modal]', { hidden: true });
            await page.click('[data-isian-detail]');
            await page.waitForSelector('[data-isian-detail-modal]', { visible: true });
        }
        if (data.mode === 'late') {
            await page.click('[data-isian-unit-next]');
            await page.waitForSelector('[data-isian-after-close]', { visible: true });
            await page.waitForSelector('[data-isian-not-compiled]', { visible: true });
            assert.equal(await page.$eval('[data-edit-via-detail]', node => getComputedStyle(node).display), 'none');
        }
        if (data.mode === 'latest') {
            await page.waitForFunction(() => getComputedStyle(document.querySelector('[data-isian-detail-modal]')).opacity === '1');
            if (fixture.screenshot) await page.screenshot({ path: fixture.screenshot, fullPage: true });
            await page.setViewport({ width: 390, height: 844 });
            await page.waitForFunction(() => getComputedStyle(document.querySelector('header').parentElement).paddingLeft === '0px');
            if (fixture.screenshot) await page.screenshot({ path: fixture.screenshot.replace('.png', '-mobile.png'), fullPage: true });
            const overflow = await page.evaluate(() => [...document.querySelectorAll('body *')]
                .filter(node => getComputedStyle(node).position !== 'fixed' && node.getBoundingClientRect().right > innerWidth + 2)
                .map(node => ({ tag: node.tagName, class: node.className, right: Math.round(node.getBoundingClientRect().right) })).slice(0, 15));
            assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 2), JSON.stringify(overflow));
            await page.setViewport({ width: 1366, height: 900 });
        }
        assert.deepEqual(errors, [], `${data.mode}: navigation and editing`);
    }
    process.stdout.write('R2 browser: create, Detail, editing, history locking and late markers passed.\n');
} finally {
    await browser?.close();
    await new Promise(resolve => server.close(resolve));
}
