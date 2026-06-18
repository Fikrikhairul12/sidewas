<?php

test('snp tanggapan table uses summary and opens detail modal', function () {
    $basePath = dirname(__DIR__, 2);

    $view = file_get_contents($basePath . '/resources/views/layouts/snp/tanggapan.blade.php');
    $script = file_get_contents($basePath . '/resources/js/script.js');

    expect($view)
        ->toContain('x-data="tanggapanSnpPage()"')
        ->toContain('Detail Tanggapan SNP')
        ->toContain('openDetailModalFor(@js($detailTanggapanPayload))')
        ->toContain('Unit Berikutnya')
        ->toContain('Download Dokumen')
        ->toContain('tanggapanList->take(2)')
        ->toContain('tanggapan_singkat')
        ->toContain('pic_tanggapans');

    expect($script)
        ->toContain('window.tanggapanSnpPage')
        ->toContain('openDetailModalFor(butir)')
        ->toContain('filteredDetailPics')
        ->toContain('selectedDetailPic')
        ->toContain('selectNextDetailPic()');
});
