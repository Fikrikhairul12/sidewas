<?php

test('ragab and rawas record status flow follows recording follow up and review stages', function () {
    $rootPath = dirname(__DIR__, 2);

    $ragabPerekaman = file_get_contents($rootPath.'/app/Http/Controllers/Ragab/PerekamanRagabController.php');
    $rawasPerekaman = file_get_contents($rootPath.'/app/Http/Controllers/Rawas/PerekamanRawasController.php');
    $ragabTindakLanjut = file_get_contents($rootPath.'/app/Http/Controllers/Ragab/TindakLanjutRagabController.php');
    $rawasTindakLanjut = file_get_contents($rootPath.'/app/Http/Controllers/Rawas/TindakLanjutRawasController.php');
    $ragabReviu = file_get_contents($rootPath.'/app/Http/Controllers/Ragab/ReviuRagabController.php');
    $rawasReviu = file_get_contents($rootPath.'/app/Http/Controllers/Rawas/ReviuRawasController.php');

    foreach ([$ragabPerekaman, $rawasPerekaman] as $source) {
        expect($source)
            ->toContain("'status' => 'draft'")
            ->toContain("if (\$record->status === 'draft')")
            ->toContain("'status' => 'terbit'");
    }

    foreach ([$ragabTindakLanjut, $rawasTindakLanjut] as $source) {
        expect($source)
            ->toContain('$allButirsReady')
            ->toContain('$hasAnyTl')
            ->toContain("'status' => \$allButirsReady ? 'diusulkan_tuntas' : (\$hasAnyTl ? 'dalam_proses' : \$record->status)");
    }

    foreach ([$ragabReviu, $rawasReviu] as $source) {
        expect($source)
            ->toContain('$allButirsReviewed')
            ->toContain("'status' => 'tuntas'")
            ->not->toContain("'status' => 'diusulkan_tuntas',")
            ->not->toContain("'status' => 'dalam_proses',");
    }
});
