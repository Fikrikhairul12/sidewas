<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Snp\PerekamanSnpController;
use App\Http\Controllers\Administrasi\PengajuanController;
use App\Http\Controllers\Snp\TanggapanSnpController;
use App\Http\Controllers\Snp\ReviuSnpController;
use App\Http\Controllers\Snp\TindakLanjutSnpController;
use App\Http\Controllers\Snp\ReportSnpController;
use App\Http\Controllers\Ragab\PerekamanRagabController;
use App\Http\Controllers\Ragab\TindakLanjutRagabController;
use App\Http\Controllers\Ragab\ReviuRagabController;
use App\Http\Controllers\Ragab\ReportRagabController;
use App\Http\Controllers\Rawas\PerekamanRawasController;
use App\Http\Controllers\Rawas\TindakLanjutRawasController;
use App\Http\Controllers\Rawas\ReviuRawasController;
use App\Http\Controllers\Rawas\ReportRawasController;
use App\Http\Controllers\Djsn\PerekamanDjsnController;
use App\Http\Controllers\Djsn\TanggapanDjsnController;
use App\Http\Controllers\Djsn\TindakLanjutDjsnController;
use App\Http\Controllers\Djsn\ReviuDjsnController;
use App\Http\Controllers\Djsn\ReportDjsnController;

Route::get('/', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

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

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/snp/perekaman', [PerekamanSnpController::class, 'index'])
        ->name('snp.perekaman');

    Route::post('/snp/perekaman', [PerekamanSnpController::class, 'storeRecord'])
        ->name('snp.perekaman.store');

    Route::post('/snp/perekaman/{record}/butir', [PerekamanSnpController::class, 'storeButir'])
        ->name('snp.perekaman.butir.store');

    Route::get('/snp/perekaman/{record}/dokumen', [PerekamanSnpController::class, 'downloadDokumen'])
        ->name('snp.perekaman.dokumen');

    Route::delete('/snp/perekaman/{record}/request-delete', [PerekamanSnpController::class, 'requestDelete'])
        ->name('snp.perekaman.destroy.request');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/administrasi/pengajuan', [PengajuanController::class, 'index'])
        ->name('administrasi.pengajuan.index');

    Route::patch('/administrasi/pengajuan/{deleteRequest}/verify', [PengajuanController::class, 'verify'])
        ->name('administrasi.pengajuan.verify');

    Route::patch('/administrasi/pengajuan/{deleteRequest}/approve', [PengajuanController::class, 'approve'])
        ->name('administrasi.pengajuan.approve');

    Route::patch('/administrasi/pengajuan/{deleteRequest}/reject', [PengajuanController::class, 'reject'])
        ->name('administrasi.pengajuan.reject');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/snp/tanggapan', [TanggapanSnpController::class, 'index'])
        ->name('snp.tanggapan.index');

    Route::post('/snp/tanggapan/{butir}', [TanggapanSnpController::class, 'store'])
        ->name('snp.tanggapan.store');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/snp/reviu', [ReviuSnpController::class, 'index'])
        ->name('snp.reviu.index');

    Route::patch('/snp/reviu/{review}', [ReviuSnpController::class, 'update'])
        ->name('snp.reviu.update');

    Route::get('/snp/reviu/{review}/dokumen', [ReviuSnpController::class, 'downloadDokumen'])
        ->name('snp.reviu.dokumen');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/snp/tindak-lanjut', [TindakLanjutSnpController::class, 'index'])
        ->name('snp.tindak-lanjut.index');

    Route::post('/snp/tindak-lanjut', [TindakLanjutSnpController::class, 'store'])
        ->name('snp.tindak-lanjut.store');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/snp/report', [ReportSnpController::class, 'index'])
        ->name('snp.report.index');

    Route::post('/snp/report/cetak', [ReportSnpController::class, 'cetak'])
        ->name('snp.report.cetak');

    Route::post('/snp/report/cetak-custom', [ReportSnpController::class, 'cetakCustom'])
        ->name('snp.report.cetak-custom');

    Route::post('/snp/report/cetak-excel', [ReportSnpController::class, 'cetakExcel'])
        ->name('snp.report.cetak-excel');

    Route::post('/snp/report/cetak-excel-custom', [ReportSnpController::class, 'cetakExcelCustom'])
        ->name('snp.report.cetak-excel-custom');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/ragab/perekaman', [PerekamanRagabController::class, 'index'])
        ->name('ragab.perekaman');

    Route::post('/ragab/perekaman', [PerekamanRagabController::class, 'storeRecord'])
        ->name('ragab.perekaman.store');

    Route::post('/ragab/perekaman/{record}/butir', [PerekamanRagabController::class, 'storeButir'])
        ->name('ragab.perekaman.butir.store');

    Route::get('/ragab/perekaman/{record}/dokumen', [PerekamanRagabController::class, 'downloadDokumen'])
        ->name('ragab.perekaman.dokumen');

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

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/rawas/perekaman', [PerekamanRawasController::class, 'index'])
        ->name('rawas.perekaman');

    Route::post('/rawas/perekaman', [PerekamanRawasController::class, 'storeRecord'])
        ->name('rawas.perekaman.store');

    Route::post('/rawas/perekaman/{record}/butir', [PerekamanRawasController::class, 'storeButir'])
        ->name('rawas.perekaman.butir.store');

    Route::get('/rawas/perekaman/{record}/dokumen', [PerekamanRawasController::class, 'downloadDokumen'])
        ->name('rawas.perekaman.dokumen');

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

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/djsn/perekaman', [PerekamanDjsnController::class, 'index'])
        ->name('djsn.perekaman');

    Route::post('/djsn/perekaman', [PerekamanDjsnController::class, 'storeRecord'])
        ->name('djsn.perekaman.store');

    Route::post('/djsn/perekaman/{record}/butir', [PerekamanDjsnController::class, 'storeButir'])
        ->name('djsn.perekaman.butir.store');

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
