<?php

use App\Enums\TahapProses;
use App\Models\DjsnKompilasi;
use App\Models\DjsnRecord;
use App\Models\DjsnTanggapan;
use App\Models\KompilasiBahan;
use App\Models\SnpKompilasi;
use App\Models\SnpRecord;
use App\Models\SnpTanggapan;
use App\Models\SuratRekonsiliasi;
use App\Models\SuratTahap;
use App\Services\SuratPutaranService;
use App\Services\SuratTahapBackfill;
use Database\Seeders\SuratTahapSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

require_once __DIR__.'/WorkflowTestSetup.php';

beforeEach(function () {
    setUpWorkflowTestDatabase(withProcessStages: false);
    $this->migration = require database_path('migrations/2026_10_23_094342_add_snp_djsn_process_stage_foundation.php');
});

/** @return array{record: Model, butir: Model, rounds: array<int, int>, sources: array<int, array<string, int>>} */
function stageFoundationFixture(string $module, int $loopCount = 2): array
{
    $butir = workflowButir($module);
    $record = $butir->record;
    $service = new SuratPutaranService;
    $db = DB::connection('mysql_'.$module);
    $legacy = $service->legacyFor('mysql_'.$module, $record->getRawOriginal());
    $legacy->forceFill(['penugasan_unit' => [$butir->{'id_butir_'.$module} => [['id' => 1, 'name' => 'Unit Uji', 'role' => 'utama']]], 'penugasan_dicatat_at' => now()])->save();
    $rounds = [];
    $sources = [];
    for ($number = 0; $number <= $loopCount; $number++) {
        $round = $number === 0 ? $legacy : $service->buka($record, 71);
        $rounds[$number] = $round->id;
        $audit = ['id_butir_'.$module => $butir->{'id_butir_'.$module}, 'putaran_id' => $round->id,
            'created_by' => 71, 'updated_by' => 72, 'created_at' => '2026-10-01 09:00:00', 'updated_at' => '2026-10-05 10:00:00'];
        $response = $db->table('tb_tanggapan')->insertGetId($audit + [
            'unit_kerja_id' => 1, 'butir_pic_id' => $butir->butirPics()->where('jenis_pic', 'utama')->value('id'),
            'tanggapan' => 'Isi asli '.$number, 'dokumen' => 'dokumen/asli-'.$number.'.pdf', 'deliverables' => 'Hasil asli '.$number,
        ]);
        $compilation = $db->table('tb_kompilasi')->insertGetId($audit + ['tahap_kompilasi' => 'tanggapan', 'hasil_kompilasi' => '<p>Kompilasi '.$number.'</p>']);
        $review = $db->table('tb_review')->insertGetId($audit + [
            'tahap_review' => 'tanggapan', 'kompilasi_id' => $compilation, 'id_tanggapan' => $response,
            'hasil_review' => 'Reviu asli '.$number, 'status' => 'belum_ditanggapi',
        ]);
        $sources[$number] = ['tb_tanggapan' => $response, 'tb_kompilasi' => $compilation, 'tb_review' => $review];
        if ($number > 0 && $number < $loopCount) {
            $service->arsipkan($round, 72);
        }
    }

    return compact('record', 'butir', 'rounds', 'sources');
}

/** @return array<string, array{columns: list<string>, rows: array}> */
function stageSourceSnapshot(string $module): array
{
    $db = DB::connection('mysql_'.$module);
    $tables = ['tb_record', 'tb_butir_'.$module, 'tb_butir_pic', 'tb_surat_putaran', 'tb_tanggapan', 'tb_tindak_lanjut', 'tb_kompilasi', 'tb_review'];

    return collect($tables)->mapWithKeys(fn ($table) => [$table => [
        'columns' => Schema::connection('mysql_'.$module)->getColumnListing($table),
        'rows' => $db->table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
    ]])->all();
}

function assertStageSourcesUnchanged(string $module, array $snapshot): void
{
    foreach ($snapshot as $table => $source) {
        $rows = DB::connection('mysql_'.$module)->table($table)->select($source['columns'])->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        expect($rows)->toEqual($source['rows']);
    }
}

test('R1 memetakan tanggapan awal dan dua looping menjadi tahap TL tanpa mengubah sumber', function (string $module) {
    $fixture = stageFoundationFixture($module);
    $before = stageSourceSnapshot($module);
    $this->migration->up();
    assertStageSourcesUnchanged($module, $before);
    $stages = SuratTahap::on('mysql_'.$module)->where('record_id', $fixture['record']->id)->orderBy('id')->get();
    expect($stages)->toHaveCount(3)
        ->and($stages->pluck('tahap_proses')->all())->toBe([TahapProses::TanggapanAwal, TahapProses::TindakLanjut, TahapProses::TindakLanjut])
        ->and($stages->pluck('nomor_putaran')->all())->toBe([0, 1, 2])
        ->and($stages->first()->penugasan_unit)->not->toBeNull()
        ->and($stages->first()->mulai_at)->toBeNull()
        ->and($stages->whereNotNull('jatuh_tempo'))->toHaveCount(0)
        ->and($fixture['record']->fresh()->tahap_aktif_id)->toBeNull();
    foreach ($stages as $number => $stage) {
        expect($stage->putaran_asal_id)->toBe($fixture['rounds'][$number])
            ->and($stage->tanggapanSumber()->pluck('id')->all())->toBe([$fixture['sources'][$number]['tb_tanggapan']])
            ->and($stage->kompilasi()->pluck('id')->all())->toBe([$fixture['sources'][$number]['tb_kompilasi']])
            ->and($stage->reviu()->pluck('id')->all())->toBe([$fixture['sources'][$number]['tb_review']]);
    }
})->with(['snp', 'djsn']);

test('R1 tidak menebak daftar atau versi bahan historis dari tanggal sumber', function (string $module) {
    $fixture = stageFoundationFixture($module, 0);
    $db = DB::connection('mysql_'.$module);
    $db->table('tb_tanggapan')->update(['created_at' => '2026-09-01 09:00:00', 'updated_at' => '2026-09-01 09:00:00']);
    $this->migration->up();
    expect($db->table('tb_kompilasi_bahan')->count())->toBe(0)
        ->and($db->table('tb_kompilasi')->value('status_bahan'))->toBe('perlu_rekonsiliasi')
        ->and($db->table('tb_kompilasi')->value('bahan_dicatat_at'))->toBeNull()
        ->and($db->table('tb_surat_rekonsiliasi')->where('kode', 'bahan_historis_belum_terbukti')->count())->toBe(1)
        ->and($db->table('tb_surat_rekonsiliasi')->where('kode', 'tanggal_tahap_belum_terverifikasi')->count())->toBe(1);
})->with(['snp', 'djsn']);

test('R1 dapat diulang dan tidak menimpa keputusan rekonsiliasi atau sumber', function () {
    foreach (['snp', 'djsn'] as $module) {
        stageFoundationFixture($module);
    }
    $before = ['snp' => stageSourceSnapshot('snp'), 'djsn' => stageSourceSnapshot('djsn')];
    $this->migration->up();
    $issue = SuratRekonsiliasi::on('mysql_snp')->firstOrFail();
    $issue->update(['status' => 'selesai', 'diselesaikan_by' => 81, 'diselesaikan_at' => now(), 'catatan_penyelesaian' => 'Bukti diperiksa pengguna berwenang']);
    $snapshot = $issue->fresh()->getRawOriginal();
    $this->migration->up();
    $this->artisan('workflow:backfill-tahap')->assertSuccessful();
    (new SuratTahapSeeder)->run();
    foreach (['snp', 'djsn'] as $module) {
        assertStageSourcesUnchanged($module, $before[$module]);
        expect(array_sum((new SuratTahapBackfill)->run('mysql_'.$module)))->toBe(0)
            ->and(SuratTahap::on('mysql_'.$module)->count())->toBe(3);
    }
    expect($issue->fresh()->getRawOriginal())->toBe($snapshot);
    $this->artisan('workflow:backfill-tahap --module=rawas')->assertFailed();
});

test('R1 mempertahankan TL lama dan menandai campuran penomoran untuk rekonsiliasi', function (string $module) {
    $fixture = stageFoundationFixture($module);
    $db = DB::connection('mysql_'.$module);
    $followUp = $db->table('tb_tindak_lanjut')->insertGetId([
        'id_butir_'.$module => $fixture['butir']->{'id_butir_'.$module}, 'putaran_id' => $fixture['rounds'][0],
        'putaran_tl' => 3, 'tindak_lanjut' => 'TL lama asli', 'created_by' => 91,
    ]);
    $before = stageSourceSnapshot($module);
    $this->migration->up();
    assertStageSourcesUnchanged($module, $before);
    $legacyStage = SuratTahap::on('mysql_'.$module)->findOrFail($db->table('tb_tindak_lanjut')->where('id', $followUp)->value('tahap_id'));
    expect($legacyStage->nomor_putaran)->toBe(3)
        ->and($legacyStage->tahap_proses)->toBe(TahapProses::TindakLanjut)
        ->and($legacyStage->status_pemetaan)->toBe('perlu_rekonsiliasi')
        ->and(SuratTahap::on('mysql_'.$module)->where('kunci_asal', 'like', 'putaran:%')->whereNull('nomor_putaran')->count())->toBe(2)
        ->and($db->table('tb_surat_rekonsiliasi')->where('kode', 'unit_pengisi_belum_terverifikasi')->exists())->toBeTrue();
})->with(['snp', 'djsn']);

test('R1 menangani TL DJSN tanpa nomor putaran melalui referensi langsung tanpa menebak urutannya', function () {
    $fixture = stageFoundationFixture('djsn', 0);
    $db = DB::connection('mysql_djsn');
    foreach (['tb_tindak_lanjut', 'tb_review'] as $table) {
        Schema::connection('mysql_djsn')->table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn('putaran_tl'));
    }
    $followUp = $db->table('tb_tindak_lanjut')->insertGetId([
        'id_butir_djsn' => $fixture['butir']->id_butir_djsn, 'putaran_id' => $fixture['rounds'][0],
        'tindak_lanjut' => 'TL tanpa nomor',
    ]);
    $review = $db->table('tb_review')->insertGetId([
        'id_butir_djsn' => $fixture['butir']->id_butir_djsn, 'putaran_id' => $fixture['rounds'][0],
        'tahap_review' => 'tindak_lanjut', 'id_tindak_lanjut' => $followUp,
    ]);
    $this->migration->up();
    $stageId = $db->table('tb_tindak_lanjut')->where('id', $followUp)->value('tahap_id');
    expect(SuratTahap::on('mysql_djsn')->findOrFail($stageId)->nomor_putaran)->toBeNull()
        ->and($db->table('tb_review')->where('id', $review)->value('tahap_id'))->toBe($stageId);
});

test('R1 tidak menebak unit pengisi dari penugasan pengguna saat ini', function (string $module) {
    stageFoundationFixture($module, 0);
    $db = DB::connection('mysql_'.$module);
    $db->table('tb_tanggapan')->update(['unit_kerja_id' => null, 'butir_pic_id' => null]);
    $this->migration->up();
    expect($db->table('tb_tanggapan')->value('unit_kerja_id'))->toBeNull()
        ->and($db->table('tb_surat_rekonsiliasi')->where('kode', 'unit_pengisi_belum_terverifikasi')->count())->toBe(1);
})->with(['snp', 'djsn']);

test('R1 memisahkan surat dan menolak referensi reviu lintas butir', function (string $module) {
    $first = stageFoundationFixture($module, 0);
    $second = stageFoundationFixture($module, 0);
    $db = DB::connection('mysql_'.$module);
    $db->table('tb_review')->where('id', $first['sources'][0]['tb_review'])->update(['id_tanggapan' => $second['sources'][0]['tb_tanggapan']]);
    $this->migration->up();
    expect($db->table('tb_review')->where('id', $first['sources'][0]['tb_review'])->value('tahap_id'))->toBeNull()
        ->and($db->table('tb_surat_rekonsiliasi')->where('kode', 'hubungan_reviu_tidak_konsisten')->count())->toBe(1);
    $firstStage = $first['record']->tahapanProses()->firstOrFail();
    $opposite = $module === 'snp' ? DjsnTanggapan::class : SnpTanggapan::class;
    expect(fn () => $opposite::untukTahap($firstStage)->get())->toThrow(InvalidArgumentException::class);
})->with(['snp', 'djsn']);

test('R1 mempertahankan Close penanda isian susulan dan putaran kosong', function (string $module) {
    $fixture = stageFoundationFixture($module, 0);
    $db = DB::connection('mysql_'.$module);
    $emptyRound = (new SuratPutaranService)->buka($fixture['record'], 71);
    $db->table('tb_record')->where('id', $fixture['record']->id)->update([
        'status' => 'tindak_lanjut_sesuai', 'closed_at' => '2026-10-20 10:00:00', 'closed_by' => 71,
        'closed_putaran_id' => $emptyRound->id, 'closed_review_id' => $fixture['sources'][0]['tb_review'],
    ]);
    $db->table('tb_tanggapan')->update(['submitted_after_close_at' => '2026-10-21 10:00:00']);
    $before = stageSourceSnapshot($module);
    $this->migration->up();
    assertStageSourcesUnchanged($module, $before);
    expect($fixture['record']->tahapanProses()->count())->toBe(2)
        ->and($db->table('tb_kompilasi_bahan')->count())->toBe(0);
})->with(['snp', 'djsn']);

test('fondasi salinan menolak sumber lintas tahap dan mempertahankan isi yang sudah dicatat', function (string $module) {
    $fixture = stageFoundationFixture($module);
    $this->migration->up();
    $responseClass = $module === 'snp' ? SnpTanggapan::class : DjsnTanggapan::class;
    $compilationClass = $module === 'snp' ? SnpKompilasi::class : DjsnKompilasi::class;
    $response = $responseClass::findOrFail($fixture['sources'][0]['tb_tanggapan']);
    $compilation = $compilationClass::findOrFail($fixture['sources'][0]['tb_kompilasi']);
    $other = $responseClass::findOrFail($fixture['sources'][1]['tb_tanggapan']);
    expect(fn () => KompilasiBahan::factory()->forSumber($compilation, $other)->create())->toThrow(InvalidArgumentException::class);
    $material = KompilasiBahan::factory()->forSumber($compilation, $response)->create(['salinan' => ['isi' => 'Salinan fixture asli']]);
    expect($compilation->bahan()->count())->toBe(1)
        ->and($material->tahap->tanggapanSumber()->count())->toBe(1)
        ->and(fn () => $material->update(['salinan' => ['isi' => 'Ubah']]))->toThrow(LogicException::class)
        ->and(fn () => $material->fresh()->delete())->toThrow(LogicException::class);
    expect($material->fresh()->salinan)->toBe(['isi' => 'Salinan fixture asli']);
})->with(['snp', 'djsn']);

test('identitas tahap dan jejak pemetaan yang sudah ada tidak boleh dipindahkan', function (string $module) {
    $fixture = stageFoundationFixture($module, 0);
    $this->migration->up();
    $stage = $fixture['record']->tahapanProses()->firstOrFail();
    expect(fn () => $stage->update(['nomor_putaran' => 5]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $stage->fresh()->update(['kunci_asal' => 'dipindah']))->toThrow(LogicException::class)
        ->and(fn () => $stage->fresh()->delete())->toThrow(LogicException::class);
    $new = SuratTahap::factory()->forSurat($fixture['record'])->tindakLanjut(8)->create();
    $class = $module === 'snp' ? SnpTanggapan::class : DjsnTanggapan::class;
    $response = $class::findOrFail($fixture['sources'][0]['tb_tanggapan']);
    expect(fn () => $response->forceFill(['tahap_id' => $new->id])->save())->toThrow(LogicException::class);
})->with(['snp', 'djsn']);

test('pemeriksaan command R1 hanya membaca dan tidak menggandakan data', function () {
    stageFoundationFixture('snp', 0);
    $this->migration->up();
    $before = DB::connection('mysql_snp')->table('tb_surat_rekonsiliasi')->get()->toJson();
    $this->artisan('workflow:backfill-tahap --check --module=snp')->assertSuccessful();
    expect(DB::connection('mysql_snp')->table('tb_surat_rekonsiliasi')->get()->toJson())->toBe($before);
});

test('rollback R1 dapat dibangun ulang tanpa kehilangan sumber dan ditolak setelah dipakai', function () {
    stageFoundationFixture('snp', 0);
    stageFoundationFixture('djsn', 0);
    $before = ['snp' => stageSourceSnapshot('snp'), 'djsn' => stageSourceSnapshot('djsn')];
    $this->migration->up();
    $this->migration->down();
    foreach ($before as $module => $snapshot) {
        assertStageSourcesUnchanged($module, $snapshot);
        expect(Schema::connection('mysql_'.$module)->hasTable('tb_surat_tahap'))->toBeFalse();
    }
    $this->migration->up();
    DB::connection('mysql_djsn')->table('tb_record')->update(['tahap_aktif_id' => DB::connection('mysql_djsn')->table('tb_surat_tahap')->value('id')]);
    expect(fn () => $this->migration->down())->toThrow(RuntimeException::class)
        ->and(Schema::connection('mysql_snp')->hasTable('tb_surat_tahap'))->toBeTrue();
});

test('R1 menolak sumber tanpa putaran sah sebelum perubahan skema pada kedua modul', function () {
    $fixture = stageFoundationFixture('djsn', 0);
    DB::connection('mysql_djsn')->table('tb_tanggapan')->update(['putaran_id' => null]);
    expect(fn () => $this->migration->up())->toThrow(RuntimeException::class)
        ->and(Schema::connection('mysql_snp')->hasTable('tb_surat_tahap'))->toBeFalse()
        ->and(DB::connection('mysql_djsn')->table('tb_tanggapan')->value('tanggapan'))->toBe('Isi asli 0');
});

test('fondasi R1 tidak menghalangi penghapusan draft kosong melalui aturan yang sudah berlaku', function (string $module) {
    $class = $module === 'snp' ? SnpRecord::class : DjsnRecord::class;
    $record = $class::create(['nomor_surat' => 'DRAFT KOSONG', 'tanggal_surat' => '2026-10-23']);
    $this->migration->up();
    $stage = $record->tahapanProses()->firstOrFail();
    $id = $record->id;
    $record->delete();
    expect($stage->fresh()->record_id)->toBeNull()
        ->and($stage->fresh()->bukti_pemetaan['surat_asal']['id'])->toBe($id)
        ->and(SuratRekonsiliasi::on('mysql_'.$module)->whereNull('record_id')->count())->toBe(0);
})->with(['snp', 'djsn']);

test('R1 mempertahankan semua isian unit ganda dan membuat daftar rekonsiliasi', function (string $module) {
    $fixture = stageFoundationFixture($module, 0);
    $db = DB::connection('mysql_'.$module);
    $db->table('tb_tanggapan')->insert([
        'id_butir_'.$module => $fixture['butir']->{'id_butir_'.$module}, 'putaran_id' => $fixture['rounds'][0],
        'unit_kerja_id' => 1, 'tanggapan' => 'Sumber ganda yang harus tetap disimpan',
    ]);
    $before = stageSourceSnapshot($module);
    $this->migration->up();
    assertStageSourcesUnchanged($module, $before);
    expect($db->table('tb_tanggapan')->count())->toBe(2)
        ->and($db->table('tb_surat_rekonsiliasi')->where('kode', 'isian_unit_ganda')->count())->toBe(2);
})->with(['snp', 'djsn']);

test('backfill historis R1 tidak mengganti tahap alur revisi yang sudah diaktifkan', function (string $module) {
    $fixture = stageFoundationFixture($module);
    $this->migration->up();
    $stage = SuratTahap::factory()->forSurat($fixture['record'])->tindakLanjut(8)->create();
    DB::connection('mysql_'.$module)->table('tb_record')->where('id', $fixture['record']->id)->update(['tahap_aktif_id' => $stage->id]);
    $before = $stage->fresh()->getRawOriginal();
    expect(array_sum((new SuratTahapBackfill)->run('mysql_'.$module)))->toBe(0)
        ->and($stage->fresh()->getRawOriginal())->toBe($before)
        ->and($fixture['record']->fresh()->tahap_aktif_id)->toBe($stage->id);
})->with(['snp', 'djsn']);

test('rekonsiliasi memerlukan jejak pemeriksaan dan hasilnya tidak boleh ditimpa', function () {
    $fixture = stageFoundationFixture('snp', 0);
    $this->migration->up();
    $item = SuratRekonsiliasi::factory()->forSurat($fixture['record'])->create();
    expect(fn () => $item->update(['status' => 'selesai']))->toThrow(InvalidArgumentException::class);
    $item->refresh()->update(['status' => 'selesai', 'diselesaikan_by' => 81, 'diselesaikan_at' => now(), 'catatan_penyelesaian' => 'Bukti diverifikasi']);
    expect(fn () => $item->update(['catatan_penyelesaian' => 'Menimpa hasil lama']))->toThrow(LogicException::class);
});

test('fondasi tahap aktif menolak surat berbeda dan mempertahankan identitas Close', function (string $module) {
    $first = stageFoundationFixture($module, 0);
    $second = stageFoundationFixture($module, 0);
    $this->migration->up();
    $firstStage = $first['record']->tahapanProses()->firstOrFail();
    $secondStage = $second['record']->tahapanProses()->firstOrFail();
    expect(fn () => $first['record']->forceFill(['tahap_aktif_id' => $secondStage->id])->save())->toThrow(InvalidArgumentException::class);
    $record = $first['record']->fresh();
    $record->forceFill(['tahap_aktif_id' => $firstStage->id])->save();
    $next = SuratTahap::factory()->forSurat($record)->tindakLanjut(1)->create();
    DB::connection('mysql_'.$module)->table('tb_record')->where('id', $record->id)->update(['closed_at' => '2026-10-23 10:00:00', 'status' => 'tindak_lanjut_sesuai']);
    expect(fn () => $record->fresh()->forceFill(['tahap_aktif_id' => $next->id])->save())->toThrow(LogicException::class)
        ->and(fn () => $firstStage->update(['penugasan_unit' => []]))->toThrow(LogicException::class);
})->with(['snp', 'djsn']);

test('rollback R1 mempertahankan bukti kompilasi resmi meskipun tidak memiliki bahan unit', function () {
    stageFoundationFixture('snp', 0);
    $this->migration->up();
    DB::connection('mysql_snp')->table('tb_kompilasi')->update(['status_bahan' => 'terverifikasi', 'bahan_dicatat_at' => now()]);
    expect(fn () => $this->migration->down())->toThrow(RuntimeException::class)
        ->and(Schema::connection('mysql_djsn')->hasTable('tb_surat_tahap'))->toBeTrue();
});
