import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const context = { window: {}, document: { addEventListener() {} } };
vm.runInNewContext(readFileSync('resources/js/script.js', 'utf8').replace(/^import .*;\r?\n/gm, ''), context);
const clusters = [
    { id: 1, status: 'active', sub_clusters: [{ id: 1, status: 'active' }, { id: 2, status: 'inactive' }] },
    { id: 2, status: 'inactive', sub_clusters: [{ id: 3, status: 'active' }, { id: 4, status: 'inactive' }] },
];
const ids = options => Array.from(options, option => option.id);
for (const name of ['perekamanRagabModal', 'perekamanRawasModal', 'perekamanDjsnModal']) {
    const form = context.window[name](clusters);
    form.selectedClusterId = '1';
    assert.deepEqual(ids(form.inputClusters), [1]);
    assert.deepEqual(ids(form.filteredSubClusters), [1]);
    form.selectedClusterId = '2';
    assert.deepEqual(ids(form.filteredSubClusters), []);
    form.openEditModal = true;
    form.editRecord = { butirs: [{ id: 1, cluster_id: 2, sub_cluster_id: 4 }] };
    assert.deepEqual(ids(form.inputClusters), [1, 2]);
    assert.deepEqual(ids(form.filteredSubClusters), [4]);
    form.selectedClusterId = '1';
    assert.deepEqual(ids(form.filteredSubClusters), [1]);
    form.editRecord.butirs[0] = { id: 1, cluster_id: 1, sub_cluster_id: 2 };
    assert.deepEqual(ids(form.filteredSubClusters), [1, 2]);
}
const snp = context.window.perekamanSnpModal(clusters);
snp.selectedClusterId = '1';
assert.deepEqual(ids(snp.filteredSubClusters), [1]);
snp.editRecord = { cluster_id: 2, sub_cluster_id: 4 };
snp.editClusterId = '2';
assert.deepEqual(ids(snp.editClusters), [1, 2]);
assert.deepEqual(ids(snp.filteredEditSubClusters), [4]);
snp.editClusterId = '1';
assert.deepEqual(ids(snp.filteredEditSubClusters), [1]);
console.log('Cluster selection controls passed');
