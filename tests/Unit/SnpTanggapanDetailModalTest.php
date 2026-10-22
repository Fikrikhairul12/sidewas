<?php

test('snp tanggapan table uses summary and opens detail modal', function () {
    $basePath = dirname(__DIR__, 2);

    $view = file_get_contents($basePath.'/resources/views/layouts/snp/tanggapan.blade.php');
    $script = file_get_contents($basePath.'/resources/js/script.js');
    $detail = file_get_contents($basePath.'/resources/views/layouts/partials/tanggapan-unit-detail.blade.php');
    $service = file_get_contents($basePath.'/app/Services/TanggapanUnitService.php');

    expect($view)
        ->toContain('x-data="tanggapanSnpPage()"')
        ->toContain("'moduleLabel' => 'SNP'")
        ->toContain('openDetailModalFor(@js($detailTanggapanPayload))')
        ->toContain('Download Dokumen')
        ->toContain('tanggapanList->take(2)')
        ->toContain('tanggapan-unit-detail');

    expect($detail)->toContain('Unit Berikutnya', 'Edit Tanggapan');
    expect($service)
        ->toContain('tanggapan_singkat')
        ->toContain('pic_tanggapans');

    expect($script)
        ->toContain('window.tanggapanSnpPage')
        ->toContain('openDetailModalFor(butir)')
        ->toContain('filteredDetailPics')
        ->toContain('selectedDetailPic')
        ->toContain('selectNextDetailPic()');
});
