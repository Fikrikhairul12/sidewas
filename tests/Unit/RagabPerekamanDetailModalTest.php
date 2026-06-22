<?php

test('ragab perekaman table summarizes multiple butir and opens detail modal', function () {
    $basePath = dirname(__DIR__, 2);

    $controller = file_get_contents($basePath.'/app/Http/Controllers/Ragab/PerekamanRagabController.php');
    $view = file_get_contents($basePath.'/resources/views/layouts/ragab/perekaman.blade.php');
    $script = file_get_contents($basePath.'/resources/js/script.js');

    expect($controller)
        ->toContain('butirRagab.cluster')
        ->toContain('butirRagab.subCluster')
        ->toContain('butirRagab.butirPics.unitKerja.direktorat')
        ->toContain('butirRagab.butirDirektorats.direktorat');

    expect($view)
        ->toContain('Detail Butir RAGAB')
        ->toContain('$butirCount === 1')
        ->toContain('$butirCount > 1')
        ->toContain('Menampilkan ringkasan 1 dari {{ $butirCount }} butir.')
        ->toContain('openDetailModalFor(@js($detailRecordPayload))')
        ->toContain("'butirs' => \$record->butirRagab")
        ->toContain('selectedDetailButir.keputusan_ragab')
        ->toContain('selectedDetailButir.pic_unit');

    expect($script)
        ->toContain('openDetailModal: false')
        ->toContain('detailRecord: null')
        ->toContain('openDetailModalFor(record)')
        ->toContain('filteredDetailButirs')
        ->toContain('selectedDetailButir')
        ->toContain('id_butir_ragab');
});
