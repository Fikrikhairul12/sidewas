<?php

use App\Enums\TahapProses;
use App\Models\User;
use App\Services\ReviuBahanPreviewService;
use App\Services\SuratAwalStatusService;
use App\Services\SuratTanggalService;
use App\Services\WorkflowReadinessService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class);
require_once __DIR__.'/WorkflowTestSetup.php';

beforeEach(function () {
    $this->withoutVite();
    setUpWorkflowTestDatabase();
    config(['sidewas.alur_revisi_enabled' => true, 'sidewas.reviu_putaran_enabled' => true]);
    Storage::fake('public');
    $this->travelTo(Carbon::parse('2026-11-01 09:00:00', 'Asia/Jakarta'));
});

function revisionJourneyFixture(string $module): array
{
    $actor = workflowUser('super_admin');
    $writers = [1 => workflowUser('pic_'.$module), 2 => workflowUser('pic_'.$module)];
    $sameUnit = workflowUser('pic_'.$module);
    DB::table('tb_unit_kerja')->insert(['id' => 2, 'direktorat_id' => 1, 'nama_unit' => 'Unit B', 'kode_unit' => 'B', 'status' => 'active']);
    $writers[2]->unitKerja()->sync([2 => ['status' => 'active']]);
    $butir = workflowButir($module);
    $butir->butirPics()->where('jenis_pic', 'utama')->firstOrFail()->touch();
    $butir->butirPics()->create(['jenis_pic' => 'pendukung', 'unit_kerja_id' => 2]);

    return compact('module', 'actor', 'writers', 'sameUnit', 'butir');
}

function revisionJourneyInput(array $f, int $unit, string $body, ?User $writer = null, bool $duplicate = false): ?Model
{
    $stage = $f['butir']->record->fresh()->tahapAktif;
    $followUp = $stage->tahap_proses === TahapProses::TindakLanjut;
    $response = test()->actingAs($writer ?? $f['writers'][$unit])->postJson($followUp ? route($f['module'].'.tindak-lanjut.store') : route($f['module'].'.tanggapan.store', $f['butir']), [
        'butir_id' => $f['butir']->id, 'tahap_id' => $stage->id, 'putaran_id' => $stage->putaran_asal_id,
        'butir_pic_id' => $f['butir']->butirPics()->where('unit_kerja_id', $unit)->firstOrFail()->id,
        'isi' => $body, 'deliverables' => 'Hasil unit '.$unit,
    ]);
    if ($duplicate) {
        $response->assertUnprocessable();

        return null;
    }
    $response->assertRedirect()->assertSessionHasNoErrors();

    return ($followUp ? $f['butir']->tindakLanjuts() : $f['butir']->tanggapanUnit())->where('tahap_id', $stage->id)->where('unit_kerja_id', $unit)->firstOrFail();
}

function revisionJourneyCompile(array $f, int $number, array $extra = []): Model
{
    $stage = $f['butir']->record->fresh()->tahapAktif;
    test()->actingAs($f['actor'])->post(route($f['module'].'.kompilasi.store', $f['butir']), $extra + [
        'tahap_id' => $stage->id, 'putaran_id' => $stage->putaran_asal_id,
        'tahap_kompilasi' => $stage->tahap_proses === TahapProses::TanggapanAwal ? 'tanggapan' : 'tindak_lanjut',
        'hasil_kompilasi' => 'KOMPILASI-RESMI-'.$number,
    ])->assertRedirect()->assertSessionHasNoErrors();

    return $f['butir']->reviews()->latest('id')->firstOrFail();
}

function revisionJourneyReview(array $f, Model $review, string $status, array $extra = []): void
{
    test()->actingAs($f['actor'])->patch(route($f['module'].'.reviu.update', $review), $extra + [
        'hasil_review' => 'REVIU-RESMI-'.$review->tahap_id, 'status' => $status,
        ...($status === 'tindak_lanjut_belum_sesuai' ? ['jatuh_tempo_tl' => app(SuratTanggalService::class)->suggestedDate()] : []),
    ])->assertRedirect()->assertSessionHasNoErrors();
}

test('R7 perjalanan utuh dari publikasi dua looping sampai Close konsisten pada akses tanggal statistik preview PDF', function (string $module, string $decision) {
    $f = revisionJourneyFixture($module);
    $record = $f['butir']->record->fresh();
    $initial = $record->tahapAktif;
    expect($initial->mulai_at->format('Y-m-d H:i'))->toBe('2026-11-01 09:00')->and($initial->jatuh_tempo->toDateString())->toBe('2026-11-15')->and($record->status)->toBe('terbit');
    $this->travelTo(Carbon::parse('2026-11-15 23:59:59', 'Asia/Jakarta'));
    app(SuratAwalStatusService::class)->synchronizeModule($module);
    expect($record->fresh()->status)->toBe('terbit');
    $this->travelTo(Carbon::parse('2026-11-16 00:00:00', 'Asia/Jakarta'));
    $this->actingAs($f['actor'])->get(route($module.'.perekaman', ['status' => 'belum_ditindaklanjuti']))->assertOk()->assertSee('Belum Ditindaklanjuti');
    expect($record->fresh()->status)->toBe('belum_ditindaklanjuti');
    $first = revisionJourneyInput($f, 1, 'BAHAN-RESMI-0');
    revisionJourneyInput($f, 1, 'Ganda dari akun seunit', $f['sameUnit'], true);
    expect($record->fresh()->status)->toBe('proses');
    $page = $this->actingAs($f['writers'][1])->get(route($module.'.tanggapan.index', ['keyword' => $f['butir']->{'id_butir_'.$module}]))->assertOk();
    $page->assertDontSee('data-beri-isian', false)->assertSee('data-isian-detail', false);
    $review = revisionJourneyCompile($f, 0);
    $this->actingAs($f['writers'][1])->patch(route($module.'.tanggapan.update', $first), ['isi' => 'EDIT-TIDAK-RESMI', 'deliverables' => 'Edit sumber'])->assertSessionHasNoErrors();
    revisionJourneyInput($f, 2, 'SUSULAN-SESUDAH-KOMPILASI-0');
    $preview = app(ReviuBahanPreviewService::class)->forReview($review->fresh(), $f['actor']);
    expect(array_column($preview['responses'], 'content'))->toBe(['BAHAN-RESMI-0'])->and($preview['missing_units'])->toBe(['Unit B']);
    $viewer = workflowUser('viewer_'.$module);
    $this->actingAs($viewer)->patchJson(route($module.'.reviu.update', $review), ['hasil_review' => 'Tanpa kewenangan', 'status' => $decision])->assertForbidden();
    revisionJourneyReview($f, $review, 'tindak_lanjut_belum_sesuai');
    foreach ([1, 2] as $number) {
        $stage = $record->fresh()->tahapAktif;
        expect($stage->tahap_proses)->toBe(TahapProses::TindakLanjut)->and($stage->nomor_putaran)->toBe($number)
            ->and($record->fresh()->status)->toBe('proses_tindak_lanjut_belum_sesuai');
        $this->actingAs($f['actor'])->get(route($module.'.tindak-lanjut.index'))->assertOk()->assertSee('Putaran '.$number);
        $this->actingAs($f['writers'][1])->postJson(route($module.'.tanggapan.store', $f['butir']), ['butir_pic_id' => $f['butir']->butirPics()->where('unit_kerja_id', 1)->first()->id, 'isi' => 'Tidak membuka Tanggapan lagi'])->assertUnprocessable();
        revisionJourneyInput($f, 1, 'BAHAN-RESMI-'.$number);
        revisionJourneyInput($f, 1, 'Ganda perbaikan', $f['sameUnit'], true);
        $proposal = $number === 1 ? ['ubah_tgl' => '2026-12-20', 'alasan_pengajuan_tgl' => 'Tambahan waktu berdasarkan kompilasi'] : [];
        $review = revisionJourneyCompile($f, $number, $proposal);
        if ($number === 1) {
            $oldDeadline = $stage->jatuh_tempo->toDateString();
            $approval = $decision === 'tindak_lanjut_sesuai';
            revisionJourneyReview($f, $review, 'belum_ditanggapi', ['status_pengajuan_tgl' => $approval ? 'disetujui' : 'ditolak', 'alasan_keputusan_tgl' => 'Keputusan tanggal berdasarkan pemeriksaan reviewer']);
            expect($stage->fresh()->jatuh_tempo->toDateString())->toBe($approval ? '2026-12-20' : $oldDeadline);
            revisionJourneyInput($f, 2, 'SUSULAN-SESUDAH-KOMPILASI-1');
            revisionJourneyReview($f, $review->fresh(), 'tindak_lanjut_belum_sesuai');
        } else {
            revisionJourneyReview($f, $review, $decision);
        }
    }
    $closed = $record->fresh()->getRawOriginal();
    $late = revisionJourneyInput($f, 2, 'SUSULAN-SETELAH-CLOSE');
    expect($late->submitted_after_close_at)->not->toBeNull()->and($late->submitted_after_compilation_at)->not->toBeNull()
        ->and($record->fresh()->getRawOriginal())->toBe($closed)->and($record->tahapanProses()->count())->toBe(3)
        ->and($initial->fresh()->mulai_at->format('Y-m-d H:i'))->toBe('2026-11-01 09:00');
    $this->actingAs($f['writers'][1])->patchJson(route($module.'.tanggapan.update', $first), ['isi' => 'Ubah riwayat'])->assertUnprocessable();
    foreach (['cetak', 'cetak-custom'] as $kind) {
        $payload = ['record_ids' => [$record->id], 'butir_ids' => [$f['butir']->id],
            'fields' => $module === 'snp' ? ['tanggapan_unit', 'tindak_lanjut_unit', 'kompilasi_tanggapan', 'kompilasi_tindak_lanjut', 'hasil_reviu', 'status'] : ['tanggapan_tl', 'hasil_reviu', 'status']];
        $html = $this->actingAs($f['actor'])->post(route($module.'.report.'.$kind), $payload)->assertOk()->viewData('reportHtml');
        expect($html)->toContain('BAHAN-RESMI-0', 'BAHAN-RESMI-1', 'BAHAN-RESMI-2', 'KOMPILASI-RESMI-0', 'KOMPILASI-RESMI-2')
            ->not->toContain('SUSULAN-SETELAH-CLOSE', 'SUSULAN-SESUDAH-KOMPILASI', 'EDIT-TIDAK-RESMI');
    }
    $stats = app(SuratAwalStatusService::class)->statistics($module);
    expect($stats[$decision])->toBe(1)->and($stats['total'])->toBe(1)->and($stats['terlambat_tindak_lanjut'])->toBe(0);
    $this->actingAs($f['actor'])->get(route($module.'.reviu.index'))->assertOk()->assertSee('Tindak Lanjut Putaran 2');
    expect(app(WorkflowReadinessService::class)->inspect([$module])['ready'])->toBeTrue();
})->with(['snp', 'djsn'])->with(['tindak_lanjut_sesuai', 'tidak_dapat_ditindaklanjuti']);
