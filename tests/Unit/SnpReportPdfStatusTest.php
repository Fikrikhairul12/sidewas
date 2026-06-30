<?php

test('snp report pdf merges status per butir and uses latest butir status', function () {
    $rootPath = dirname(__DIR__, 2);

    $pdf = file_get_contents($rootPath.'/resources/views/layouts/snp/report/pdf.blade.php');
    $pdfCustom = file_get_contents($rootPath.'/resources/views/layouts/snp/report/pdf-custom.blade.php');
    $controller = file_get_contents($rootPath.'/app/Http/Controllers/Snp/ReportSnpController.php');

    expect($pdf)
        ->toContain('$reviewTerbaruButir = $butir->reviews->sortByDesc(\'id\')->first();')
        ->toContain('$statusTerbaruButir')
        ->toContain('<td class="center" rowspan="{{ $jumlahBarisButir }}">')
        ->toContain('display: table-header-group;')
        ->not->toContain('page-break-inside: avoid;')
        ->not->toContain('td[rowspan]')
        ->not->toContain('<tbody class="record-group">')
        ->not->toContain('.record-group')
        ->toContain('{{ ucwords(str_replace(\'_\', \' \', $statusTerbaruButir)) }}')
        ->not->toContain('{{ ucwords(str_replace(\'_\', \' \', $statusTl)) }}');

    expect($pdfCustom)
        ->toContain('$reviewTerbaruButir = $butir->reviews->sortByDesc(\'id\')->first();')
        ->toContain('$reviewTerbaruButir?->status');

    expect($controller)
        ->toContain('use Spatie\\Browsershot\\Browsershot;')
        ->toContain('Browsershot::html($html)')
        ->toContain("->format('Legal')")
        ->toContain('->landscape()')
        ->toContain('Content-Disposition');
});

test('snp custom report filters unit kerja with checkbox options from selected butir', function () {
    $rootPath = dirname(__DIR__, 2);

    $controller = file_get_contents($rootPath.'/app/Http/Controllers/Snp/ReportSnpController.php');
    $export = file_get_contents($rootPath.'/app/Exports/SnpReportExport.php');
    $view = file_get_contents($rootPath.'/resources/views/layouts/snp/report/index.blade.php');
    $script = file_get_contents($rootPath.'/resources/js/script.js');

    expect($controller)
        ->toContain("'butirSnp.butirPics.unitKerja'")
        ->toContain('$tanggapanUnitKerjaIds = $validated[\'tanggapan_unit_kerja_ids\'] ?? [];')
        ->toContain('$tindakLanjutUnitKerjaIds = $validated[\'tindak_lanjut_unit_kerja_ids\'] ?? []');

    expect($export)
        ->toContain('array $tanggapanUnitKerjaIds = []')
        ->toContain('array $tindakLanjutUnitKerjaIds = []')
        ->toContain("'tanggapanUnitKerjaIds' => \$this->tanggapanUnitKerjaIds");

    expect($view)
        ->toContain("'tanggapan_units' => \$unitOptions")
        ->toContain("'tindak_lanjut_units' => \$unitOptions")
        ->toContain('id="customReportTanggapanUnitList"')
        ->toContain('id="customReportTindakLanjutUnitList"')
        ->not->toContain('name="tanggapan_unit_kerja_ids[]" multiple')
        ->not->toContain('name="tindak_lanjut_unit_kerja_ids[]" multiple');

    expect($script)
        ->toContain('customReportTanggapanUnitList')
        ->toContain('custom-tanggapan-unit-checkbox')
        ->toContain('tanggapan_unit_kerja_ids[]')
        ->toContain('tindak_lanjut_unit_kerja_ids[]');
});

test('snp custom report labels are readable and repeated unit entries are separated', function () {
    $rootPath = dirname(__DIR__, 2);

    $controller = file_get_contents($rootPath.'/app/Http/Controllers/Snp/ReportSnpController.php');
    $pdfCustom = file_get_contents($rootPath.'/resources/views/layouts/snp/report/pdf-custom.blade.php');
    $excel = file_get_contents($rootPath.'/resources/views/layouts/snp/report/excel.blade.php');

    expect($controller)
        ->toContain("'tanggapan_unit' => 'TANGGAPAN UNIT KERJA'")
        ->toContain("'tindak_lanjut_unit' => 'TINDAK LANJUT UNIT KERJA'")
        ->toContain("'kompilasi_tanggapan' => 'KOMPILASI TANGGAPAN'")
        ->toContain("'kompilasi_tindak_lanjut' => 'KOMPILASI TINDAK LANJUT'");

    expect($pdfCustom)
        ->toContain('.entry-block')
        ->toContain('class="entry-block"')
        ->toContain('class="entry-title"')
        ->toContain('$butir->kompilasis->where(\'tahap_kompilasi\', \'tanggapan\')->sortByDesc(\'id\')->first();')
        ->toContain('$normalizeReportText')
        ->toContain('$joinReportSections')
        ->toContain('$tanggapan->butirPic?->unitKerja?->nama_unit')
        ->toContain('$tl->butirPic?->unitKerja?->nama_unit');

    expect($excel)
        ->toContain('--------------------')
        ->toContain('$tanggapan->butirPic?->unitKerja?->nama_unit')
        ->toContain('$tl->butirPic?->unitKerja?->nama_unit');
});
