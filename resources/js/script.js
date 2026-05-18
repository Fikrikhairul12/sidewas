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
