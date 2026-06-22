/**
 * ============================================================
 * PEREKAMAN SNP
 * ============================================================
 */
window.perekamanSnpModal = function (clusters = [], direktorats = []) {
    return {
        openCreateModal: false,
        openButirModal: false,
        openDetailModal: false,

        selectedRecord: null,
        detailRecord: null,
        selectedDetailButirId: null,
        detailSearch: '',

        selectedClusterId: '',
        selectedSubClusterId: '',
        selectedDirektoratUtamaId: '',

        picPendukungSearch: '',
        selectedPicPendukung: [],

        clusters: clusters,
        direktorats: direktorats,

        openButirModalFor(record) {
            this.selectedRecord = record;
            this.selectedClusterId = '';
            this.selectedSubClusterId = '';
            this.selectedDirektoratUtamaId = '';
            this.picPendukungSearch = '';
            this.selectedPicPendukung = [];
            this.openButirModal = true;
        },

        openDetailModalFor(record) {
            this.detailRecord = record;
            this.detailSearch = '';
            this.selectedDetailButirId = record.butirs?.[0]?.id ?? null;
            this.openDetailModal = true;
        },

        selectDetailButir(butir) {
            this.selectedDetailButirId = butir.id;
        },

        get detailButirs() {
            return this.detailRecord?.butirs ?? [];
        },

        get filteredDetailButirs() {
            const keyword = this.detailSearch.toLowerCase().trim();

            if (!keyword) {
                return this.detailButirs;
            }

            return this.detailButirs.filter(butir => {
                const id = String(butir.id_butir_snp || '').toLowerCase();
                const isi = String(butir.butir_snp || '').toLowerCase();

                return id.includes(keyword) || isi.includes(keyword);
            });
        },

        get selectedDetailButir() {
            const selected = this.detailButirs.find(butir => String(butir.id) === String(this.selectedDetailButirId));

            if (selected) {
                return selected;
            }

            return this.filteredDetailButirs[0] ?? null;
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

/**
 * ============================================================
 * TANGGAPAN SNP
 * ============================================================
 */
window.tanggapanSnpPage = function () {
    return {
        openModal: false,
        openDetailModal: false,
        selectedButir: null,
        detailButir: null,
        selectedDetailPicId: null,
        detailSearch: '',

        openDetailModalFor(butir) {
            this.detailButir = butir;
            this.detailSearch = '';
            this.selectedDetailPicId = butir.pic_tanggapans?.[0]?.id ?? null;
            this.openDetailModal = true;
        },

        selectDetailPic(pic) {
            this.selectedDetailPicId = pic.id;
        },

        get detailPics() {
            return this.detailButir?.pic_tanggapans ?? [];
        },

        get filteredDetailPics() {
            const keyword = this.detailSearch.toLowerCase().trim();

            if (!keyword) {
                return this.detailPics;
            }

            return this.detailPics.filter(pic => {
                const unit = String(pic.unit_label || '').toLowerCase();
                const tanggapan = String(pic.tanggapan || '').toLowerCase();
                const deliverables = String(pic.deliverables || '').toLowerCase();

                return unit.includes(keyword)
                    || tanggapan.includes(keyword)
                    || deliverables.includes(keyword);
            });
        },

        get selectedDetailPic() {
            const selected = this.detailPics.find(pic => String(pic.id) === String(this.selectedDetailPicId));

            if (selected) {
                return selected;
            }

            return this.filteredDetailPics[0] ?? null;
        },

        get nextDetailPic() {
            if (this.filteredDetailPics.length === 0) {
                return null;
            }

            const currentIndex = this.filteredDetailPics.findIndex(pic => {
                return String(pic.id) === String(this.selectedDetailPic?.id);
            });

            if (currentIndex < 0) {
                return this.filteredDetailPics[0];
            }

            return this.filteredDetailPics[(currentIndex + 1) % this.filteredDetailPics.length];
        },

        selectNextDetailPic() {
            if (this.nextDetailPic) {
                this.selectDetailPic(this.nextDetailPic);
            }
        },
    };
};


/**
 * ============================================================
 * PEREKAMAN RAGAB
 * ============================================================
 * Clone dari perekaman SNP.
 * Dipakai di resources/views/layouts/ragab/perekaman.blade.php
 */
window.perekamanRagabModal = function (clusters = [], direktorats = [], unitKerjas = []) {
    return {
        openCreateModal: false,
        openButirModal: false,
        openDetailModal: false,

        selectedRecord: null,
        detailRecord: null,
        selectedDetailButir: null,

        selectedClusterId: '',
        selectedDirektoratIds: [],
        selectedUnitKerjaIds: [],

        unitKerjaSearch: '',
        detailSearch: '',

        clusters: clusters,
        direktorats: direktorats,
        unitKerjas: unitKerjas,

        openButirModalFor(record) {
            this.selectedRecord = record;

            this.selectedClusterId = '';
            this.selectedDirektoratIds = [];
            this.selectedUnitKerjaIds = [];
            this.unitKerjaSearch = '';

            this.openButirModal = true;
        },

        openDetailModalFor(record) {
            this.detailRecord = record;
            this.detailSearch = '';
            this.selectedDetailButir = record?.butirs?.[0] ?? null;
            this.openDetailModal = true;
        },

        selectDetailButir(butir) {
            this.selectedDetailButir = butir;
        },

        get filteredSubClusters() {
            const cluster = this.clusters.find(item => String(item.id) === String(this.selectedClusterId));
            return cluster ? cluster.sub_clusters : [];
        },

        get filteredDetailButirs() {
            const butirs = this.detailRecord?.butirs ?? [];
            const keyword = this.detailSearch.toLowerCase().trim();

            if (!keyword) {
                return butirs;
            }

            return butirs.filter(butir => {
                const id = String(butir.id_butir_ragab || '').toLowerCase();
                const agenda = String(butir.agenda_ragab || '').toLowerCase();
                const keputusan = String(butir.keputusan_ragab || '').toLowerCase();

                return id.includes(keyword)
                    || agenda.includes(keyword)
                    || keputusan.includes(keyword);
            });
        },

        get filteredUnitKerjas() {
            const keyword = this.unitKerjaSearch.toLowerCase().trim();

            if (!keyword) {
                return this.unitKerjas;
            }

            return this.unitKerjas.filter(unit => {
                const kode = String(unit.kode_unit || '').toLowerCase();
                const nama = String(unit.nama_unit || '').toLowerCase();
                const direktorat = String(unit.direktorat?.nama_direktorat || unit.direktorat_nama || '').toLowerCase();

                return kode.includes(keyword)
                    || nama.includes(keyword)
                    || direktorat.includes(keyword);
            });
        },

        get selectedUnitKerjaDetail() {
            return this.unitKerjas.filter(unit => {
                return this.selectedUnitKerjaIds.includes(String(unit.id));
            });
        },

        removeUnitKerja(id) {
            this.selectedUnitKerjaIds = this.selectedUnitKerjaIds.filter(item => String(item) !== String(id));
        },
    };
};


/**
 * ============================================================
 * REPORT SNP - MODAL CETAK CUSTOM
 * ============================================================
 * Flow:
 * - user centang surat SNP
 * - klik Cetak Report Custom
 * - modal tampil daftar butir dari surat terpilih
 * - user pilih butir dan field
 * - submit ke PDF Custom / Excel Custom
 */
document.addEventListener('DOMContentLoaded', () => {
    const openCustomReportModalBtn = document.getElementById('openCustomReportModalBtn');
    const closeCustomReportModalBtn = document.getElementById('closeCustomReportModalBtn');
    const cancelCustomReportModalBtn = document.getElementById('cancelCustomReportModalBtn');

    const customReportModal = document.getElementById('customReportModal');
    const customReportRecordIds = document.getElementById('customReportRecordIds');
    const customReportButirList = document.getElementById('customReportButirList');
    const customReportForm = document.getElementById('customReportForm');
    const customReportTanggapanUnitList = document.getElementById('customReportTanggapanUnitList');
    const customReportTindakLanjutUnitList = document.getElementById('customReportTindakLanjutUnitList');

    const selectAllCustomButirBtn = document.getElementById('selectAllCustomButirBtn');
    const selectAllCustomFieldsBtn = document.getElementById('selectAllCustomFieldsBtn');
    const selectAllTanggapanUnitBtn = document.getElementById('selectAllTanggapanUnitBtn');
    const selectAllTindakLanjutUnitBtn = document.getElementById('selectAllTindakLanjutUnitBtn');

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
            const recordLabel = checkbox.dataset.recordLabel || 'Surat';
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
                empty.textContent = 'Surat ini belum memiliki butir.';
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
                input.dataset.tanggapanUnits = JSON.stringify(butir.tanggapan_units || []);
                input.dataset.tindakLanjutUnits = JSON.stringify(butir.tindak_lanjut_units || []);
                input.className = 'custom-butir-checkbox mt-1 rounded border-slate-300 text-blue-600 focus:ring-blue-500';
                input.addEventListener('change', renderUnitFilters);

                const span = document.createElement('span');
                span.className = 'text-slate-700';

                const idButir = butir.id_butir_snp ?? butir.id_butir_ragab ?? butir.id_butir_rawas ?? butir.id_butir_djsn ?? '-';
                const isiButir = butir.butir_snp ?? butir.butir_ragab ?? butir.id_butir_rawas ?? butir.id_butir_djsn ?? '-';

                span.innerHTML = `
                    <span class="font-bold" style="color:#2377b9;">${escapeHtml(idButir)}</span>
                    <br>
                    <span class="text-xs">${escapeHtml(isiButir)}</span>
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
                    Surat yang dipilih belum memiliki butir.
                </p>
            `;
        }

        renderUnitFilters();
    };

    const parseUnits = (value) => {
        try {
            return JSON.parse(value || '[]');
        } catch (error) {
            return [];
        }
    };

    const getSelectedButirUnits = (datasetKey) => {
        const selectedButirs = customReportForm.querySelectorAll('.custom-butir-checkbox:checked');
        const unitMap = new Map();

        selectedButirs.forEach((checkbox) => {
            parseUnits(checkbox.dataset[datasetKey]).forEach((unit) => {
                if (!unit?.id || unitMap.has(String(unit.id))) {
                    return;
                }

                unitMap.set(String(unit.id), unit);
            });
        });

        return Array.from(unitMap.values())
            .sort((first, second) => String(first.label || '').localeCompare(String(second.label || '')));
    };

    const renderUnitCheckboxList = (container, units, inputName, checkboxClass) => {
        if (!container) {
            return;
        }

        container.innerHTML = '';

        if (units.length === 0) {
            container.innerHTML = `
                <p class="text-sm text-slate-400">
                    Belum ada unit kerja dari butir terpilih.
                </p>
            `;
            return;
        }

        units.forEach((unit) => {
            const label = document.createElement('label');
            label.className = 'flex cursor-pointer items-start gap-3 rounded-lg bg-white px-3 py-2 text-sm text-slate-700 hover:bg-blue-50';

            const input = document.createElement('input');
            input.type = 'checkbox';
            input.name = inputName;
            input.value = unit.id;
            input.checked = true;
            input.className = `${checkboxClass} mt-1 rounded border-slate-300 text-blue-600 focus:ring-blue-500`;

            const span = document.createElement('span');
            span.textContent = unit.label || '-';

            label.appendChild(input);
            label.appendChild(span);
            container.appendChild(label);
        });
    };

    function renderUnitFilters() {
        renderUnitCheckboxList(
            customReportTanggapanUnitList,
            getSelectedButirUnits('tanggapanUnits'),
            'tanggapan_unit_kerja_ids[]',
            'custom-tanggapan-unit-checkbox',
        );

        renderUnitCheckboxList(
            customReportTindakLanjutUnitList,
            getSelectedButirUnits('tindakLanjutUnits'),
            'tindak_lanjut_unit_kerja_ids[]',
            'custom-tindak-lanjut-unit-checkbox',
        );
    };

    const openCustomReportModal = () => {
        const checkedRecords = document.querySelectorAll('input[name="record_ids[]"]:checked');

        customReportRecordIds.innerHTML = '';

        if (checkedRecords.length === 0) {
            alert('Pilih minimal satu surat terlebih dahulu.');
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
        renderUnitFilters();
    });

    selectAllCustomFieldsBtn?.addEventListener('click', () => {
        const checkboxes = customReportForm.querySelectorAll('.custom-field-checkbox');
        const allChecked = Array.from(checkboxes).every((checkbox) => checkbox.checked);

        checkboxes.forEach((checkbox) => {
            checkbox.checked = !allChecked;
        });

        selectAllCustomFieldsBtn.textContent = allChecked ? 'Pilih Semua Kolom' : 'Hapus Pilihan Kolom';
    });

    selectAllTanggapanUnitBtn?.addEventListener('click', () => {
        const checkboxes = customReportForm.querySelectorAll('.custom-tanggapan-unit-checkbox');
        const allChecked = Array.from(checkboxes).every((checkbox) => checkbox.checked);

        checkboxes.forEach((checkbox) => {
            checkbox.checked = !allChecked;
        });

        selectAllTanggapanUnitBtn.textContent = allChecked ? 'Pilih Semua' : 'Hapus Pilihan';
    });

    selectAllTindakLanjutUnitBtn?.addEventListener('click', () => {
        const checkboxes = customReportForm.querySelectorAll('.custom-tindak-lanjut-unit-checkbox');
        const allChecked = Array.from(checkboxes).every((checkbox) => checkbox.checked);

        checkboxes.forEach((checkbox) => {
            checkbox.checked = !allChecked;
        });

        selectAllTindakLanjutUnitBtn.textContent = allChecked ? 'Pilih Semua' : 'Hapus Pilihan';
    });

    customReportForm.addEventListener('submit', (event) => {
        const selectedButirs = customReportForm.querySelectorAll('input[name="butir_ids[]"]:checked');
        const selectedFields = customReportForm.querySelectorAll('input[name="fields[]"]:checked');

        if (selectedButirs.length === 0) {
            event.preventDefault();
            alert('Pilih minimal satu butir untuk dicetak.');
            return;
        }

        if (selectedFields.length === 0) {
            event.preventDefault();
            alert('Pilih minimal satu kolom untuk dicetak.');
        }
    });
});

/**
 * ============================================================
 * REPORT - PILIH SEMUA RECORD
 * ============================================================
 * Dipakai di halaman report SNP/RAGAB/RAWAS/DJSN.
 */
document.addEventListener('DOMContentLoaded', () => {
    const selectAllCheckbox = document.getElementById('selectAllReportRecords');
    const recordCheckboxes = document.querySelectorAll('.record-report-checkbox');

    if (!selectAllCheckbox || recordCheckboxes.length === 0) {
        return;
    }

    const refreshSelectAllState = () => {
        const checkedCount = Array.from(recordCheckboxes).filter((checkbox) => checkbox.checked).length;

        selectAllCheckbox.checked = checkedCount === recordCheckboxes.length;
        selectAllCheckbox.indeterminate = checkedCount > 0 && checkedCount < recordCheckboxes.length;
    };

    selectAllCheckbox.addEventListener('change', () => {
        recordCheckboxes.forEach((checkbox) => {
            checkbox.checked = selectAllCheckbox.checked;
        });
    });

    recordCheckboxes.forEach((checkbox) => {
        checkbox.addEventListener('change', refreshSelectAllState);
    });

    refreshSelectAllState();
});

/**
 * ============================================================
 * REPORT SNP - MODAL PILIH FORMAT
 * ============================================================
 * Flow:
 * - user centang surat SNP
 * - klik Cetak Report
 * - modal pilih format PDF / Excel muncul
 */
document.addEventListener('DOMContentLoaded', () => {
    const openReportFormatModalBtn = document.getElementById('openReportFormatModalBtn');
    const closeReportFormatModalBtn = document.getElementById('closeReportFormatModalBtn');
    const reportFormatModal = document.getElementById('reportFormatModal');

    if (!reportFormatModal) {
        return;
    }

    const openReportFormatModal = () => {
        const checkedRecords = document.querySelectorAll('input[name="record_ids[]"]:checked');

        if (checkedRecords.length === 0) {
            alert('Pilih minimal satu surat terlebih dahulu.');
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

    reportFormatModal.addEventListener('click', (event) => {
        if (event.target === reportFormatModal) {
            closeReportFormatModal();
        }
    });
});

/**
 * ============================================================
 * PEREKAMAN RAWAS
 * ============================================================
 * Clone dari perekaman SNP.
 * Dipakai di resources/views/layouts/rawas/perekaman.blade.php
 */
window.perekamanRawasModal = function (clusters = [], picOptions = []) {
    return {
        openCreateModal: false,
        openButirModal: false,
        openDetailModal: false,

        selectedRecord: null,
        detailRecord: null,
        selectedDetailButir: null,

        selectedClusterId: '',
        selectedPicIds: [],

        picSearch: '',
        detailSearch: '',

        clusters: clusters,
        picOptions: picOptions,

        openButirModalFor(record) {
            this.selectedRecord = record;
            this.selectedClusterId = '';
            this.selectedPicIds = [];
            this.picSearch = '';
            this.openButirModal = true;
        },

        openDetailModalFor(record) {
            this.detailRecord = record;
            this.detailSearch = '';
            this.selectedDetailButir = record?.butirs?.[0] ?? null;
            this.openDetailModal = true;
        },

        selectDetailButir(butir) {
            this.selectedDetailButir = butir;
        },

        get filteredSubClusters() {
            const cluster = this.clusters.find(item => String(item.id) === String(this.selectedClusterId));
            return cluster ? cluster.sub_clusters : [];
        },

        get filteredDetailButirs() {
            const butirs = this.detailRecord?.butirs ?? [];
            const keyword = this.detailSearch.toLowerCase().trim();

            if (!keyword) {
                return butirs;
            }

            return butirs.filter(butir => {
                const id = String(butir.id_butir_rawas || '').toLowerCase();
                const agenda = String(butir.agenda_rawas || '').toLowerCase();
                const keputusan = String(butir.keputusan_rawas || '').toLowerCase();

                return id.includes(keyword)
                    || agenda.includes(keyword)
                    || keputusan.includes(keyword);
            });
        },

        get filteredPicOptions() {
            const keyword = this.picSearch.toLowerCase().trim();

            if (!keyword) {
                return this.picOptions;
            }

            return this.picOptions.filter(pic => {
                const label = String(pic.label || '').toLowerCase();
                const subLabel = String(pic.sub_label || '').toLowerCase();
                const type = String(pic.type || '').toLowerCase();

                return label.includes(keyword)
                    || subLabel.includes(keyword)
                    || type.includes(keyword);
            });
        },

        get selectedPicDetail() {
            return this.picOptions.filter(pic => {
                return this.selectedPicIds.includes(String(pic.value));
            });
        },

        removePic(id) {
            this.selectedPicIds = this.selectedPicIds.filter(item => String(item) !== String(id));
        },
    };
};

/**
 * ============================================================
 * PEREKAMAN DJSN
 * ============================================================
 * Clone dari perekaman SNP.
 * Dipakai di resources/views/layouts/djsn/perekaman.blade.php
 */
window.perekamanDjsnModal = function (clusters = [], direktorats = []) {
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
            this.selectedClusterId = '';
            this.selectedSubClusterId = '';
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
