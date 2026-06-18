<?php

test('snp perekaman table summarizes multiple butir and opens detail modal', function () {
    $basePath = dirname(__DIR__, 2);

    $view = file_get_contents($basePath . '/resources/views/layouts/snp/perekaman.blade.php');
    $script = file_get_contents($basePath . '/resources/js/script.js');

    expect($view)
        ->toContain('Detail Butir SNP')
        ->toContain('$butirCount === 1')
        ->toContain('$butirCount > 1')
        ->toContain('Ringkasan ditampilkan di tabel. Detail lengkap ada di tombol Detail.')
        ->toContain('openDetailModalFor(@js($detailRecordPayload))')
        ->toContain("'butirs' => \$record->butirSnp")
        ->toContain('Pilih Butir Ini');

    expect($script)
        ->toContain('openDetailModal: false')
        ->toContain('detailRecord: null')
        ->toContain('openDetailModalFor(record)')
        ->toContain('filteredDetailButirs')
        ->toContain('selectedDetailButir');
});
