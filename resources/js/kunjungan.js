import './bootstrap';
import Chart from 'chart.js/auto';
import L from 'leaflet';

const shell = document.querySelector('.app-shell');
document.querySelector('[data-sidebar-toggle]')?.addEventListener('click', () => {
    if (window.innerWidth <= 900) shell?.classList.toggle('sidebar-mobile-open');
    else shell?.classList.toggle('sidebar-collapsed');
});
document.querySelectorAll('[data-nav-toggle]').forEach((button) => button.addEventListener('click', () => button.closest('.nav-group')?.classList.toggle('open')));

const userDropdown = document.querySelector('[data-user-dropdown]');
document.querySelector('[data-user-trigger]')?.addEventListener('click', (event) => {
    event.stopPropagation();
    userDropdown?.classList.toggle('open');
});
document.addEventListener('click', () => userDropdown?.classList.remove('open'));
setTimeout(() => document.querySelector('.toast')?.classList.add('hide'), 4500);

document.querySelectorAll('[data-confirm]').forEach((form) => form.addEventListener('submit', (event) => {
    if (!window.confirm(form.dataset.confirm)) event.preventDefault();
}));
document.querySelectorAll('[data-modal-open]').forEach((button) => button.addEventListener('click', () => document.getElementById(button.dataset.modalOpen)?.classList.add('open')));
document.querySelectorAll('[data-modal-close]').forEach((button) => button.addEventListener('click', () => button.closest('.modal-backdrop')?.classList.remove('open')));
document.querySelectorAll('[data-select-filter]').forEach((input) => {
    const select = document.getElementById(input.dataset.selectFilter);
    if (!select) return;
    input.addEventListener('input', () => {
        const query = input.value.toLowerCase();
        [...select.options].forEach((option) => { option.hidden = query && !option.text.toLowerCase().includes(query); });
    });
});

document.querySelectorAll('[data-check-picker]').forEach((picker) => {
    const search = picker.querySelector('[data-picker-search]');
    const options = [...picker.querySelectorAll('[data-picker-option]')];
    const checkboxes = [...picker.querySelectorAll('[data-picker-checkbox]')];
    const chips = picker.querySelector('[data-picker-chips]');
    const count = picker.querySelector('[data-picker-count]');
    const empty = picker.querySelector('[data-picker-empty]');

    const render = () => {
        const selected = checkboxes.filter((checkbox) => checkbox.checked);
        chips.replaceChildren();
        count.textContent = `(${selected.length})`;
        empty.hidden = selected.length > 0;

        selected.forEach((checkbox) => {
            const chip = document.createElement('span');
            chip.className = 'check-picker-chip';
            chip.append(document.createTextNode(checkbox.dataset.chipLabel || checkbox.value));

            const remove = document.createElement('button');
            remove.type = 'button';
            remove.setAttribute('aria-label', `Hapus ${checkbox.dataset.chipLabel || checkbox.value}`);
            remove.textContent = '×';
            remove.addEventListener('click', () => {
                checkbox.checked = false;
                checkbox.dispatchEvent(new Event('change', {bubbles: true}));
            });
            chip.append(remove);
            chips.append(chip);
        });

        if (checkboxes[0]) {
            checkboxes[0].setCustomValidity(selected.length ? '' : (picker.dataset.validationMessage || 'Pilih minimal satu item.'));
        }
    };

    search?.addEventListener('input', () => {
        const query = search.value.trim().toLocaleLowerCase('id');
        options.forEach((option) => {
            option.hidden = Boolean(query) && !option.textContent.toLocaleLowerCase('id').includes(query);
        });
    });
    checkboxes.forEach((checkbox) => checkbox.addEventListener('change', render));
    render();
});

const chartCanvas = document.querySelector('[data-monthly-chart]');
if (chartCanvas) {
    new Chart(chartCanvas, {
        type: 'bar',
        data: {labels:['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'],datasets:[{label:'Kunjungan',data:JSON.parse(chartCanvas.dataset.monthlyChart),backgroundColor:'#4a9bd0',borderRadius:7,borderSkipped:false,maxBarThickness:34}]},
        options: {responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{x:{grid:{display:false},border:{display:false}},y:{beginAtZero:true,ticks:{precision:0},grid:{color:'#eaf0f5'},border:{display:false}}}},
    });
}

const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (char) => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[char]));
const mapNode = document.getElementById('visit-map');
if (mapNode) {
    const markers = JSON.parse(mapNode.dataset.markers || '[]');
    const tileUrl = mapNode.dataset.tileUrl || 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
    const map = L.map(mapNode, {scrollWheelZoom:false}).setView([-2.4,118], 5);
    L.tileLayer(tileUrl, {maxZoom:18,attribution:'&copy; OpenStreetMap contributors'}).addTo(map);
    const bounds = [];
    markers.forEach((item) => {
        const color = item.upcoming_count > 0 ? '#61ad7c' : (item.total > 0 ? '#2378b8' : '#9aa9b8');
        const marker = L.circleMarker([item.lat,item.lng], {radius:Math.min(11,5+Math.sqrt(item.total || 0)),fillColor:color,color:'#fff',weight:2,fillOpacity:.9}).addTo(map);
        const activity = item.total > 0 ? `<div class="popup-stats"><span>Total: <b>${item.total}</b></span><span>Tahun ini: <b>${item.year}</b></span><span>Terakhir: <b>${escapeHtml(item.last || '-')}</b></span><span>Mendatang: <b>${escapeHtml(item.next || '-')}</b></span></div>` : '<p>Belum ada riwayat kunjungan.</p>';
        marker.bindPopup(`<div class="map-popup"><h3>${escapeHtml(item.name)}</h3><p>${escapeHtml(item.city)}<br>${escapeHtml(item.province)}</p>${activity}<a class="popup-link" href="${escapeHtml(item.history_url)}">Lihat Riwayat →</a></div>`);
        bounds.push([item.lat,item.lng]);
    });
    if (bounds.length) map.fitBounds(bounds, {padding:[25,25],maxZoom:6});
}

