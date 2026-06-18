<?php

test('snp tindak lanjut table uses summary and opens detail modal', function () {
    $basePath = dirname(__DIR__, 2);

    $view = file_get_contents($basePath . '/resources/views/layouts/snp/tindak-lanjut.blade.php');

    expect($view)
        ->toContain('openDetailModal: false')
        ->toContain('Detail Tindak Lanjut SNP')
        ->toContain('openDetailModalFor(@js($detailTindakLanjutPayload))')
        ->toContain('filteredDetailTindakLanjuts')
        ->toContain('selectedDetailTl')
        ->toContain('selectNextDetailTl()')
        ->toContain('$items->take(2)')
        ->toContain('tindak_lanjut_singkat')
        ->toContain('Unit Berikutnya')
        ->toContain('Download Dokumen');
});
