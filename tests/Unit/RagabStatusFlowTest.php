<?php

test('ragab uses butir status as workflow source and record status as aggregate', function () {
    $rootPath = dirname(__DIR__, 2);

    $recordModel = file_get_contents($rootPath.'/app/Models/RagabRecord.php');
    $butirModel = file_get_contents($rootPath.'/app/Models/RagabButir.php');
    $butirSubClusterModel = file_get_contents($rootPath.'/app/Models/RagabButirSubCluster.php');
    $migration = file_get_contents($rootPath.'/database/migrations/2026_05_22_110928_create_ragab_tables.php');
    $perekamanController = file_get_contents($rootPath.'/app/Http/Controllers/Ragab/PerekamanRagabController.php');
    $perekamanView = file_get_contents($rootPath.'/resources/views/layouts/ragab/perekaman.blade.php');
    $script = file_get_contents($rootPath.'/resources/js/script.js');
    $tindakLanjutController = file_get_contents($rootPath.'/app/Http/Controllers/Ragab/TindakLanjutRagabController.php');
    $reviuController = file_get_contents($rootPath.'/app/Http/Controllers/Ragab/ReviuRagabController.php');

    expect($migration)
        ->toContain("\$table->enum('status', [\n                'draft',\n                'dalam_proses',\n                'tuntas',")
        ->toContain("\$table->enum('status', [\n                'terbit',\n                'dalam_proses',\n                'diusulkan_tuntas',\n                'selesai_tuntas',");

    expect($migration)
        ->toContain("create('tb_butir_sub_cluster'")
        ->toContain("'id_butir_ragab',\n                'sub_cluster_id',")
        ->toContain("dropIfExists('tb_butir_sub_cluster')");

    expect($recordModel)
        ->toContain('public function syncStatusFromButir(?int $updatedBy = null): void')
        ->toContain("\$this->butirRagab->isEmpty() => 'draft'")
        ->toContain("\$this->isEveryButirSelesaiTuntas() => 'tuntas'")
        ->toContain("default => 'dalam_proses'");

    expect($butirModel)
        ->toContain("'status',")
        ->toContain("\$butir->status = 'terbit'")
        ->toContain('public function syncStatusFromTindakLanjut(?int $updatedBy = null): void')
        ->toContain("\$this->tindakLanjutUnitKerjaIds() === [] => 'terbit'")
        ->toContain("\$this->isTindakLanjutLengkap() => 'diusulkan_tuntas'")
        ->toContain("default => 'dalam_proses'")
        ->toContain('public function markSelesaiTuntas(?int $updatedBy = null): void')
        ->toContain('public function subClusters()')
        ->toContain("'tb_butir_sub_cluster'");

    expect($butirSubClusterModel)
        ->toContain("protected \$table = 'tb_butir_sub_cluster'")
        ->toContain("'id_butir_ragab'")
        ->toContain("'sub_cluster_id'")
        ->toContain('public function subCluster()');

    expect($perekamanController)
        ->toContain("'status' => 'draft'")
        ->toContain("'status' => 'terbit'")
        ->toContain("'sub_cluster_ids' => ['required', 'array', 'min:1']")
        ->toContain('RagabButirSubCluster::create')
        ->toContain("'sub_cluster_id' => \$selectedSubClusterIds->first()")
        ->toContain('->syncStatusFromButir($user->id)');

    expect($perekamanView)
        ->toContain('name="sub_cluster_ids[]"')
        ->toContain('x-model="selectedSubClusterIds"')
        ->toContain('selectedSubClusterDetail')
        ->toContain('$butir->subClusters->pluck');

    expect($script)
        ->toContain('selectedSubClusterIds: []')
        ->toContain('get selectedSubClusterDetail()')
        ->toContain('removeSubCluster(id)');

    expect($tindakLanjutController)
        ->toContain('$butir->refresh()->syncStatusFromTindakLanjut($user->id)')
        ->toContain('$record->refresh()->syncStatusFromButir($user->id)');

    expect($reviuController)
        ->toContain('$review->butir?->markSelesaiTuntas($user->id)')
        ->toContain('$record->refresh()->syncStatusFromButir($user->id)');
});
