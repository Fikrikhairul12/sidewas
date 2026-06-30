<?php

test('produk hukum fields follow the revised naming and file link flow', function () {
    $basePath = dirname(__DIR__, 2);

    $migration = file_get_contents($basePath.'/database/migrations/2026_06_23_093422_create_produk_hukum_tables.php');
    $controller = file_get_contents($basePath.'/app/Http/Controllers/ProdukHukum/ProdukHukumController.php');
    $produkHukumModel = file_get_contents($basePath.'/app/Models/ProdukHukum.php');
    $fileModel = file_get_contents($basePath.'/app/Models/ProdukHukumFile.php');
    $relasiModel = file_get_contents($basePath.'/app/Models/ProdukHukumRelasi.php');
    $view = file_get_contents($basePath.'/resources/views/layouts/produk-hukum/index.blade.php');

    expect($migration)
        ->toContain('nomor_peraturan_keputusan')
        ->toContain('bidang_pengaturan')
        ->toContain('sumber_ln_tbn')
        ->toContain('sumber_tln_tbn')
        ->toContain('muatan_substansial')
        ->toContain('bentuk_file')
        ->toContain('link_file')
        ->toContain('nomor_produk_hukum_terkait')
        ->not->toContain('tipe_dokumen')
        ->not->toContain('tempat_penetapan')
        ->not->toContain('status_publish')
        ->not->toContain('bidang_hukum');

    expect($controller)
        ->toContain('bidangOptions')
        ->toContain('jenisOptions')
        ->toContain('tahunOptions')
        ->toContain("'bentuk_file' => ['required', Rule::in(['file', 'link'])]")
        ->toContain("'link_file' => ['nullable', 'url', 'max:2048']")
        ->toContain("redirect()->away(\$file->link_file)")
        ->not->toContain('status_publish')
        ->not->toContain('tipe_dokumen')
        ->not->toContain('nomor_peraturan_terkait');

    expect($produkHukumModel)
        ->toContain('nomor_peraturan_keputusan')
        ->toContain('bidang_pengaturan')
        ->toContain('muatan_substansial')
        ->not->toContain('status_publish');

    expect($fileModel)
        ->toContain('bentuk_file')
        ->toContain('link_file');

    expect($relasiModel)
        ->toContain('nomor_produk_hukum_terkait')
        ->not->toContain('nomor_peraturan_terkait');

    expect($view)
        ->toContain('Nomor Peraturan/Keputusan')
        ->toContain('Bidang Pengaturan')
        ->toContain('Sumber LN/TBN')
        ->toContain('Sumber TLN/TBN')
        ->toContain('Muatan Substansial')
        ->toContain('Bentuk File')
        ->toContain('Link Produk Hukum')
        ->toContain('Nomor Produk Hukum Terkait')
        ->not->toContain('Status Publish')
        ->not->toContain('Tempat Penetapan')
        ->not->toContain('Bidang Hukum');
});
