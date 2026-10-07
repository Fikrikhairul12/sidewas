import assert from 'node:assert/strict';
import { existsSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';
import puppeteer from 'puppeteer';

let input = '';
for await (const chunk of process.stdin) input += chunk;
const fixture = JSON.parse(input);
const executablePath = [process.env.PUPPETEER_EXECUTABLE_PATH, puppeteer.executablePath(), 'C:/Program Files/Google/Chrome/Application/chrome.exe', '/usr/bin/chromium'].find(path => path && existsSync(path));
const browser = await puppeteer.launch({ headless: true, executablePath });
try {
    for (const [name, html] of Object.entries(fixture.fixtures)) {
        const page = await browser.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.setViewport({ width: 1285, height: 816 });
        await page.setContent(html, { waitUntil: 'load' });
        await page.waitForFunction(() => window.butirReportReady === true);
        const labels = await page.$$eval('[data-report-label-id]', nodes => nodes.map(node => ({ id: node.dataset.reportLabelId, page: node.closest('tr').dataset.reportPage, visible: getComputedStyle(node).visibility === 'visible', continued: getComputedStyle(node.querySelector('.report-continuation')).visibility === 'visible' })));
        const seen = new Map();
        for (const label of labels) {
            if (seen.get(label.id) === label.page) assert.equal(label.visible, false, 'No repeated label on the same page');
            else if (seen.has(label.id)) assert.ok(label.visible && label.continued, 'Continuation identifies a butir on its next page');
            seen.set(label.id, label.page);
        }
        const cells = await page.$$eval('td[data-report-content]', nodes => nodes.map(node => ({
            width: node.clientWidth, scrollWidth: node.scrollWidth,
            images: [...node.querySelectorAll('img')].map(image => ({ complete: image.complete, width: image.clientWidth, height: image.clientHeight, natural: image.naturalWidth })),
        })));
        assert.ok(cells.length > 0);
        for (const cell of cells) {
            assert.ok(cell.scrollWidth <= cell.width + 1);
            for (const image of cell.images) assert.ok(image.complete && image.natural > 0 && image.width <= cell.width && image.height <= 560);
        }
        assert.deepEqual(errors, []);
        const bytes = await page.pdf({ format: 'Legal', landscape: true, printBackground: true, preferCSSPageSize: true });
        assert.ok(bytes.length > 10000);
        if (fixture.artifactDirectory) {
            writeFileSync(resolve(fixture.artifactDirectory, `${fixture.module}-${name}.pdf`), bytes);
            writeFileSync(resolve(fixture.artifactDirectory, `${fixture.module}-${name}.html`), html);
        }
        await page.setContent(html.replace('window.butirReportReady = false;', 'document.documentElement.style.zoom = "125%"; window.butirReportReady = false;'), { waitUntil: 'load' });
        await page.waitForFunction(() => window.butirReportReady === true);
        const zoomedLabels = await page.$$eval('[data-report-label-id]', nodes => nodes.map(node => ({ id: node.dataset.reportLabelId, page: node.closest('tr').dataset.reportPage, visible: getComputedStyle(node).visibility === 'visible', continued: getComputedStyle(node.querySelector('.report-continuation')).visibility === 'visible' })));
        assert.deepEqual(zoomedLabels, labels, 'Preview zoom does not change continuation labels');
        await page.close();
    }
    console.log(`${fixture.module}: actual Chromium print, bounded text and complete embedded images passed.`);
} finally {
    await browser.close();
}
