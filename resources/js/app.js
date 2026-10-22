import './bootstrap';
import './script';
import './report-navigation';
import './dashboard-chart';

import Alpine from 'alpinejs';
import { produkHukumReport } from './produk-hukum-report';
import { reviuBahanPage } from './reviu-bahan-preview';

window.Alpine = Alpine;
Alpine.data('produkHukumReport', produkHukumReport);
Alpine.data('reviuBahanPage', reviuBahanPage);

const hasEditor = document.querySelector('[data-snp-editor]')
    || [...document.querySelectorAll('template')].some((template) => template.innerHTML.includes('data-snp-editor'));
const editorReady = hasEditor
    ? import('./snp-butir-editor')
    : Promise.resolve();

editorReady.then(() => Alpine.start());
