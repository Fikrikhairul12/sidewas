<?php

use App\Services\SnpButirContent;
use App\Services\SuratPutaranService;
use App\Services\SuratTahapBackfill;
use App\Services\WorkflowReadinessService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class);
require_once __DIR__.'/WorkflowTestSetup.php';

beforeEach(function () {
    setUpWorkflowTestDatabase();
    config(['sidewas.alur_revisi_enabled' => false]);
});

test('R7 pemeriksaan kosong siap dan tidak mengubah database atau flag', function () {
    $report = app(WorkflowReadinessService::class)->inspect();
    expect($report['ready'])->toBeTrue()->and(config('sidewas.alur_revisi_enabled'))->toBeFalse();
    $this->artisan('workflow:check-revisi --json')->assertSuccessful();
    expect(DB::connection('mysql_snp')->table('tb_surat_tahap')->count())->toBe(0);
});

test('R7 flag nonaktif melewati gate deploy tanpa memeriksa koneksi data', function () {
    DB::purge('mysql_snp');
    config(['database.connections.mysql_snp.driver' => 'driver_tidak_ada']);
    $this->artisan('workflow:check-revisi --if-enabled')->assertSuccessful();
});

test('R7 data lama tanpa bukti menghentikan aktivasi tanpa membuat salinan atau tanggal', function (string $module) {
    $butir = workflowButir($module);
    app(SuratTahapBackfill::class)->run('mysql_'.$module);
    $before = app(WorkflowReadinessService::class)->fingerprint($module, $butir->record->id);
    $report = app(WorkflowReadinessService::class)->inspect([$module]);
    expect($report['ready'])->toBeFalse()->and(array_column($report['modules'][$module]['issues'], 'code'))->toContain('tahap_aktif_belum_sah', 'tanggal_tahap_belum_terverifikasi');
    $this->artisan('workflow:check-revisi --module='.$module)->assertFailed();
    config(['sidewas.alur_revisi_enabled' => true]);
    $this->artisan('workflow:check-revisi --if-enabled --module='.$module)->assertFailed();
    expect(app(WorkflowReadinessService::class)->fingerprint($module, $butir->record->id))->toBe($before)
        ->and(DB::connection('mysql_'.$module)->table('tb_kompilasi_bahan')->count())->toBe(0);
})->with(['snp', 'djsn']);

test('R7 pemeriksaan akun asli disiapkan tanpa membaca password atau email dan tetap tanpa menulis', function () {
    $actor = workflowUser('super_admin');
    $before = DB::connection('mysql')->table('users')->orderBy('id')->get()->toJson();
    DB::connection('mysql')->enableQueryLog();
    DB::connection('mysql')->flushQueryLog();
    $report = app(WorkflowReadinessService::class)->inspect(['snp', 'djsn'], true);
    $queries = implode(' ', array_column(DB::connection('mysql')->getQueryLog(), 'query'));
    DB::connection('mysql')->disableQueryLog();
    expect($report['ready'])->toBeTrue()->and($report['main_checked'])->toBeTrue()->and(strtolower($queries))->not->toContain('password', 'email')
        ->and(DB::connection('mysql')->table('users')->orderBy('id')->get()->toJson())->toBe($before);
    $this->artisan('workflow:check-revisi --with-main --json')->assertSuccessful();
});

test('R7 gate menolak konfigurasi flag yang terpisah dan akun pelaksana yang tidak tersedia', function () {
    config(['sidewas.alur_revisi_enabled' => true, 'sidewas.reviu_putaran_enabled' => false]);
    $report = app(WorkflowReadinessService::class)->inspect(['snp']);
    expect($report['ready'])->toBeFalse()->and(array_column($report['modules']['snp']['issues'], 'code'))->toContain('flag_putaran_belum_aktif');
    config(['sidewas.alur_revisi_enabled' => false]);
    $report = app(WorkflowReadinessService::class)->inspect(['snp'], true);
    expect($report['ready'])->toBeFalse()->and(array_column($report['modules']['snp']['issues'], 'code'))->toContain('petugas_kompilasi_tidak_tersedia');
});

test('R7 modul yang salah dan fingerprint surat yang tidak ada ditolak', function () {
    $this->artisan('workflow:check-revisi --module=rawas')->assertFailed();
    $this->artisan('workflow:check-revisi --record=123')->assertFailed();
    $this->artisan('workflow:check-revisi --module=snp --record=123')->assertFailed();
});

test('R7 pemeriksaan menemukan referensi dokumen dan gambar yang tidak tersedia tanpa mengubah sumber', function (string $module) {
    Storage::fake('public');
    $butir = workflowButir($module);
    $db = DB::connection('mysql_'.$module);
    $image = $module.'-images/'.$butir->record->id.'/12345678-1234-1234-1234-123456789abc.png';
    $db->table('tb_record')->where('id', $butir->record->id)->update(['dokumen' => 'dokumen/arsip.pdf']);
    $db->table('tb_butir_'.$module)->where('id', $butir->id)->update(['butir_'.$module => SnpButirContent::PREFIX.'<p>Isi lama</p><img src="/'.$module.'/perekaman/'.$butir->record->id.'/gambar/12345678-1234-1234-1234-123456789abc.png">']);
    $before = app(WorkflowReadinessService::class)->fingerprint($module, $butir->record->id);
    $report = app(WorkflowReadinessService::class)->inspect([$module]);
    expect(collect($report['modules'][$module]['issues'])->where('code', 'berkas_sumber_tidak_tersedia'))->toHaveCount(2);
    Storage::disk('public')->put('dokumen/arsip.pdf', 'Berkas uji');
    Storage::disk('local')->put($image, 'Gambar uji');
    $report = app(WorkflowReadinessService::class)->inspect([$module]);
    expect(array_column($report['modules'][$module]['issues'], 'code'))->not->toContain('berkas_sumber_tidak_tersedia')
        ->and(app(WorkflowReadinessService::class)->fingerprint($module, $butir->record->id))->toBe($before);
})->with(['snp', 'djsn']);

test('R7 reviu lama pada tahap aktif tetap menjadi penghalang dan tidak direset', function (string $module) {
    $butir = workflowButir($module);
    app(SuratPutaranService::class)->legacyFor('mysql_'.$module, $butir->record->getRawOriginal());
    app(SuratTahapBackfill::class)->run('mysql_'.$module);
    $db = DB::connection('mysql_'.$module);
    $stage = $butir->record->tahapanProses()->firstOrFail();
    $db->table('tb_record')->where('id', $butir->record->id)->update(['tahap_aktif_id' => $stage->id]);
    $id = $db->table('tb_review')->insertGetId(['id_butir_'.$module => $butir->{'id_butir_'.$module},
        'tahap_id' => $stage->id, 'status' => 'dalam_proses_tindak_lanjut_direksi', 'komite_id' => 1]);
    $before = $db->table('tb_review')->find($id);
    $report = app(WorkflowReadinessService::class)->inspect([$module]);
    expect(array_column($report['modules'][$module]['issues'], 'code'))->toContain('reviu_aktif_masih_alur_lama')
        ->and($db->table('tb_review')->find($id))->toEqual($before);
})->with(['snp', 'djsn']);

test('R7 slot kompilasi kosong tidak dianggap bahan historis resmi tetapi isi kompilasi yang ada tetap diperiksa', function (string $module) {
    $butir = workflowButir($module);
    app(SuratPutaranService::class)->legacyFor('mysql_'.$module, $butir->record->getRawOriginal());
    app(SuratTahapBackfill::class)->run('mysql_'.$module);
    $db = DB::connection('mysql_'.$module);
    $stage = $butir->record->tahapanProses()->firstOrFail();
    $id = $db->table('tb_kompilasi')->insertGetId(['id_butir_'.$module => $butir->{'id_butir_'.$module},
        'tahap_id' => $stage->id, 'status' => 'belum_dikompilasi']);
    $db->table('tb_surat_rekonsiliasi')->insert(['record_id' => $butir->record->id, 'tahap_id' => $stage->id,
        'tabel_sumber' => 'tb_kompilasi', 'sumber_id' => $id, 'kode' => 'bahan_historis_belum_terbukti', 'status' => 'terbuka', 'alasan' => 'Slot lama']);
    $report = app(WorkflowReadinessService::class)->inspect([$module]);
    $issue = collect($report['modules'][$module]['issues'])->firstWhere('code', 'placeholder_belum_dikompilasi');
    expect($issue['severity'])->toBe('warning');
    $db->table('tb_kompilasi')->where('id', $id)->update(['hasil_kompilasi' => 'Hasil yang belum memiliki bukti bahan']);
    $report = app(WorkflowReadinessService::class)->inspect([$module]);
    expect(array_column($report['modules'][$module]['issues'], 'code'))->toContain('bahan_historis_belum_terbukti')
        ->not->toContain('placeholder_belum_dikompilasi');
})->with(['snp', 'djsn']);

test('R7 fingerprint berubah ketika bahan sumber berubah dan stabil pada pembacaan ulang', function (string $module) {
    $butir = workflowButir($module);
    app(SuratTahapBackfill::class)->run('mysql_'.$module);
    $service = app(WorkflowReadinessService::class);
    $before = $service->fingerprint($module, $butir->record->id);
    expect($service->fingerprint($module, $butir->record->id))->toBe($before);
    DB::connection('mysql_'.$module)->table('tb_butir_'.$module)->where('id', $butir->id)->update(['butir_'.$module => 'Berubah saat rencana sedang ditinjau']);
    expect($service->fingerprint($module, $butir->record->id))->not->toBe($before);
})->with(['snp', 'djsn']);
