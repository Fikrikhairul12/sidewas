<?php

test('djsn perekaman table summarizes multiple butir and opens detail modal', function () {
    $basePath = dirname(__DIR__, 2);

    $controller = file_get_contents($basePath.'/app/Http/Controllers/Djsn/PerekamanDjsnController.php');
    $view = file_get_contents($basePath.'/resources/views/layouts/djsn/perekaman.blade.php');
    $script = file_get_contents($basePath.'/resources/js/script.js');

    expect($controller)
        ->toContain('butirDjsn.cluster')
        ->toContain('butirDjsn.subCluster')
        ->toContain('butirDjsn.butirPics.unitKerja.direktorat');

    expect($view)
        ->toContain('Detail Butir DJSN')
        ->toContain('$butirCount === 1')
        ->toContain('$butirCount > 1')
        ->toContain('Menampilkan ringkasan 1 dari {{ $butirCount }} butir.')
        ->toContain('openDetailModalFor(@js($detailRecordPayload))')
        ->toContain("'butirs' => \$record->butirDjsn")
        ->toContain('selectedDetailButir.butir_djsn')
        ->toContain('selectedDetailButir.pic_pendukung');

    expect($script)
        ->toContain('window.perekamanDjsnModal')
        ->toContain('openDetailModal: false')
        ->toContain('detailRecord: null')
        ->toContain('openDetailModalFor(record)')
        ->toContain('filteredDetailButirs')
        ->toContain('id_butir_djsn');
});
