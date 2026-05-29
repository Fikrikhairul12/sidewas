window.perekamanSnpModal = function (clusters = [], direktorats = []) {
    return {
        openCreateModal: false,

        selectedClusterId: '',
        selectedDirektoratUtamaId: '',
        selectedDirektoratPendukungId: '',

        clusters: clusters,
        direktorats: direktorats,

        get filteredSubClusters() {
            const cluster = this.clusters.find(item => String(item.id) === String(this.selectedClusterId));
            return cluster ? cluster.sub_clusters : [];
        },

        get filteredUnitKerjaUtama() {
            const direktorat = this.direktorats.find(item => String(item.id) === String(this.selectedDirektoratUtamaId));
            return direktorat ? direktorat.unit_kerja : [];
        },

        get filteredUnitKerjaPendukung() {
            const direktorat = this.direktorats.find(item => String(item.id) === String(this.selectedDirektoratPendukungId));
            return direktorat ? direktorat.unit_kerja : [];
        },
    };
};

window.perekamanSnpModal = function (clusters = [], direktorats = []) {
    return {
        openCreateModal: false,
        openButirModal: false,

        selectedRecord: null,

        selectedClusterId: '',
        selectedDirektoratUtamaId: '',

        picPendukungSearch: '',
        selectedPicPendukung: [],

        clusters: clusters,
        direktorats: direktorats,

        openButirModalFor(record) {
            this.selectedRecord = record;
            this.selectedDirektoratUtamaId = '';
            this.picPendukungSearch = '';
            this.selectedPicPendukung = [];
            this.openButirModal = true;
        },

        get filteredSubClusters() {
            const cluster = this.clusters.find(item => String(item.id) === String(this.selectedClusterId));
            return cluster ? cluster.sub_clusters : [];
        },

        get filteredUnitKerjaUtama() {
            const direktorat = this.direktorats.find(item => String(item.id) === String(this.selectedDirektoratUtamaId));
            return direktorat ? direktorat.unit_kerja : [];
        },

        get allUnitKerjaPendukung() {
            return this.direktorats.flatMap(direktorat => {
                return (direktorat.unit_kerja || []).map(unit => ({
                    ...unit,
                    direktorat_nama: direktorat.nama_direktorat,
                }));
            });
        },

        get filteredAllUnitKerjaPendukung() {
            const keyword = this.picPendukungSearch.toLowerCase().trim();

            if (!keyword) {
                return this.allUnitKerjaPendukung;
            }

            return this.allUnitKerjaPendukung.filter(unit => {
                const kode = String(unit.kode_unit || '').toLowerCase();
                const nama = String(unit.nama_unit || '').toLowerCase();
                const direktorat = String(unit.direktorat_nama || '').toLowerCase();

                return kode.includes(keyword)
                    || nama.includes(keyword)
                    || direktorat.includes(keyword);
            });
        },

        get selectedPicPendukungDetail() {
            return this.allUnitKerjaPendukung.filter(unit => {
                return this.selectedPicPendukung.includes(String(unit.id));
            });
        },

        removePicPendukung(id) {
            this.selectedPicPendukung = this.selectedPicPendukung.filter(item => String(item) !== String(id));
        },
    };
};

document.addEventListener('DOMContentLoaded', () => {
    const openCustomReportModalBtn = document.getElementById('openCustomReportModalBtn');
    const closeCustomReportModalBtn = document.getElementById('closeCustomReportModalBtn');
    const cancelCustomReportModalBtn = document.getElementById('cancelCustomReportModalBtn');

    const customReportModal = document.getElementById('customReportModal');
    const customReportRecordIds = document.getElementById('customReportRecordIds');
    const customReportButirList = document.getElementById('customReportButirList');
    const customReportForm = document.getElementById('customReportForm');

    const selectAllCustomButirBtn = document.getElementById('selectAllCustomButirBtn');
    const selectAllCustomFieldsBtn = document.getElementById('selectAllCustomFieldsBtn');

    if (!customReportModal || !customReportRecordIds || !customReportButirList || !customReportForm) {
        return;
    }

    const closeCustomReportModal = () => {
        customReportModal.classList.add('hidden');
        customReportModal.classList.remove('flex');
    };

    const escapeHtml = (value) => {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    };

    const renderButirOptions = (checkedRecords) => {
        customReportButirList.innerHTML = '';

        let hasButir = false;

        checkedRecords.forEach((checkbox) => {
            const recordLabel = checkbox.dataset.recordLabel || 'Surat SNP';
            let butirs = [];

            try {
                butirs = JSON.parse(checkbox.dataset.butirs || '[]');
            } catch (error) {
                butirs = [];
            }

            const group = document.createElement('div');
            group.className = 'rounded-xl border border-slate-200 bg-white p-4';

            const title = document.createElement('p');
            title.className = 'mb-3 text-xs font-bold uppercase tracking-wide text-slate-500';
            title.textContent = recordLabel;

            group.appendChild(title);

            if (butirs.length === 0) {
                const empty = document.createElement('p');
                empty.className = 'text-sm text-slate-400';
                empty.textContent = 'Surat ini belum memiliki butir SNP.';
                group.appendChild(empty);
            }

            butirs.forEach((butir) => {
                hasButir = true;

                const label = document.createElement('label');
                label.className = 'mb-2 flex cursor-pointer items-start gap-3 rounded-lg border border-slate-100 px-3 py-2 text-sm hover:bg-blue-50';

                const input = document.createElement('input');
                input.type = 'checkbox';
                input.name = 'butir_ids[]';
                input.value = butir.id;
                input.checked = true;
                input.className = 'custom-butir-checkbox mt-1 rounded border-slate-300 text-blue-600 focus:ring-blue-500';

                const span = document.createElement('span');
                span.className = 'text-slate-700';
                span.innerHTML = `
                    <span class="font-bold" style="color:#2377b9;">${escapeHtml(butir.id_butir_snp ?? '-')}</span>
                    <br>
                    <span class="text-xs">${escapeHtml(butir.butir_snp ?? '-')}</span>
                `;

                label.appendChild(input);
                label.appendChild(span);
                group.appendChild(label);
            });

            customReportButirList.appendChild(group);
        });

        if (!hasButir) {
            customReportButirList.innerHTML = `
                <p class="text-sm text-red-500">
                    Surat yang dipilih belum memiliki butir SNP.
                </p>
            `;
        }
    };

    const openCustomReportModal = () => {
        const checkedRecords = document.querySelectorAll('input[name="record_ids[]"]:checked');

        customReportRecordIds.innerHTML = '';

        if (checkedRecords.length === 0) {
            alert('Pilih minimal satu surat SNP terlebih dahulu.');
            return;
        }

        checkedRecords.forEach((checkbox) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'record_ids[]';
            input.value = checkbox.value;

            customReportRecordIds.appendChild(input);
        });

        renderButirOptions(checkedRecords);

        customReportModal.classList.remove('hidden');
        customReportModal.classList.add('flex');
    };

    openCustomReportModalBtn?.addEventListener('click', openCustomReportModal);
    closeCustomReportModalBtn?.addEventListener('click', closeCustomReportModal);
    cancelCustomReportModalBtn?.addEventListener('click', closeCustomReportModal);

    customReportModal.addEventListener('click', (event) => {
        if (event.target === customReportModal) {
            closeCustomReportModal();
        }
    });

    selectAllCustomButirBtn?.addEventListener('click', () => {
        const checkboxes = customReportForm.querySelectorAll('.custom-butir-checkbox');
        const allChecked = Array.from(checkboxes).every((checkbox) => checkbox.checked);

        checkboxes.forEach((checkbox) => {
            checkbox.checked = !allChecked;
        });

        selectAllCustomButirBtn.textContent = allChecked ? 'Pilih Semua Butir' : 'Hapus Pilihan Butir';
    });

    selectAllCustomFieldsBtn?.addEventListener('click', () => {
        const checkboxes = customReportForm.querySelectorAll('.custom-field-checkbox');
        const allChecked = Array.from(checkboxes).every((checkbox) => checkbox.checked);

        checkboxes.forEach((checkbox) => {
            checkbox.checked = !allChecked;
        });

        selectAllCustomFieldsBtn.textContent = allChecked ? 'Pilih Semua Kolom' : 'Hapus Pilihan Kolom';
    });

    customReportForm.addEventListener('submit', (event) => {
        const selectedButirs = customReportForm.querySelectorAll('input[name="butir_ids[]"]:checked');
        const selectedFields = customReportForm.querySelectorAll('input[name="fields[]"]:checked');

        if (selectedButirs.length === 0) {
            event.preventDefault();
            alert('Pilih minimal satu butir SNP untuk dicetak.');
            return;
        }

        if (selectedFields.length === 0) {
            event.preventDefault();
            alert('Pilih minimal satu kolom untuk dicetak.');
        }
    });
});

document.addEventListener('DOMContentLoaded', () => {
    const openReportFormatModalBtn = document.getElementById('openReportFormatModalBtn');
    const closeReportFormatModalBtn = document.getElementById('closeReportFormatModalBtn');
    const reportFormatModal = document.getElementById('reportFormatModal');

    const openReportFormatModal = () => {
        const checkedRecords = document.querySelectorAll('input[name="record_ids[]"]:checked');

        if (checkedRecords.length === 0) {
            alert('Pilih minimal satu surat SNP terlebih dahulu.');
            return;
        }

        reportFormatModal.classList.remove('hidden');
        reportFormatModal.classList.add('flex');
    };

    const closeReportFormatModal = () => {
        reportFormatModal.classList.add('hidden');
        reportFormatModal.classList.remove('flex');
    };

    openReportFormatModalBtn?.addEventListener('click', openReportFormatModal);
    closeReportFormatModalBtn?.addEventListener('click', closeReportFormatModal);

    reportFormatModal?.addEventListener('click', (event) => {
        if (event.target === reportFormatModal) {
            closeReportFormatModal();
        }
    });
});
