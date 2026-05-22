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
    Route::get('/snp/perekaman', function () {
        return view('layouts.snp.perekaman');
    })->name('snp.perekaman');
});

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
});
