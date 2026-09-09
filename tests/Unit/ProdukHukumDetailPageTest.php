<?php

use App\Models\ProdukHukumFile;

test('produk hukum detail uses a dedicated page with format aware file actions', function () {
    $basePath = dirname(__DIR__, 2);

    $routes = file_get_contents($basePath.'/routes/web.php');
    $controller = file_get_contents($basePath.'/app/Http/Controllers/ProdukHukum/ProdukHukumController.php');
    $fileModel = file_get_contents($basePath.'/app/Models/ProdukHukumFile.php');
    $indexView = file_get_contents($basePath.'/resources/views/layouts/produk-hukum/index.blade.php');
    $showView = file_get_contents($basePath.'/resources/views/layouts/produk-hukum/show.blade.php');

    expect($routes)
        ->toContain("->name('produk-hukum.show')")
        ->toContain("->name('produk-hukum.file.preview')");

    expect($controller)
        ->toContain('public function show(ProdukHukum $produkHukum): View')
        ->toContain('public function previewFile(ProdukHukumFile $file): BinaryFileResponse')
        ->toContain("if (\$validated['bentuk_file'] === 'file')")
        ->toContain("setContentDisposition('inline', \$filename)");

    expect($fileModel)
        ->toContain('public function isPreviewable(): bool')
        ->toContain("['pdf', 'jpg', 'jpeg', 'png']");

    expect($indexView)
        ->toContain("route('produk-hukum.show', \$produk)")
        ->toContain('singkatanPeraturan = jenisSingkatan[selectedJenis]')
        ->toContain('filteredRelatedOptions()')
        ->toContain(':disabled="fileMode !== \'file\'"')
        ->toContain(':disabled="fileMode !== \'link\'"')
        ->not->toContain('openDetailModal');

    expect($showView)
        ->toContain('Ringkasan Peraturan')
        ->toContain('Metadata Peraturan')
        ->toContain('Status Dokumen')
        ->toContain('Hubungan Peraturan')
        ->toContain('Buka Tautan')
        ->toContain("route('produk-hukum.file.preview', \$file)")
        ->toContain("route('produk-hukum.file.download', \$file)")
        ->not->toContain('lg:sticky');
});

test('only browser friendly uploaded files are previewable', function () {
    $pdf = new ProdukHukumFile([
        'bentuk_file' => 'file',
        'nama_file' => 'peraturan.pdf',
    ]);
    $image = new ProdukHukumFile([
        'bentuk_file' => 'file',
        'nama_file' => 'lampiran.PNG',
    ]);
    $spreadsheet = new ProdukHukumFile([
        'bentuk_file' => 'file',
        'nama_file' => 'lampiran.xlsx',
    ]);
    $link = new ProdukHukumFile([
        'bentuk_file' => 'link',
        'nama_file' => 'Dokumen JDIH',
    ]);

    expect($pdf->isPreviewable())->toBeTrue()
        ->and($image->isPreviewable())->toBeTrue()
        ->and($spreadsheet->isPreviewable())->toBeFalse()
        ->and($link->isPreviewable())->toBeFalse();
});
