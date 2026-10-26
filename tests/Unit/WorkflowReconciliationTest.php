<?php

use App\Models\LogActivity;
use App\Services\KompilasiSalinanService;
use App\Services\ReviuBahanPreviewService;
use App\Services\SuratPutaranService;
use App\Services\SuratRekonsiliasiService;
use App\Services\SuratTahapBackfill;
use App\Services\WorkflowReadinessService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

uses(TestCase::class);
require_once __DIR__.'/WorkflowTestSetup.php';

beforeEach(function () {
    setUpWorkflowTestDatabase();
    config(['sidewas.alur_revisi_enabled' => false, 'sidewas.reviu_putaran_enabled' => true]);
    Storage::fake('public');
    $this->travelTo(now()->setDate(2026, 11, 20)->setTime(10, 0));
});

function reconciliationFixture(string $module): array
{
    $actor = workflowUser('super_admin');
    $butir = workflowButir($module);
    app(SuratPutaranService::class)->legacyFor('mysql_'.$module, $butir->record->getRawOriginal());
    app(SuratTahapBackfill::class)->run('mysql_'.$module);
    $record = $butir->record->fresh();
    $stage = $record->tahapanProses()->firstOrFail();
    Storage::disk('local')->put('arsip/bukti.pdf', 'Arsip bukti yang diperiksa oleh Super Admin');
    $evidence = ['disk' => 'local', 'path' => 'arsip/bukti.pdf', 'sha256' => hash('sha256', Storage::disk('local')->get('arsip/bukti.pdf'))];

    return compact('module', 'actor', 'butir', 'record', 'stage', 'evidence');
}

function reconciliationPlan(array $f, string $action, array $details): array
{
    return ['version' => 1, 'module' => $f['module'], 'record_id' => $f['record']->id, 'action' => $action, 'details' => $details,
        'expected_fingerprint' => app(WorkflowReadinessService::class)->fingerprint($f['module'], $f['record']->id),
        'reason' => 'Bukti penerbitan dan riwayat telah diperiksa petugas berwenang.', 'evidence' => $f['evidence']];
}

test('R7 validasi tanpa apply tidak mengubah data dan keputusan yang sama tidak digandakan', function (string $module) {
    $f = reconciliationFixture($module);
    $plan = reconciliationPlan($f, 'konfirmasi_tahap', ['stage_id' => $f['stage']->id, 'nomor_putaran' => 0, 'aktif' => true]);
    $service = app(SuratRekonsiliasiService::class);
    $files = Storage::disk('local')->allFiles();
    expect($service->run($plan, $f['actor'])['applied'])->toBeFalse()
        ->and(app(WorkflowReadinessService::class)->fingerprint($module, $f['record']->id))->toBe($plan['expected_fingerprint'])
        ->and(Storage::disk('local')->allFiles())->toBe($files);
    $service->run($plan, $f['actor'], true);
    expect($f['record']->fresh()->tahap_aktif_id)->toBe($f['stage']->id)
        ->and($service->run($plan, $f['actor'], true)['already_applied'])->toBeTrue();
    expect($f['record']->rekonsiliasi()->where('kode', 'like', 'r7_%')->count())->toBe(1);
})->with(['snp', 'djsn']);

test('R7 identitas unit yang belum tercatat dilengkapi berdasarkan bukti tanpa mengubah isi dan waktu', function (string $module) {
    $f = reconciliationFixture($module);
    $db = DB::connection('mysql_'.$module);
    $sourceId = $db->table('tb_tanggapan')->insertGetId(['id_butir_'.$module => $f['butir']->{'id_butir_'.$module}, 'tahap_id' => $f['stage']->id,
        'putaran_id' => $f['stage']->putaran_asal_id, 'tanggapan' => 'Isi sumber lama', 'created_by' => $f['actor']->id, 'created_at' => '2026-10-01 10:00:00']);
    $before = (array) $db->table('tb_tanggapan')->find($sourceId);
    $plan = reconciliationPlan($f, 'konfirmasi_unit', ['stage_id' => $f['stage']->id, 'table' => 'tb_tanggapan', 'source_id' => $sourceId, 'unit_kerja_id' => 1]);
    app(SuratRekonsiliasiService::class)->run($plan, $f['actor'], true);
    expect((array) $db->table('tb_tanggapan')->find($sourceId))->toBe(array_replace($before, ['unit_kerja_id' => 1]));
    $change = reconciliationPlan($f, 'konfirmasi_unit', ['stage_id' => $f['stage']->id, 'table' => 'tb_tanggapan', 'source_id' => $sourceId, 'unit_kerja_id' => 1]);
    expect(fn () => app(SuratRekonsiliasiService::class)->run($change, $f['actor'], true))->toThrow(ValidationException::class);
})->with(['snp', 'djsn']);

test('R7 audit gagal membatalkan metadata dan salinan bukti yang baru', function (string $module) {
    $f = reconciliationFixture($module);
    $plan = reconciliationPlan($f, 'konfirmasi_tahap', ['stage_id' => $f['stage']->id, 'nomor_putaran' => 0, 'aktif' => true]);
    $files = Storage::disk('local')->allFiles();
    LogActivity::creating(fn () => throw new RuntimeException('Audit uji gagal'));
    expect(fn () => app(SuratRekonsiliasiService::class)->run($plan, $f['actor'], true))->toThrow(RuntimeException::class, 'Audit uji gagal');
    expect(app(WorkflowReadinessService::class)->fingerprint($module, $f['record']->id))->toBe($plan['expected_fingerprint'])
        ->and(Storage::disk('local')->allFiles())->toBe($files);
})->with(['snp', 'djsn']);

test('R7 konfirmasi unit tidak membuat duplikasi dengan sumber lain yang unitnya terbukti melalui PIC', function (string $module) {
    $f = reconciliationFixture($module);
    $db = DB::connection('mysql_'.$module);
    $pic = $db->table('tb_butir_pic')->where('id_butir_'.$module, $f['butir']->{'id_butir_'.$module})->where('unit_kerja_id', 1)->first();
    $sourceId = $db->table('tb_tanggapan')->insertGetId(['id_butir_'.$module => $f['butir']->{'id_butir_'.$module},
        'tahap_id' => $f['stage']->id, 'tanggapan' => 'Unit belum tercatat']);
    $db->table('tb_tindak_lanjut')->insert(['id_butir_'.$module => $f['butir']->{'id_butir_'.$module},
        'tahap_id' => $f['stage']->id, 'butir_pic_id' => $pic->id, 'tindak_lanjut' => 'Unit terbukti melalui PIC']);
    $plan = reconciliationPlan($f, 'konfirmasi_unit', ['stage_id' => $f['stage']->id, 'table' => 'tb_tanggapan', 'source_id' => $sourceId, 'unit_kerja_id' => 1]);
    expect(fn () => app(SuratRekonsiliasiService::class)->run($plan, $f['actor'], true))->toThrow(ValidationException::class)
        ->and(app(WorkflowReadinessService::class)->fingerprint($module, $f['record']->id))->toBe($plan['expected_fingerprint']);
})->with(['snp', 'djsn']);

test('R7 validasi tidak menghapus bukti tujuan yang sudah ada', function () {
    $f = reconciliationFixture('snp');
    $plan = reconciliationPlan($f, 'konfirmasi_tahap', ['stage_id' => $f['stage']->id, 'nomor_putaran' => 0, 'aktif' => true]);
    $operation = hash('sha256', json_encode([$plan, $f['actor']->id], JSON_THROW_ON_ERROR));
    $path = 'rekonsiliasi-r7/'.$operation.'.bin';
    Storage::disk('local')->put($path, 'Bukti lama jangan dihapus');
    expect(fn () => app(SuratRekonsiliasiService::class)->run($plan, $f['actor'], true))->toThrow(ValidationException::class)
        ->and(Storage::disk('local')->get($path))->toBe('Bukti lama jangan dihapus');
});

test('R7 tanggal berbukti melengkapi metadata tanpa menimpa tanggal surat atau waktu lama', function (string $module) {
    $f = reconciliationFixture($module);
    $raw = $f['record']->getRawOriginal();
    $plan = reconciliationPlan($f, 'konfirmasi_tanggal', ['stage_id' => $f['stage']->id, 'mulai_at' => '2026-11-01 09:30:00+07:00', 'ditetapkan_at' => '2026-11-01 09:30:00+07:00']);
    app(SuratRekonsiliasiService::class)->run($plan, $f['actor'], true);
    expect($f['stage']->fresh()->jatuh_tempo->toDateString())->toBe('2026-11-15')
        ->and($f['stage']->fresh()->mulai_at->format('Y-m-d H:i'))->toBe('2026-11-01 09:30')
        ->and($f['record']->fresh()->getRawOriginal())->toBe($raw)->and($f['stage']->riwayatTanggal()->count())->toBe(1);
    $again = reconciliationPlan($f, 'konfirmasi_tanggal', ['stage_id' => $f['stage']->id, 'mulai_at' => '2026-11-02', 'ditetapkan_at' => '2026-11-02']);
    expect(fn () => app(SuratRekonsiliasiService::class)->run($again, $f['actor'], true))->toThrow(ValidationException::class);
})->with(['snp', 'djsn']);

test('R7 rencana basi bukti berubah dan pengguna tanpa kewenangan ditolak', function (string $module) {
    $f = reconciliationFixture($module);
    $plan = reconciliationPlan($f, 'konfirmasi_tahap', ['stage_id' => $f['stage']->id, 'nomor_putaran' => 0, 'aktif' => true]);
    $service = app(SuratRekonsiliasiService::class);
    expect(fn () => $service->run($plan, workflowUser('admin_'.$module), true))->toThrow(HttpException::class);
    Storage::disk('local')->put('arsip/bukti.pdf', 'Bukti telah diganti');
    expect(fn () => $service->run($plan, $f['actor'], true))->toThrow(ValidationException::class);
    Storage::disk('local')->put('arsip/bukti.pdf', 'Arsip bukti yang diperiksa oleh Super Admin');
    DB::connection('mysql_'.$module)->table('tb_record')->where('id', $f['record']->id)->update(['perihal_surat' => 'Surat berubah']);
    expect(fn () => $service->run($plan, $f['actor'], true))->toThrow(ValidationException::class);
})->with(['snp', 'djsn']);

test('R7 arsip surat lama selesai tetap bukan salinan resmi dan tidak membuka siklus', function (string $module) {
    $f = reconciliationFixture($module);
    DB::connection('mysql_'.$module)->table('tb_record')->where('id', $f['record']->id)->update(['status' => 'tuntas']);
    $before = $f['record']->fresh()->getRawOriginal();
    $plan = reconciliationPlan($f, 'arsip_historis', []);
    app(SuratRekonsiliasiService::class)->run($plan, $f['actor'], true);
    expect($f['record']->fresh()->getRawOriginal())->toBe($before)
        ->and(DB::connection('mysql_'.$module)->table('tb_kompilasi_bahan')->count())->toBe(0)
        ->and(app(WorkflowReadinessService::class)->inspect([$module])['ready'])->toBeTrue();
})->with(['snp', 'djsn']);

test('R7 surat aktif tidak boleh dijadikan arsip untuk melewati pemeriksaan', function (string $module) {
    $f = reconciliationFixture($module);
    $plan = reconciliationPlan($f, 'arsip_historis', []);
    expect(fn () => app(SuratRekonsiliasiService::class)->run($plan, $f['actor'], true))->toThrow(ValidationException::class);
})->with(['snp', 'djsn']);

test('R7 arsip historis tidak dapat mengesahkan salinan resmi yang rusak', function (string $module) {
    $f = reconciliationFixture($module);
    $db = DB::connection('mysql_'.$module);
    $db->table('tb_record')->where('id', $f['record']->id)->update(['status' => 'tuntas']);
    $db->table('tb_kompilasi')->insert(['id_butir_'.$module => $f['butir']->{'id_butir_'.$module}, 'tahap_id' => $f['stage']->id,
        'hasil_kompilasi' => 'Kompilasi dengan salinan rusak', 'salinan_konteks' => '{}', 'status_bahan' => 'terverifikasi']);
    app(SuratRekonsiliasiService::class)->run(reconciliationPlan($f, 'arsip_historis', []), $f['actor'], true);
    $report = app(WorkflowReadinessService::class)->inspect([$module]);
    $issue = collect($report['modules'][$module]['issues'])->firstWhere('code', 'salinan_resmi_tidak_sah');
    expect($report['ready'])->toBeFalse()->and($issue['severity'])->toBe('blocker');
})->with(['snp', 'djsn']);

test('R7 salinan historis hanya dibentuk dari arsip eksplisit bukan versi sumber terkini', function (string $module) {
    $f = reconciliationFixture($module);
    $db = DB::connection('mysql_'.$module);
    $sourceId = $db->table('tb_tanggapan')->insertGetId(['id_butir_'.$module => $f['butir']->{'id_butir_'.$module}, 'tahap_id' => $f['stage']->id,
        'putaran_id' => $f['stage']->putaran_asal_id, 'unit_kerja_id' => 1, 'tanggapan' => 'ISI-ARSIP-TERBUKTI', 'created_by' => $f['actor']->id, 'created_at' => now(), 'updated_at' => now()]);
    $compilationId = $db->table('tb_kompilasi')->insertGetId(['id_butir_'.$module => $f['butir']->{'id_butir_'.$module}, 'tahap_id' => $f['stage']->id,
        'putaran_id' => $f['stage']->putaran_asal_id, 'status' => 'dalam_proses_reviu_dewas', 'hasil_kompilasi' => 'KOMPILASI-ARSIP', 'created_at' => now(), 'created_by' => $f['actor']->id]);
    $compilation = $f['butir']->kompilasis()->findOrFail($compilationId);
    $copies = [];
    $snapshot = app(KompilasiSalinanService::class)->capture($f['butir']->fresh(), $f['stage'], $compilation, $f['actor'], $f['butir']->tanggapanUnit()->get(), now(), $copies);
    $reviewId = $db->table('tb_review')->insertGetId(['id_butir_'.$module => $f['butir']->{'id_butir_'.$module}, 'tahap_id' => $f['stage']->id, 'putaran_id' => $f['stage']->putaran_asal_id, 'komite_id' => 1, 'status' => 'belum_ditanggapi']);
    $db->table('tb_tanggapan')->where('id', $sourceId)->update(['tanggapan' => 'SUMBER-TERKINI-BERBEDA']);
    $sourceBefore = $db->table('tb_tanggapan')->first();
    $rawBefore = $db->table('tb_kompilasi')->first();
    $plan = reconciliationPlan($f, 'salinan_historis', ['compilation_id' => $compilationId, 'context' => $snapshot['context'], 'materials' => $snapshot['materials'], 'review_ids' => [$reviewId]]);
    $service = app(SuratRekonsiliasiService::class);
    expect($service->run($plan, $f['actor'])['applied'])->toBeFalse()->and($db->table('tb_kompilasi_bahan')->count())->toBe(0);
    $service->run($plan, $f['actor'], true);
    expect($db->table('tb_tanggapan')->first())->toEqual($sourceBefore)
        ->and($compilation->fresh()->hasil_kompilasi)->toBe($rawBefore->hasil_kompilasi)
        ->and($compilation->fresh()->bahan->sole()->salinan['isi'])->toBe('ISI-ARSIP-TERBUKTI');
    config(['sidewas.alur_revisi_enabled' => true]);
    $preview = app(ReviuBahanPreviewService::class)->forReview($f['butir']->reviews()->findOrFail($reviewId), $f['actor']);
    expect($preview['responses'][0]['content'])->toBe('ISI-ARSIP-TERBUKTI')->and($preview['compilation']['content'])->toBe('KOMPILASI-ARSIP');
})->with(['snp', 'djsn']);
