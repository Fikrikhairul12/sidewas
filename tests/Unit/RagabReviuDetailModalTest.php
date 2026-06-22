<?php

test('ragab reviu summarizes tindak lanjut and provides detail modal', function () {
    $basePath = dirname(__DIR__, 2);

    $controller = file_get_contents($basePath.'/app/Http/Controllers/Ragab/ReviuRagabController.php');
    $view = file_get_contents($basePath.'/resources/views/layouts/ragab/reviu.blade.php');

    expect($controller)
        ->toContain('butir.tindakLanjuts.creator')
        ->toContain('butir.tindakLanjuts.unitKerja.direktorat')
        ->toContain('butir.butirPics.unitKerja.direktorat');

    expect($view)
        ->toContain('openDetailModal: false')
        ->toContain('openDetailModalFor(butir)')
        ->toContain('filteredDetailTindakLanjuts')
        ->toContain('$detailTindakLanjutPayload')
        ->toContain('$tindakLanjutItems->take(2)')
        ->toContain('+ {{ $tindakLanjutItems->count() - 2 }} tindak lanjut lainnya')
        ->toContain('Detail Tindak Lanjut RAGAB')
        ->toContain('openDetailModalFor(@js($detailTindakLanjutPayload))')
        ->toContain('Unit Berikutnya')
        ->toContain('selectedDetailTl.tindak_lanjut');
});
