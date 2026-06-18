<?php

test('rawas perekaman uses memo document and combined pic inputs', function () {
    $rootPath = dirname(__DIR__, 2);

    $view = file_get_contents($rootPath.'/resources/views/layouts/rawas/perekaman.blade.php');
    $controller = file_get_contents($rootPath.'/app/Http/Controllers/Rawas/PerekamanRawasController.php');
    $routes = file_get_contents($rootPath.'/routes/web.php');

    expect($view)
        ->toContain('name="dokumen_memo"')
        ->toContain("route('rawas.perekaman.dokumen-memo'")
        ->toContain('Dewan Pengawas')
        ->toContain('name="pic_ids[]"')
        ->toContain('name="tanggal_rawas"')
        ->toContain('name="agenda_rawas"')
        ->toContain('name="keputusan_rawas"')
        ->not->toContain('name="unit_kerja_utama_id"')
        ->not->toContain('name="unit_kerja_pendukung_id[]"');

    expect($controller)
        ->toContain("'dokumen_memo' =>")
        ->toContain("'pic_ids' => ['required', 'array', 'min:1']")
        ->toContain("UnitKerja::query()")
        ->toContain("'type' => 'Direktorat'")
        ->toContain("'sub_label' => 'Dewan Pengawas'")
        ->toContain("'jenis_pic' => 'unit'")
        ->toContain("'jenis_pic' => 'komite'")
        ->not->toContain("->where('direktorat_id'");

    expect($routes)
        ->toContain("->name('rawas.perekaman.dokumen-memo')");
});

test('rawas follow up review and report mirror ragab flow with locked directorate', function () {
    $rootPath = dirname(__DIR__, 2);

    $tindakLanjutController = file_get_contents($rootPath.'/app/Http/Controllers/Rawas/TindakLanjutRawasController.php');
    $reviuController = file_get_contents($rootPath.'/app/Http/Controllers/Rawas/ReviuRawasController.php');
    $reportController = file_get_contents($rootPath.'/app/Http/Controllers/Rawas/ReportRawasController.php');
    $butirModel = file_get_contents($rootPath.'/app/Models/RawasButir.php');
    $tindakLanjutView = file_get_contents($rootPath.'/resources/views/layouts/rawas/tindak-lanjut.blade.php');
    $reviuView = file_get_contents($rootPath.'/resources/views/layouts/rawas/reviu.blade.php');
    $reportView = file_get_contents($rootPath.'/resources/views/layouts/rawas/report/index.blade.php');
    $reportPdf = file_get_contents($rootPath.'/resources/views/layouts/rawas/report/pdf.blade.php');
    $reportExcel = file_get_contents($rootPath.'/resources/views/layouts/rawas/report/excel.blade.php');

    expect($tindakLanjutController)
        ->toContain("'butir_pic_id' => ['required', 'integer', 'exists:mysql_rawas.tb_butir_pic,id']")
        ->toContain("->where('jenis_pic', 'unit')")
        ->toContain("RawasButirPic::where('id', \$validated['butir_pic_id'])")
        ->not->toContain('RawasReview::create');

    expect($reviuController)
        ->toContain('RawasReview::firstOrCreate')
        ->toContain("'id_tindak_lanjut' => null")
        ->toContain('canReviewAllRawas')
        ->toContain('reviewTindakLanjut')
        ->not->toContain("whereNotNull('id_tindak_lanjut')");

    expect($reportController)
        ->toContain("'tgl_agenda' => 'TGL & AGENDA RAWAS'")
        ->toContain("'direktorat' => 'DIREKTORAT'")
        ->toContain("'unit_pic' => 'UNIT PIC'")
        ->toContain('getRecordsForReport')
        ->not->toContain("'pic_utama'")
        ->not->toContain("'pic_pendukung'");

    expect($butirModel)
        ->toContain('statusTindakLanjut')
        ->toContain('progressTindakLanjutLabel')
        ->toContain('picUnitButirPicIds');

    expect($tindakLanjutView)
        ->toContain('name="butir_pic_id"')
        ->toContain('Direktorat - Dewan Pengawas')
        ->not->toContain('name="unit_kerja_utama_id"')
        ->not->toContain('name="unit_kerja_pendukung_id"');

    expect($reviuView)
        ->toContain('Dewan Pengawas')
        ->toContain('statusTindakLanjut()')
        ->toContain('progressTindakLanjutLabel()');

    expect($reportView)
        ->toContain('Dewan Pengawas')
        ->toContain('name="unit_kerja_id"')
        ->toContain('Cetak Report Custom');

    expect($reportPdf)
        ->toContain('TGL & AGENDA RAWAS')
        ->toContain('Dewan Pengawas')
        ->toContain('butirPic?->unitKerja');

    expect($reportExcel)
        ->toContain('Dewan Pengawas')
        ->toContain('butirPic?->unitKerja');
});
