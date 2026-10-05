import './bootstrap';
import './script';
import './dashboard-chart';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

const editorReady = document.querySelector('[data-snp-editor]')
    ? import('./snp-butir-editor')
    : Promise.resolve();

editorReady.then(() => Alpine.start());
