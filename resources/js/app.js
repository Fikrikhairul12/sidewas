import './bootstrap';
import './script';
import './snp-report-navigation';
import './dashboard-chart';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

const hasEditor = document.querySelector('[data-snp-editor]')
    || [...document.querySelectorAll('template')].some((template) => template.innerHTML.includes('data-snp-editor'));
const editorReady = hasEditor
    ? import('./snp-butir-editor')
    : Promise.resolve();

editorReady.then(() => Alpine.start());
