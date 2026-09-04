<?php

use App\Http\Controllers\Administrasi\PengajuanController;
use App\Http\Controllers\Administrasi\ManajemenUserController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Djsn\PerekamanDjsnController;
use App\Http\Controllers\Djsn\ReportDjsnController;
use App\Http\Controllers\Djsn\ReviuDjsnController;
use App\Http\Controllers\Djsn\TanggapanDjsnController;
use App\Http\Controllers\Djsn\TindakLanjutDjsnController;
use App\Http\Controllers\Eksternal\PerekamanEksternalController;
use App\Http\Controllers\Eksternal\ReportEksternalController;
use App\Http\Controllers\Eksternal\ReviuEksternalController;
use App\Http\Controllers\Eksternal\TindakLanjutEksternalController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProdukHukum\ProdukHukumController;
use App\Http\Controllers\Ragab\PerekamanRagabController;
use App\Http\Controllers\Ragab\ReportRagabController;
use App\Http\Controllers\Ragab\ReviuRagabController;
use App\Http\Controllers\Ragab\TindakLanjutRagabController;
use App\Http\Controllers\Rawas\PerekamanRawasController;
use App\Http\Controllers\Rawas\ReportRawasController;
use App\Http\Controllers\Rawas\ReviuRawasController;
use App\Http\Controllers\Rawas\TindakLanjutRawasController;
use App\Http\Controllers\Snp\KompilasiSnpController;
use App\Http\Controllers\Snp\PerekamanSnpController;
use App\Http\Controllers\Snp\ReportSnpController;
use App\Http\Controllers\Snp\ReviuSnpController;
use App\Http\Controllers\Snp\TanggapanSnpController;
use App\Http\Controllers\Snp\TindakLanjutSnpController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// Route::get('/register', function () {
//     return view('auth.register');
// });

// Route::get('/dashboard', function () {
//     return view('dashboard');
// })->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';

Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])
    ->name('google.redirect');

Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])
    ->name('google.callback');

// TODO: ADMINISTRASI
Route::middleware(['auth', 'verified'])->group(function () {
        Route::get('/administrasi/manajemen-user', [ManajemenUserController::class, 'index'])
            ->name('administrasi.manajemen-user.index');

        Route::post('/administrasi/manajemen-user', [ManajemenUserController::class, 'store'])
            ->name('administrasi.manajemen-user.store');

        Route::patch('/administrasi/manajemen-user/{user}', [ManajemenUserController::class, 'update'])
            ->name('administrasi.manajemen-user.update');

        Route::delete('/administrasi/manajemen-user/{user}', [ManajemenUserController::class, 'destroy'])
            ->name('administrasi.manajemen-user.destroy');

        Route::get('/administrasi/pengajuan', [PengajuanController::class, 'index'])
            ->name('administrasi.pengajuan.index');

        Route::patch('/administrasi/pengajuan/{deleteRequest}/verify', [PengajuanController::class, 'verify'])
            ->name('administrasi.pengajuan.verify');

        Route::patch('/administrasi/pengajuan/{deleteRequest}/approve', [PengajuanController::class, 'approve'])
            ->name('administrasi.pengajuan.approve');

    Route::patch('/administrasi/pengajuan/{deleteRequest}/reject', [PengajuanController::class, 'reject'])
        ->name('administrasi.pengajuan.reject');
    });

// TODO: PRODUK HUKUM
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/produk-hukum', [ProdukHukumController::class, 'index'])
        ->name('produk-hukum.index');

    Route::post('/produk-hukum', [ProdukHukumController::class, 'store'])
        ->name('produk-hukum.store');

    Route::post('/produk-hukum/{produkHukum}/request-access', [ProdukHukumController::class, 'requestAccess'])
        ->name('produk-hukum.request-access');

    Route::delete('/produk-hukum/{produkHukum}/request-delete', [ProdukHukumController::class, 'requestDelete'])
        ->name('produk-hukum.request-delete');

    Route::get('/produk-hukum/file/{file}/download', [ProdukHukumController::class, 'downloadFile'])
        ->name('produk-hukum.file.download');
});

// TODO: SNP
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/snp/perekaman', [PerekamanSnpController::class, 'index'])
        ->name('snp.perekaman');

    Route::post('/snp/perekaman', [PerekamanSnpController::class, 'storeRecord'])
        ->name('snp.perekaman.store');

    Route::post('/snp/perekaman/{record}/butir', [PerekamanSnpController::class, 'storeButir'])
        ->name('snp.perekaman.butir.store');

    Route::patch('/snp/perekaman/{record}', [PerekamanSnpController::class, 'update'])
        ->name('snp.perekaman.update');

    Route::get('/snp/perekaman/{record}/dokumen', [PerekamanSnpController::class, 'downloadDokumen'])
        ->name('snp.perekaman.dokumen');

    Route::get('/snp/perekaman/{record}/dokumen-memo', [PerekamanSnpController::class, 'downloadDokumenMemo'])
        ->name('snp.perekaman.dokumen-memo');

    Route::delete('/snp/perekaman/{record}/request-delete', [PerekamanSnpController::class, 'requestDelete'])
        ->name('snp.perekaman.destroy.request');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/snp/tanggapan', [TanggapanSnpController::class, 'index'])
        ->name('snp.tanggapan.index');

    Route::post('/snp/tanggapan/{butir}', [TanggapanSnpController::class, 'store'])
        ->name('snp.tanggapan.store');

    Route::patch('/snp/tanggapan/{tanggapan}', [TanggapanSnpController::class, 'update'])
        ->name('snp.tanggapan.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/snp/kompilasi', [KompilasiSnpController::class, 'index'])
        ->name('snp.kompilasi.index');

    Route::post('/snp/kompilasi/{butir}', [KompilasiSnpController::class, 'store'])
        ->name('snp.kompilasi.store');

    Route::get('/snp/kompilasi/{kompilasi}/dokumen', [KompilasiSnpController::class, 'downloadDokumen'])
        ->name('snp.kompilasi.dokumen');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/snp/reviu', [ReviuSnpController::class, 'index'])
        ->name('snp.reviu.index');

    Route::patch('/snp/reviu/{review}', [ReviuSnpController::class, 'update'])
        ->name('snp.reviu.update');

    Route::get('/snp/reviu/{review}/dokumen', [ReviuSnpController::class, 'downloadDokumen'])
        ->name('snp.reviu.dokumen');

    Route::get('/snp/reviu/{review}/dokumen-memo', [ReviuSnpController::class, 'downloadDokumenMemo'])
        ->name('snp.reviu.dokumen-memo');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/snp/tindak-lanjut', [TindakLanjutSnpController::class, 'index'])
        ->name('snp.tindak-lanjut.index');

    Route::post('/snp/tindak-lanjut', [TindakLanjutSnpController::class, 'store'])
        ->name('snp.tindak-lanjut.store');

    Route::patch('/snp/tindak-lanjut/{tindakLanjut}', [TindakLanjutSnpController::class, 'update'])
        ->name('snp.tindak-lanjut.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/snp/report', [ReportSnpController::class, 'index'])
        ->name('snp.report.index');

    Route::post('/snp/report/cetak', [ReportSnpController::class, 'cetak'])
        ->name('snp.report.cetak');

    Route::post('/snp/report/download', [ReportSnpController::class, 'download'])
        ->name('snp.report.download');

    Route::post('/snp/report/cetak-custom', [ReportSnpController::class, 'cetakCustom'])
        ->name('snp.report.cetak-custom');

    Route::post('/snp/report/download-custom', [ReportSnpController::class, 'downloadCustom'])
        ->name('snp.report.download-custom');

    Route::post('/snp/report/cetak-excel', [ReportSnpController::class, 'cetakExcel'])
        ->name('snp.report.cetak-excel');

    Route::post('/snp/report/cetak-excel-custom', [ReportSnpController::class, 'cetakExcelCustom'])
        ->name('snp.report.cetak-excel-custom');
});

// TODO: RAGAB
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/ragab/perekaman', [PerekamanRagabController::class, 'index'])
        ->name('ragab.perekaman');

    Route::post('/ragab/perekaman', [PerekamanRagabController::class, 'storeRecord'])
        ->name('ragab.perekaman.store');

    Route::post('/ragab/perekaman/{record}/butir', [PerekamanRagabController::class, 'storeButir'])
        ->name('ragab.perekaman.butir.store');

    Route::patch('/ragab/perekaman/{record}', [PerekamanRagabController::class, 'update'])
        ->name('ragab.perekaman.update');

    Route::get('/ragab/perekaman/{record}/dokumen', [PerekamanRagabController::class, 'downloadDokumen'])
        ->name('ragab.perekaman.dokumen');

    Route::get('/ragab/perekaman/{record}/dokumen-memo', [PerekamanRagabController::class, 'downloadDokumenMemo'])
        ->name('ragab.perekaman.dokumen-memo');

    Route::delete('/ragab/perekaman/{record}/request-delete', [PerekamanRagabController::class, 'requestDelete'])
        ->name('ragab.perekaman.destroy.request');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/ragab/tindak-lanjut', [TindakLanjutRagabController::class, 'index'])
        ->name('ragab.tindak-lanjut.index');

    Route::post('/ragab/tindak-lanjut', [TindakLanjutRagabController::class, 'store'])
        ->name('ragab.tindak-lanjut.store');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/ragab/reviu', [ReviuRagabController::class, 'index'])
        ->name('ragab.reviu.index');

    Route::patch('/ragab/reviu/{review}', [ReviuRagabController::class, 'update'])
        ->name('ragab.reviu.update');

    Route::get('/ragab/reviu/{review}/dokumen', [ReviuRagabController::class, 'downloadDokumen'])
        ->name('ragab.reviu.dokumen');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/ragab/report', [ReportRagabController::class, 'index'])
        ->name('ragab.report.index');

    Route::post('/ragab/report/cetak', [ReportRagabController::class, 'cetak'])
        ->name('ragab.report.cetak');

    Route::post('/ragab/report/cetak-custom', [ReportRagabController::class, 'cetakCustom'])
        ->name('ragab.report.cetak-custom');

    Route::post('/ragab/report/cetak-excel', [ReportRagabController::class, 'cetakExcel'])
        ->name('ragab.report.cetak-excel');

    Route::post('/ragab/report/cetak-excel-custom', [ReportRagabController::class, 'cetakExcelCustom'])
        ->name('ragab.report.cetak-excel-custom');
});

// TODO: RAPAT EKSTERNAL
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/eksternal/perekaman', [PerekamanEksternalController::class, 'index'])
        ->name('eksternal.perekaman');

    Route::post('/eksternal/perekaman', [PerekamanEksternalController::class, 'storeRecord'])
        ->name('eksternal.perekaman.store');

    Route::post('/eksternal/perekaman/{record}/butir', [PerekamanEksternalController::class, 'storeButir'])
        ->name('eksternal.perekaman.butir.store');

    Route::patch('/eksternal/perekaman/{record}', [PerekamanEksternalController::class, 'update'])
        ->name('eksternal.perekaman.update');

    Route::get('/eksternal/perekaman/{record}/dokumen', [PerekamanEksternalController::class, 'downloadDokumen'])
        ->name('eksternal.perekaman.dokumen');

    Route::get('/eksternal/perekaman/{record}/dokumen-memo', [PerekamanEksternalController::class, 'downloadDokumenMemo'])
        ->name('eksternal.perekaman.dokumen-memo');

    Route::delete('/eksternal/perekaman/{record}/request-delete', [PerekamanEksternalController::class, 'requestDelete'])
        ->name('eksternal.perekaman.destroy.request');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/eksternal/tindak-lanjut', [TindakLanjutEksternalController::class, 'index'])
        ->name('eksternal.tindak-lanjut.index');

    Route::post('/eksternal/tindak-lanjut', [TindakLanjutEksternalController::class, 'store'])
        ->name('eksternal.tindak-lanjut.store');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/eksternal/reviu', [ReviuEksternalController::class, 'index'])
        ->name('eksternal.reviu.index');

    Route::patch('/eksternal/reviu/{review}', [ReviuEksternalController::class, 'update'])
        ->name('eksternal.reviu.update');

    Route::get('/eksternal/reviu/{review}/dokumen', [ReviuEksternalController::class, 'downloadDokumen'])
        ->name('eksternal.reviu.dokumen');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/eksternal/report', [ReportEksternalController::class, 'index'])
        ->name('eksternal.report.index');

    Route::post('/eksternal/report/cetak', [ReportEksternalController::class, 'cetak'])
        ->name('eksternal.report.cetak');

    Route::post('/eksternal/report/cetak-custom', [ReportEksternalController::class, 'cetakCustom'])
        ->name('eksternal.report.cetak-custom');

    Route::post('/eksternal/report/cetak-excel', [ReportEksternalController::class, 'cetakExcel'])
        ->name('eksternal.report.cetak-excel');

    Route::post('/eksternal/report/cetak-excel-custom', [ReportEksternalController::class, 'cetakExcelCustom'])
        ->name('eksternal.report.cetak-excel-custom');
});

// TODO: RAWAS
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/rawas/perekaman', [PerekamanRawasController::class, 'index'])
        ->name('rawas.perekaman');

    Route::post('/rawas/perekaman', [PerekamanRawasController::class, 'storeRecord'])
        ->name('rawas.perekaman.store');

    Route::post('/rawas/perekaman/{record}/butir', [PerekamanRawasController::class, 'storeButir'])
        ->name('rawas.perekaman.butir.store');

    Route::patch('/rawas/perekaman/{record}', [PerekamanRawasController::class, 'update'])
        ->name('rawas.perekaman.update');

    Route::get('/rawas/perekaman/{record}/dokumen', [PerekamanRawasController::class, 'downloadDokumen'])
        ->name('rawas.perekaman.dokumen');

    Route::get('/rawas/perekaman/{record}/dokumen-memo', [PerekamanRawasController::class, 'downloadDokumenMemo'])
        ->name('rawas.perekaman.dokumen-memo');

    Route::delete('/rawas/perekaman/{record}/request-delete', [PerekamanRawasController::class, 'requestDelete'])
        ->name('rawas.perekaman.destroy.request');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/rawas/tindak-lanjut', [TindakLanjutRawasController::class, 'index'])
        ->name('rawas.tindak-lanjut.index');

    Route::post('/rawas/tindak-lanjut', [TindakLanjutRawasController::class, 'store'])
        ->name('rawas.tindak-lanjut.store');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/rawas/reviu', [ReviuRawasController::class, 'index'])
        ->name('rawas.reviu.index');

    Route::patch('/rawas/reviu/{review}', [ReviuRawasController::class, 'update'])
        ->name('rawas.reviu.update');

    Route::get('/rawas/reviu/{review}/dokumen', [ReviuRawasController::class, 'downloadDokumen'])
        ->name('rawas.reviu.dokumen');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/rawas/report', [ReportRawasController::class, 'index'])
        ->name('rawas.report.index');

    Route::post('/rawas/report/cetak', [ReportRawasController::class, 'cetak'])
        ->name('rawas.report.cetak');

    Route::post('/rawas/report/cetak-custom', [ReportRawasController::class, 'cetakCustom'])
        ->name('rawas.report.cetak-custom');

    Route::post('/rawas/report/cetak-excel', [ReportRawasController::class, 'cetakExcel'])
        ->name('rawas.report.cetak-excel');

    Route::post('/rawas/report/cetak-excel-custom', [ReportRawasController::class, 'cetakExcelCustom'])
        ->name('rawas.report.cetak-excel-custom');
});

// TODO: DJSN
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/djsn/perekaman', [PerekamanDjsnController::class, 'index'])
        ->name('djsn.perekaman');

    Route::post('/djsn/perekaman', [PerekamanDjsnController::class, 'storeRecord'])
        ->name('djsn.perekaman.store');

    Route::post('/djsn/perekaman/{record}/butir', [PerekamanDjsnController::class, 'storeButir'])
        ->name('djsn.perekaman.butir.store');

    Route::patch('/djsn/perekaman/{record}', [PerekamanDjsnController::class, 'update'])
        ->name('djsn.perekaman.update');

    Route::get('/djsn/perekaman/{record}/dokumen', [PerekamanDjsnController::class, 'downloadDokumen'])
        ->name('djsn.perekaman.dokumen');

    Route::delete('/djsn/perekaman/{record}/request-delete', [PerekamanDjsnController::class, 'requestDelete'])
        ->name('djsn.perekaman.destroy.request');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/djsn/tanggapan', [TanggapanDjsnController::class, 'index'])
        ->name('djsn.tanggapan.index');

    Route::post('/djsn/tanggapan/{butir}', [TanggapanDjsnController::class, 'store'])
        ->name('djsn.tanggapan.store');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/djsn/reviu', [ReviuDjsnController::class, 'index'])
        ->name('djsn.reviu.index');

    Route::patch('/djsn/reviu/{review}', [ReviuDjsnController::class, 'update'])
        ->name('djsn.reviu.update');

    Route::get('/djsn/reviu/{review}/dokumen', [ReviuDjsnController::class, 'downloadDokumen'])
        ->name('djsn.reviu.dokumen');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/djsn/tindak-lanjut', [TindakLanjutDjsnController::class, 'index'])
        ->name('djsn.tindak-lanjut.index');

    Route::post('/djsn/tindak-lanjut', [TindakLanjutDjsnController::class, 'store'])
        ->name('djsn.tindak-lanjut.store');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/djsn/report', [ReportDjsnController::class, 'index'])
        ->name('djsn.report.index');

    Route::post('/djsn/report/cetak', [ReportDjsnController::class, 'cetak'])
        ->name('djsn.report.cetak');

    Route::post('/djsn/report/cetak-custom', [ReportDjsnController::class, 'cetakCustom'])
        ->name('djsn.report.cetak-custom');

    Route::post('/djsn/report/cetak-excel', [ReportDjsnController::class, 'cetakExcel'])
        ->name('djsn.report.cetak-excel');

    Route::post('/djsn/report/cetak-excel-custom', [ReportDjsnController::class, 'cetakExcelCustom'])
        ->name('djsn.report.cetak-excel-custom');
});
