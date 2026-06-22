<?php

test('rawas perekaman summarizes multiple butir and opens detail modal', function () {
    $basePath = dirname(__DIR__, 2);

    $controller = file_get_contents($basePath.'/app/Http/Controllers/Rawas/PerekamanRawasController.php');
    $view = file_get_contents($basePath.'/resources/views/layouts/rawas/perekaman.blade.php');
    $script = file_get_contents($basePath.'/resources/js/script.js');

    expect($controller)
        ->toContain('butirRawas.cluster')
        ->toContain('butirRawas.subCluster')
        ->toContain('butirRawas.butirPics.unitKerja.direktorat');

    expect($view)
        ->toContain('Detail Butir RAWAS')
        ->toContain('$butirCount === 1')
        ->toContain('$butirCount > 1')
        ->toContain('Menampilkan ringkasan 1 dari {{ $butirCount }} butir.')
        ->toContain('openDetailModalFor(@js($detailRecordPayload))')
        ->toContain("'butirs' => \$record->butirRawas")
        ->toContain('selectedDetailButir.keputusan_rawas')
        ->toContain('selectedDetailButir.pic_unit');

    expect($script)
        ->toContain('openDetailModal: false')
        ->toContain('detailRecord: null')
        ->toContain('openDetailModalFor(record)')
        ->toContain('filteredDetailButirs')
        ->toContain('id_butir_rawas');
});

test('rawas reviu summarizes tindak lanjut and provides detail modal', function () {
    $basePath = dirname(__DIR__, 2);

    $controller = file_get_contents($basePath.'/app/Http/Controllers/Rawas/ReviuRawasController.php');
    $view = file_get_contents($basePath.'/resources/views/layouts/rawas/reviu.blade.php');

    expect($controller)
        ->toContain('butir.tindakLanjuts.creator')
        ->toContain('butir.tindakLanjuts.butirPic.unitKerja.direktorat')
        ->toContain('butir.butirPics.unitKerja.direktorat');

    expect($view)
        ->toContain('openDetailModal: false')
        ->toContain('openDetailModalFor(butir)')
        ->toContain('filteredDetailTindakLanjuts')
        ->toContain('$detailTindakLanjutPayload')
        ->toContain('$tindakLanjutItems->take(2)')
        ->toContain('+ {{ $tindakLanjutItems->count() - 2 }} tindak lanjut lainnya')
        ->toContain('Detail Tindak Lanjut RAWAS')
        ->toContain('openDetailModalFor(@js($detailTindakLanjutPayload))')
        ->toContain('Unit Berikutnya')
        ->toContain('selectedDetailTl.tindak_lanjut');
});
