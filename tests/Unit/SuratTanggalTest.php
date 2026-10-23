<?php

use App\Enums\TahapProses;
use App\Models\DjsnRecord;
use App\Models\LogActivity;
use App\Models\SnpRecord;
use App\Services\SuratAwalStatusService;
use App\Services\SuratTanggalService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Tests\TestCase;

uses(TestCase::class);

require_once __DIR__.'/WorkflowTestSetup.php';

beforeEach(function () {
    $this->withoutVite();
    setUpWorkflowTestDatabase();
    config(['sidewas.alur_revisi_enabled' => true, 'sidewas.reviu_putaran_enabled' => true]);
    $this->travelTo(Carbon::parse('2026-11-01 10:30:00', 'Asia/Jakarta'));
    Storage::fake('public');
    Mail::fake();
    Notification::fake();
});

function tanggalFixture(string $module, bool $publish = true): array
{
    $actor = workflowUser('super_admin');
    $users = [1 => workflowUser('pic_'.$module), 2 => workflowUser('pic_'.$module)];
    DB::table('tb_unit_kerja')->insert(['id' => 2, 'nama_unit' => 'Unit B', 'kode_unit' => 'B', 'direktorat_id' => 1, 'status' => 'active']);
    $users[2]->unitKerja()->sync([2 => ['status' => 'active']]);
    $db = DB::connection('mysql_'.$module);
    $db->table('tb_cluster')->insert(['id' => 1, 'nama_cluster' => 'Cluster tanggal', 'status' => 'active']);
    $db->table('tb_sub_cluster')->insert(['id' => 1, 'nama_sub_cluster' => 'Subcluster tanggal', 'cluster_id' => 1, 'status' => 'active']);
    $class = $module === 'snp' ? SnpRecord::class : DjsnRecord::class;
    $record = $class::create(['nomor_surat' => 'SURAT-TANGGAL', 'tanggal_surat' => '2025-01-01', 'perihal_surat' => 'Pemantauan tanggal',
        ...($module === 'snp' ? ['cluster_id' => 1, 'sub_cluster_id' => 1] : [])]);
    $f = compact('module', 'actor', 'record', 'users');
    if ($publish) {
        $f['butir'] = tanggalPublish($f);
    }

    return $f;
}

function tanggalPublish(array $f): Model
{
    test()->actingAs($f['actor'])->post(route($f['module'].'.perekaman.butir.store', $f['record']), [
        'butir_'.$f['module'] => 'Butir tersedia untuk unit', 'unit_kerja_utama_id' => 1, 'unit_kerja_pendukung_id' => [2], 'komite_id' => 1,
        'cluster_id' => 1, 'sub_cluster_id' => 1,
    ])->assertRedirect()->assertSessionHasNoErrors();

    return $f['record']->{'butir'.ucfirst($f['module'])}()->latest('id')->firstOrFail();
}

function tanggalInput(array $f, int $unit = 1): Model
{
    $stage = $f['record']->fresh()->tahapAktif;
    $followUp = $stage->tahap_proses === TahapProses::TindakLanjut;
    test()->actingAs($f['users'][$unit])->post($followUp ? route($f['module'].'.tindak-lanjut.store') : route($f['module'].'.tanggapan.store', $f['butir']), [
        'butir_id' => $f['butir']->id, 'tahap_id' => $stage->id, 'isi' => 'Isian unit '.$unit, 'deliverables' => 'Hasil unit',
        'butir_pic_id' => $f['butir']->butirPics()->where('unit_kerja_id', $unit)->firstOrFail()->id,
    ])->assertRedirect()->assertSessionHasNoErrors();

    return ($followUp ? $f['butir']->tindakLanjuts() : $f['butir']->tanggapanUnit())->where('tahap_id', $stage->id)->where('unit_kerja_id', $unit)->firstOrFail();
}

function tanggalCompile(array $f, array $extra = []): Model
{
    $stage = $f['record']->fresh()->tahapAktif;
    test()->actingAs($f['actor'])->post(route($f['module'].'.kompilasi.store', $f['butir']), array_merge([
        'tahap_id' => $stage->id, 'putaran_id' => $stage->putaran_asal_id,
        'tahap_kompilasi' => $stage->tahap_proses === TahapProses::TindakLanjut ? 'tindak_lanjut' : 'tanggapan', 'hasil_kompilasi' => 'Kompilasi sumber',
    ], $extra))->assertRedirect()->assertSessionHasNoErrors();

    return $f['butir']->reviews()->where('tahap_id', $stage->id)->firstOrFail();
}

function tanggalReview(array $f, Model $review, string $status = 'tindak_lanjut_belum_sesuai', array $extra = []): void
{
    test()->actingAs($f['actor'])->patch(route($f['module'].'.reviu.update', $review), array_merge([
        'status' => $status, 'hasil_review' => 'Keputusan reviewer',
        ...($status === 'tindak_lanjut_belum_sesuai' ? ['jatuh_tempo_tl' => app(SuratTanggalService::class)->suggestedDate()] : []),
    ], $extra))->assertRedirect()->assertSessionHasNoErrors();
}

test('R5 Draft tanpa butir tidak terlambat dan publikasi HTTP memulai satu batas kalender', function (string $module) {
    $f = tanggalFixture($module, false);
    $service = app(SuratAwalStatusService::class);
    $service->synchronizeModule($module);
    expect($f['record']->fresh()->status)->toBe('draft')->and($f['record']->jth_tempo)->toBeNull();
    $butir = tanggalPublish($f);
    $record = $f['record']->fresh();
    $stage = $record->tahapAktif;
    expect($record->status)->toBe('terbit')->and($stage->mulai_at->format('Y-m-d H:i'))->toBe('2026-11-01 10:30')
        ->and($stage->jatuh_tempo->toDateString())->toBe('2026-11-15')->and($stage->tanggal_ditetapkan_by)->toBe($f['actor']->id)
        ->and($stage->bukti_tanggal['butir_id'])->toBe($butir->id)->and($stage->riwayatTanggal()->count())->toBe(1);
    $first = $stage->getRawOriginal();
    $this->travel(2)->days();
    tanggalPublish($f);
    $record->update(['tanggal_surat' => '2030-12-31']);
    foreach ([$module.'.perekaman', $module.'.tanggapan.index', $module.'.kompilasi.index', $module.'.reviu.index'] as $route) {
        $this->get(route($route))->assertOk();
    }
    expect($stage->fresh()->getRawOriginal())->toBe($first)->and($stage->riwayatTanggal()->count())->toBe(1);
    Mail::assertNothingSent();
    Notification::assertNothingSent();
})->with(['snp', 'djsn']);

test('R5 batas awal berakhir 23.59 WIB dan respons pertama terlambat tetap menjadi Proses', function (string $module) {
    $f = tanggalFixture($module);
    $stage = $f['record']->fresh()->tahapAktif;
    $this->travelTo(Carbon::parse('2026-11-15 16:59:59', 'UTC'));
    app(SuratAwalStatusService::class)->synchronizeModule($module);
    expect($f['record']->fresh()->status)->toBe('terbit');
    $this->travelTo(Carbon::parse('2026-11-15 17:00:00', 'UTC'));
    $this->actingAs($f['actor'])->get(route($module.'.perekaman', ['status' => 'belum_ditindaklanjuti']))->assertOk()
        ->assertViewHas('statistik', fn ($stats) => $stats['belum_ditindaklanjuti'] === 1);
    expect($f['record']->fresh()->status)->toBe('belum_ditindaklanjuti');
    $source = tanggalInput($f);
    expect($f['record']->fresh()->status)->toBe('proses')->and($source->submitted_late_at)->not->toBeNull()
        ->and($source->jatuh_tempo_saat_pengisian->toDateString())->toBe('2026-11-15')
        ->and($stage->fresh()->jatuh_tempo->toDateString())->toBe('2026-11-15');
    $this->actingAs($f['actor'])->get(route($module.'.perekaman', ['status' => 'proses']))->assertOk()
        ->assertViewHas('statistik', fn ($stats) => $stats['proses'] === 1 && $stats['belum_ditindaklanjuti'] === 0);
    $this->get(route($module.'.tanggapan.index'))->assertOk()->assertSee('Terlambat saat pengisian')->assertSee('15/11/2026 23.59 WIB');
})->with(['snp', 'djsn']);

test('R5 butir tanpa penugasan dan butir Draft belum memulai tanggal sampai tersedia', function (string $module) {
    $f = tanggalFixture($module, false);
    $butirClass = '\\App\\Models\\'.ucfirst($module).'Butir';
    $picClass = '\\App\\Models\\'.ucfirst($module).'ButirPic';
    $butir = $butirClass::create(['id_'.$module => $f['record']->{'id_'.$module}, 'butir_'.$module => 'Belum diterbitkan', 'status' => 'draft']);
    expect($f['record']->tahapanProses()->firstOrFail()->mulai_at)->toBeNull();
    $picClass::create(['id_butir_'.$module => $butir->{'id_butir_'.$module}, 'jenis_pic' => 'utama', 'unit_kerja_id' => 1]);
    expect($f['record']->tahapanProses()->firstOrFail()->mulai_at)->toBeNull();
    $this->travel(3)->days();
    $butir->update(['status' => 'terbit']);
    expect($f['record']->fresh()->tahapAktif->jatuh_tempo->toDateString())->toBe('2026-11-18');
})->with(['snp', 'djsn']);

test('R5 data lama tidak memperoleh tanggal publikasi perkiraan dan tenggat historis dipertahankan', function (string $module) {
    config(['sidewas.alur_revisi_enabled' => false]);
    $f = tanggalFixture($module);
    $legacyDate = $f['record']->fresh()->jth_tempo->toDateString();
    config(['sidewas.alur_revisi_enabled' => true]);
    $this->actingAs($f['actor'])->get(route($module.'.perekaman'))->assertOk()->assertSee('Tanggal belum terverifikasi');
    tanggalPublish($f);
    $this->get(route($module.'.tanggapan.index'))->assertOk();
    expect($f['record']->fresh()->status)->toBe('terbit')->and($f['record']->fresh()->jth_tempo->toDateString())->toBe($legacyDate)
        ->and($f['record']->tahapanProses()->whereNotNull('tanggal_ditetapkan_at')->count())->toBe(0);
})->with(['snp', 'djsn']);

test('R5 keputusan Belum Sesuai menolak tanggal hilang tidak valid dan alasan kustom kosong', function (string $module, array $extra, string $error) {
    $f = tanggalFixture($module);
    $review = tanggalCompile($f);
    $this->actingAs($f['actor'])->patchJson(route($module.'.reviu.update', $review), array_merge([
        'status' => 'tindak_lanjut_belum_sesuai', 'hasil_review' => 'Perbaiki',
    ], $extra))->assertUnprocessable()->assertJsonValidationErrors($error);
    expect($f['record']->tahapanProses()->count())->toBe(1)->and($review->fresh()->status)->toBe('belum_ditanggapi');
})->with(['snp', 'djsn'])->with([
    [[], 'jatuh_tempo_tl'],
    [['jatuh_tempo_tl' => '2026-11-01'], 'jatuh_tempo_tl'],
    [['jatuh_tempo_tl' => '2026-10-31'], 'jatuh_tempo_tl'],
    [['jatuh_tempo_tl' => '2026-11-20'], 'alasan_jatuh_tempo'],
    [['jatuh_tempo_tl' => 'tanggal salah'], 'jatuh_tempo_tl'],
]);

test('R5 dua putaran memiliki tanggal terpisah dan keterlambatan tidak mengubah status resmi', function (string $module) {
    $f = tanggalFixture($module);
    $initial = $f['record']->fresh()->tahapAktif;
    $review = tanggalCompile($f);
    tanggalReview($f, $review, extra: ['jatuh_tempo_tl' => '2026-11-03', 'alasan_jatuh_tempo' => 'Diperlukan lebih cepat']);
    $first = $f['record']->fresh()->tahapAktif;
    $this->travelTo(Carbon::parse('2026-11-03 23:59:59', 'Asia/Jakarta'));
    expect(app(SuratTanggalService::class)->lateRecordIds($module))->toBe([]);
    $this->travelTo(Carbon::parse('2026-11-04 00:00:00', 'Asia/Jakarta'));
    expect(app(SuratTanggalService::class)->lateRecordIds($module))->toBe([$f['record']->id]);
    $this->actingAs($f['actor'])->get(route($module.'.perekaman', ['keterlambatan' => 'terlambat']))->assertOk()
        ->assertViewHas('records', fn ($records) => $records->total() === 1)
        ->assertViewHas('statistik', fn ($stats) => $stats['terlambat_tindak_lanjut'] === 1 && $stats['proses_tindak_lanjut_belum_sesuai'] === 1)
        ->assertSee('03/11/2026 23.59 WIB');
    $this->get(route($module.'.perekaman', ['keterlambatan' => 'tidak_terlambat']))->assertViewHas('records', fn ($records) => $records->total() === 0);
    tanggalInput($f);
    $secondReview = tanggalCompile($f);
    tanggalReview($f, $secondReview, extra: ['jatuh_tempo_tl' => '2026-11-25', 'alasan_jatuh_tempo' => 'Waktu perbaikan berikutnya']);
    $second = $f['record']->fresh()->tahapAktif;
    expect($first->fresh()->jatuh_tempo->toDateString())->toBe('2026-11-03')->and($first->fresh()->archived_at)->not->toBeNull()
        ->and($second->nomor_putaran)->toBe(2)->and($second->jatuh_tempo->toDateString())->toBe('2026-11-25')
        ->and($initial->fresh()->jatuh_tempo->toDateString())->toBe('2026-11-15')
        ->and($f['record']->fresh()->status)->toBe('proses_tindak_lanjut_belum_sesuai');
    expect(fn () => $second->update(['jatuh_tempo' => '2030-01-01']))->toThrow(LogicException::class);
    $this->actingAs($f['actor'])->get(route($module.'.reviu.index'))->assertOk()->assertSee('25/11/2026 23.59 WIB');
})->with(['snp', 'djsn']);

test('R5 pengajuan Pending Ditolak dan Disetujui hanya memengaruhi putaran terkait dengan audit', function (string $module, string $decision) {
    $f = tanggalFixture($module);
    tanggalReview($f, tanggalCompile($f));
    $stage = $f['record']->fresh()->tahapAktif;
    tanggalInput($f);
    $review = tanggalCompile($f, ['ubah_tgl' => '2026-11-30', 'alasan_pengajuan_tgl' => 'Memerlukan bahan tambahan']);
    $snapshot = $review->kompilasiPutaran->salinan_konteks;
    $this->travel(1)->days();
    tanggalReview($f, $review, 'belum_ditanggapi', ['status_pengajuan_tgl' => $decision, 'alasan_keputusan_tgl' => 'Keputusan berdasarkan kebutuhan unit']);
    expect($stage->fresh()->jatuh_tempo->toDateString())->toBe($decision === 'disetujui' ? '2026-11-30' : '2026-11-15')
        ->and($review->kompilasiPutaran->fresh()->salinan_konteks)->toBe($snapshot)->and($f['record']->fresh()->jth_tempo)->toBeNull()
        ->and($stage->riwayatTanggal()->count())->toBe($decision === 'pending' ? 1 : 2);
    if ($decision !== 'pending') {
        $history = $stage->riwayatTanggal()->get()->last();
        expect($history->tanggal_lama->toDateString())->toBe('2026-11-15')->and($history->tanggal_baru->toDateString())->toBe('2026-11-30')
            ->and($history->pengguna_id)->toBe($f['actor']->id)->and($history->alasan)->toBe('Keputusan berdasarkan kebutuhan unit');
        expect(fn () => $history->update(['alasan' => 'Timpa']))->toThrow(LogicException::class);
        $this->actingAs($f['actor'])->patchJson(route($module.'.reviu.update', $review), ['status' => 'belum_ditanggapi', 'hasil_review' => 'Ubah keputusan tanggal',
            'status_pengajuan_tgl' => $decision === 'disetujui' ? 'ditolak' : 'disetujui', 'alasan_keputusan_tgl' => 'Tidak boleh'])->assertUnprocessable();
    }
})->with(['snp', 'djsn'])->with(['pending', 'ditolak', 'disetujui']);

test('R5 batas awal tidak dapat diusulkan ulang dan pengajuan TL memerlukan alasan', function (string $module) {
    $f = tanggalFixture($module);
    $this->actingAs($f['actor'])->postJson(route($module.'.kompilasi.store', $f['butir']), [
        'tahap_kompilasi' => 'tanggapan', 'hasil_kompilasi' => 'Kompilasi', 'ubah_tgl' => '2026-12-01',
    ])->assertUnprocessable()->assertJsonValidationErrors('ubah_tgl');
    tanggalReview($f, tanggalCompile($f));
    $stage = $f['record']->fresh()->tahapAktif;
    $this->postJson(route($module.'.kompilasi.store', $f['butir']), [
        'tahap_id' => $stage->id, 'putaran_id' => $stage->putaran_asal_id, 'tahap_kompilasi' => 'tindak_lanjut', 'hasil_kompilasi' => 'Kompilasi', 'ubah_tgl' => '2026-12-01',
    ])->assertUnprocessable()->assertJsonValidationErrors('alasan_pengajuan_tgl');
})->with(['snp', 'djsn']);

test('R5 kedua Close tidak meminta tanggal baru dan kelengkapan susulan tidak mengganti tanggal', function (string $module, string $decision) {
    $f = tanggalFixture($module);
    tanggalReview($f, tanggalCompile($f));
    $stage = $f['record']->fresh()->tahapAktif;
    $review = tanggalCompile($f);
    $before = $stage->getRawOriginal();
    tanggalReview($f, $review, $decision);
    $this->travel(30)->days();
    $source = tanggalInput($f, 2);
    expect($source->submitted_late_at)->not->toBeNull()->and($source->submitted_after_close_at)->not->toBeNull()
        ->and($stage->fresh()->getRawOriginal())->toBe($before)->and($f['record']->fresh()->status)->toBe($decision)
        ->and(app(SuratTanggalService::class)->lateRecordIds($module))->toBe([]);
})->with(['snp', 'djsn'])->with(['tindak_lanjut_sesuai', 'tidak_dapat_ditindaklanjuti']);

test('R5 audit gagal membatalkan tanggal putaran dan keputusan', function (string $module) {
    $f = tanggalFixture($module);
    $review = tanggalCompile($f);
    LogActivity::creating(function ($entry): void {
        if ($entry->action === 'buka_tahap_tindak_lanjut') {
            throw new RuntimeException('Audit sengaja gagal');
        }
    });
    try {
        $this->actingAs($f['actor'])->patchJson(route($module.'.reviu.update', $review), ['status' => 'tindak_lanjut_belum_sesuai',
            'hasil_review' => 'Gagal', 'jatuh_tempo_tl' => '2026-11-20', 'alasan_jatuh_tempo' => 'Alasan tanggal'])->assertStatus(500);
        expect($f['record']->tahapanProses()->count())->toBe(1)->and($review->fresh()->status)->toBe('belum_ditanggapi')
            ->and($review->getConnection()->table('tb_surat_tanggal_riwayat')->count())->toBe(1);
    } finally {
        LogActivity::flushEventListeners();
    }
})->with(['snp', 'djsn']);

test('R5 unit lengkap tepat waktu tidak ditandai terlambat dan statistik utama memakai tanggal aktif', function (string $module) {
    $f = tanggalFixture($module);
    tanggalReview($f, tanggalCompile($f), extra: ['jatuh_tempo_tl' => '2026-11-02', 'alasan_jatuh_tempo' => 'Pemeriksaan segera']);
    tanggalInput($f, 1);
    tanggalInput($f, 2);
    $this->travel(3)->days();
    expect(app(SuratTanggalService::class)->lateRecordIds($module))->toBe([]);
    $this->actingAs($f['actor'])->get(route('dashboard', ['jenis_rapat' => $module]))->assertOk()
        ->assertViewHas('moduleStats', fn ($stats) => $stats[$module]['jatuh_tempo'] === 0);
})->with(['snp', 'djsn']);

test('R5 persetujuan tanggal butuh alasan dan tidak boleh mengesahkan tanggal yang sudah lewat', function (string $module) {
    $f = tanggalFixture($module);
    tanggalReview($f, tanggalCompile($f));
    $review = tanggalCompile($f, ['ubah_tgl' => '2026-11-03', 'alasan_pengajuan_tgl' => 'Mempercepat penyelesaian']);
    $this->actingAs($f['actor'])->patchJson(route($module.'.reviu.update', $review), [
        'status' => 'belum_ditanggapi', 'hasil_review' => 'Ditinjau', 'status_pengajuan_tgl' => 'disetujui',
    ])->assertUnprocessable()->assertJsonValidationErrors('alasan_keputusan_tgl');
    $this->travel(3)->days();
    $this->patchJson(route($module.'.reviu.update', $review), [
        'status' => 'belum_ditanggapi', 'hasil_review' => 'Ditinjau', 'status_pengajuan_tgl' => 'disetujui', 'alasan_keputusan_tgl' => 'Tanggal lewat',
    ])->assertUnprocessable()->assertJsonValidationErrors('status_pengajuan_tgl');
    tanggalReview($f, $review, 'belum_ditanggapi', ['status_pengajuan_tgl' => 'ditolak', 'alasan_keputusan_tgl' => 'Tanggal yang diajukan telah lewat']);
    expect($f['record']->fresh()->tahapAktif->jatuh_tempo->toDateString())->toBe('2026-11-15');
})->with(['snp', 'djsn']);

test('R5 persetujuan putaran lama dan penetapan putaran berikutnya disimpan terpisah', function (string $module) {
    $f = tanggalFixture($module);
    tanggalReview($f, tanggalCompile($f));
    $first = $f['record']->fresh()->tahapAktif;
    $review = tanggalCompile($f, ['ubah_tgl' => '2026-11-30', 'alasan_pengajuan_tgl' => 'Tambah waktu pemeriksaan']);
    tanggalReview($f, $review, extra: ['status_pengajuan_tgl' => 'disetujui', 'alasan_keputusan_tgl' => 'Pengajuan sah']);
    expect($first->fresh()->jatuh_tempo->toDateString())->toBe('2026-11-30')
        ->and($f['record']->fresh()->tahapAktif->jatuh_tempo->toDateString())->toBe('2026-11-15')
        ->and($first->riwayatTanggal()->count())->toBe(2);
})->with(['snp', 'djsn']);

test('R5 penanda keterlambatan menyimpan tanggal yang berlaku saat unit mengisi', function (string $module, bool $approvalFirst) {
    $f = tanggalFixture($module);
    tanggalReview($f, tanggalCompile($f), extra: ['jatuh_tempo_tl' => '2026-11-02', 'alasan_jatuh_tempo' => 'Kebutuhan segera']);
    $this->travel(2)->days();
    $review = tanggalCompile($f, ['ubah_tgl' => '2026-11-30', 'alasan_pengajuan_tgl' => 'Tambahan waktu']);
    $approve = fn () => tanggalReview($f, $review, 'belum_ditanggapi', ['status_pengajuan_tgl' => 'disetujui', 'alasan_keputusan_tgl' => 'Kebutuhan tambahan disetujui']);
    if ($approvalFirst) {
        $approve();
    }
    $source = tanggalInput($f, 2);
    if (! $approvalFirst) {
        $approve();
    }
    expect($source->fresh()->submitted_late_at !== null)->toBe(! $approvalFirst)
        ->and($source->fresh()->jatuh_tempo_saat_pengisian->toDateString())->toBe($approvalFirst ? '2026-11-30' : '2026-11-02')
        ->and($review->kompilasiPutaran->salinan_konteks['tahap']['jatuh_tempo'])->toBe('2026-11-02');
    expect(fn () => $source->fresh()->forceFill(['submitted_late_at' => now(), 'jatuh_tempo_saat_pengisian' => '2030-01-01'])->save())->toThrow(LogicException::class);
})->with(['snp', 'djsn'])->with([true, false]);

test('R5 role pembaca tidak dapat menetapkan tanggal putaran atau menyetujui pengajuan', function (string $module) {
    $f = tanggalFixture($module);
    $review = tanggalCompile($f);
    $this->actingAs(workflowUser('viewer_'.$module))->patchJson(route($module.'.reviu.update', $review), [
        'status' => 'tindak_lanjut_belum_sesuai', 'hasil_review' => 'Tidak berhak', 'jatuh_tempo_tl' => '2026-11-15',
    ])->assertForbidden();
    expect($f['record']->tahapanProses()->count())->toBe(1);
    tanggalReview($f, $review);
    $next = tanggalCompile($f, ['ubah_tgl' => '2026-11-30', 'alasan_pengajuan_tgl' => 'Usulan unit']);
    $this->actingAs(workflowUser('admin_'.$module))->patchJson(route($module.'.reviu.update', $next), [
        'status' => 'belum_ditanggapi', 'hasil_review' => 'Tidak berhak', 'status_pengajuan_tgl' => 'disetujui', 'alasan_keputusan_tgl' => 'Tidak berhak',
    ])->assertForbidden();
})->with(['snp', 'djsn']);

test('R5 status akhir historis tidak dihitung terlambat meskipun metadata Close belum tersedia', function (string $module, string $status) {
    $f = tanggalFixture($module);
    tanggalReview($f, tanggalCompile($f), extra: ['jatuh_tempo_tl' => '2026-11-02', 'alasan_jatuh_tempo' => 'Batas segera']);
    $f['record']->getConnection()->table('tb_record')->where('id', $f['record']->id)->update(['status' => $status, 'closed_at' => null]);
    $this->travel(3)->days();
    expect(app(SuratTanggalService::class)->lateRecordIds($module))->toBe([])
        ->and(app(SuratTanggalService::class)->presentation($f['record']->fresh())['late'])->toBeFalse();
})->with(['snp', 'djsn'])->with(['tindak_lanjut_sesuai', 'tidak_dapat_ditindaklanjuti', 'tuntas']);

test('R5 migrasi berulang mempertahankan tanggal lama dan rollback menolak bukti tanggal', function (string $module) {
    config(['sidewas.alur_revisi_enabled' => false]);
    $f = tanggalFixture($module);
    $before = $f['record']->fresh()->getRawOriginal();
    $migration = require database_path('migrations/2026_10_23_143129_add_snp_djsn_stage_deadline_audit.php');
    $migration->up();
    $migration->up();
    expect($f['record']->fresh()->getRawOriginal())->toBe($before);
    $migration->down();
    $migration->up();
    config(['sidewas.alur_revisi_enabled' => true]);
    $class = $module === 'snp' ? SnpRecord::class : DjsnRecord::class;
    $f['record'] = $class::create(['nomor_surat' => 'SURAT-BARU', 'tanggal_surat' => '2026-11-01', 'cluster_id' => 1, 'sub_cluster_id' => 1]);
    tanggalPublish($f);
    expect(fn () => $migration->down())->toThrow(RuntimeException::class, 'Rollback R5 dibatalkan');
})->with(['snp', 'djsn']);

test('R5 browser membedakan tanggal awal tanggal berikutnya dan keputusan usulan', function (string $module) {
    $f = tanggalFixture($module);
    $pages = [['kind' => 'initial-compile', 'html' => $this->actingAs($f['actor'])->get(route($module.'.kompilasi.index'))->assertOk()->getContent()]];
    $review = tanggalCompile($f);
    $pages[] = ['kind' => 'open-review', 'html' => $this->get(route($module.'.reviu.index'))->assertOk()->getContent()];
    tanggalReview($f, $review);
    $stage = $f['record']->fresh()->tahapAktif;
    $pages[] = ['kind' => 'follow-compile', 'stageId' => $stage->id, 'html' => $this->get(route($module.'.kompilasi.index'))->assertOk()->getContent()];
    $nextReview = tanggalCompile($f, ['ubah_tgl' => '2026-11-30', 'alasan_pengajuan_tgl' => 'Bahan tambahan diperlukan']);
    $pages[] = ['kind' => 'proposal-review', 'html' => $this->get(route($module.'.reviu.index'))->assertOk()->getContent()];
    tanggalReview($f, $nextReview, 'belum_ditanggapi', ['status_pengajuan_tgl' => 'disetujui', 'alasan_keputusan_tgl' => 'Bukti mendukung perpanjangan']);
    $pages[] = ['kind' => 'detail', 'html' => $this->get(route($module.'.tindak-lanjut.index'))->assertOk()->getContent()];
    $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
    $process = new Process(['node', base_path('tests/Unit/SuratTanggal.browser.mjs')], base_path());
    $process->setInput(json_encode(['pages' => $pages, 'css' => $manifest['resources/css/app.css']['file'], 'js' => $manifest['resources/js/app.js']['file'],
        'screenshot' => base_path('tmp/tanggal-r5-'.$module.'.png')]));
    $process->setTimeout(90)->run();
    expect($process->isSuccessful())->toBeTrue($process->getOutput().$process->getErrorOutput());
})->with(['snp', 'djsn']);
