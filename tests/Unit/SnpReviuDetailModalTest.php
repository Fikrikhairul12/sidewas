<?php

test('snp reviu detail opens record modal with all butirs and highlighted clicked butir', function () {
    $basePath = dirname(__DIR__, 2);

    $controller = file_get_contents($basePath . '/app/Http/Controllers/Snp/ReviuSnpController.php');
    $view = file_get_contents($basePath . '/resources/views/layouts/snp/reviu.blade.php');

    expect($controller)
        ->toContain("'butir.record.butirSnp.reviews.komite'")
        ->toContain("'butir.record.butirSnp.kompilasis'")
        ->toContain("\$review->tahap_review === 'tindak_lanjut' && \$review->status === 'dalam_proses_tindak_lanjut_direksi'")
        ->toContain("->orWhereHas('kompilasiTanggapan', function (\$kompilasiQuery) use (\$keyword) {")
        ->not->toContain("->orWhere('status_pengajuan_tgl', 'like', \"%{\$keyword}%\")");

    expect($view)
        ->toContain('openDetailModalFor(record, selectedButirId)')
        ->toContain('openDetailModalFor(@js($detailRecordPayload)')
        ->toContain('Detail Reviu SNP')
        ->toContain("'butirs' =>")
        ->toContain('Reviu Tindak Lanjut')
        ->toContain('Reviu Tanggapan')
        ->toContain('Kompilasi Tindak Lanjut')
        ->toContain('Kompilasi Tanggapan')
        ->toContain("'selesai' => \$reviewAktif?->status === 'selesai_tuntas'")
        ->toContain('selectedDetailButir.selesai')
        ->toContain("\$review->tahap_review === 'tindak_lanjut' && \$review->status === 'dalam_proses_tindak_lanjut_direksi'");

    expect($view)
        ->not->toContain('Surat SNP')
        ->not->toContain('detailRecord?.nomor_surat')
        ->not->toContain('detailRecord?.perihal_surat');
});
