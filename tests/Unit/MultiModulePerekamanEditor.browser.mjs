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
    if (request.url === '/') {
        response.setHeader('Content-Type', 'text/html; charset=utf-8');
        response.end(fixture.html.replace('</head>', `<link rel="stylesheet" href="/build/${fixture.css}"><script type="module" src="/build/${fixture.js}"></script></head>`));
        return;
    }
    if (request.url === fixture.image) {
        response.setHeader('Content-Type', 'image/png');
        response.end(Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jJ1kAAAAASUVORK5CYII=', 'base64'));
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
const executablePath = [process.env.PUPPETEER_EXECUTABLE_PATH, await puppeteer.executablePath(),
    'C:/Program Files/Google/Chrome/Application/chrome.exe',
    'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',
    '/usr/bin/chromium', '/usr/bin/google-chrome',
].find(candidate => candidate && existsSync(candidate));
let browser;
try {
    browser = await puppeteer.launch({ headless: true, executablePath });
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.setViewport({ width: 1440, height: 1000 });
    await page.goto(`http://127.0.0.1:${server.address().port}`, { waitUntil: 'networkidle0' });
    const modal = name => `div[x-show="${name}"]`;
    const clickAction = async (prefix, scope = 'body') => {
        await page.$eval(scope, (root, prefix) => {
            const button = [...root.querySelectorAll('button')].find(node => node.getAttribute('@click')?.startsWith(prefix));
            if (!button) throw new Error(`Missing action ${prefix}`);
            button.click();
        }, prefix);
    };
    const detail = modal('openDetailModal');
    await clickAction('openDetailModalFor(');
    await page.waitForSelector(detail, { visible: true });
    await page.$eval(detail, (root, label) => {
        const button = [...root.querySelectorAll('button')].find(node => node.getAttribute('@click') === 'selectDetailButir(butir)' && node.textContent.includes(label));
        if (!button) throw new Error('Missing rich butir in detail');
        button.click();
    }, fixture.butirLabel);
    await page.waitForFunction(selector => document.querySelector(`${selector} .snp-rich-content strong`)?.textContent === 'Butir baru', {}, detail);
    assert.equal(await page.$eval(`${detail} .snp-rich-content img`, node => node.dataset.width), '25');
    await page.$eval(`${detail} .snp-butir-read-button`, button => button.click());
    await page.waitForFunction(() => document.getElementById('snpButirReader').open);
    assert.equal(await page.$eval('[data-snp-reader-content] strong', node => node.textContent), 'Butir baru');
    await page.keyboard.press('Escape');
    await page.waitForFunction(() => !document.getElementById('snpButirReader').open);
    assert.equal(await page.$eval(detail, node => getComputedStyle(node).display !== 'none'), true);
    await clickAction('openDetailModal = false', detail);
    await page.waitForSelector(detail, { hidden: true });

    const edit = modal('openEditModal');
    await clickAction('openEditModalFor(');
    await page.waitForSelector(`${edit} .tiptap`, { visible: true });
    await page.select(`${edit} select[name="butir_id"]`, String(fixture.butirId));
    await page.waitForFunction(selector => document.querySelector(`${selector} .tiptap`)?.editor?.getText().includes('Butir baru'), {}, edit);
    assert.equal(await page.$eval(`${edit} .tiptap img`, node => node.dataset.width), '25');
    const values = async () => page.$eval(`${edit} form`, (form, field) => [...new FormData(form).entries()].filter(([name]) => name !== field), fixture.field);
    const before = await values();
    await page.$eval(`${edit} .tiptap`, node => node.editor.commands.insertContent(' Draf halaman asli'));
    await page.waitForFunction((selector, field) => document.querySelector(`${selector} input[name="${field}"]`).value.includes('Draf halaman asli'), {}, edit, fixture.field);
    assert.deepEqual(await values(), before);
    assert.equal(await page.$eval(`${edit} input[name="_method"]`, node => node.value), 'PATCH');
    const legacyId = await page.$eval(`${edit} select[name="butir_id"]`, (node, id) => [...node.options].find(option => option.value && option.value !== String(id)).value, fixture.butirId);
    await page.select(`${edit} select[name="butir_id"]`, legacyId);
    await page.waitForFunction(selector => document.querySelector(`${selector} .tiptap`)?.editor?.getText().includes('Teks lama <literal>'), {}, edit);
    assert.equal(await page.$eval(`${edit} .tiptap`, node => node.querySelectorAll('literal').length), 0);
    await page.select(`${edit} select[name="butir_id"]`, String(fixture.butirId));
    await page.waitForFunction(selector => document.querySelector(`${selector} .tiptap`)?.editor?.getText().includes('Draf halaman asli'), {}, edit);
    if (process.env.SNP_READER_SCREENSHOTS) await page.screenshot({ path: resolve(process.env.SNP_READER_SCREENSHOTS, `${fixture.module}-editor-page.png`) });
    await clickAction('openEditModal = false', edit);
    await page.waitForSelector(edit, { hidden: true });

    const add = modal('openButirModal');
    await clickAction('openButirModalFor(');
    await page.waitForSelector(`${add} .tiptap`, { visible: true });
    await page.waitForFunction(selector => !!document.querySelector(`${selector} .tiptap`)?.editor, {}, add);
    assert.equal(await page.$eval(`${add} .tiptap`, node => node.editor.getText()), '');
    await page.$eval(`${add} .tiptap`, node => node.editor.commands.insertContent('Butir tambahan'));
    await page.waitForFunction((selector, field) => document.querySelector(`${selector} input[name="${field}"]`).value.includes('Butir tambahan'), {}, add, fixture.field);
    assert.equal(await page.$eval(`${add} input[name="editor_record_id"]`, node => node.value), String(fixture.recordId));
    assert.equal(await page.$eval(`${add} input[name="${fixture.field}"]`, node => node.value.startsWith('<!--snp-rich:v1-->')), true);
    assert.deepEqual(errors, []);
    console.log(`${fixture.module}: actual page create/edit forms, draft switching, rich detail and reader passed.`);
} finally {
    await browser?.close();
    await new Promise(resolve => server.close(resolve));
}
