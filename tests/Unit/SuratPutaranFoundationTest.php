<?php

use App\Enums\SuratStatus;
use App\Models\DjsnButir;
use App\Models\DjsnKompilasi;
use App\Models\DjsnRecord;
use App\Models\DjsnReview;
use App\Models\DjsnTanggapan;
use App\Models\DjsnTindakLanjut;
use App\Models\SnpKompilasi;
use App\Models\SnpRecord;
use App\Models\SnpReview;
use App\Models\SnpTanggapan;
use App\Models\SnpTindakLanjut;
use App\Models\SuratPutaran;
use App\Models\User;
use App\Services\SuratPutaranBackfill;
use App\Services\SuratPutaranService;
use Database\Seeders\DjsnKompilasiSeeder;
use Database\Seeders\SuratPutaranSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config(['app.key' => 'base64:'.base64_encode(str_repeat('p', 32)), 'database.default' => 'mysql']);

    foreach (['mysql', 'mysql_snp', 'mysql_djsn'] as $connection) {
        config(['database.connections.'.$connection => ['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true]]);
        DB::purge($connection);
    }

    (require database_path('migrations/0001_01_01_000000_create_users_table.php'))->up();

    foreach (['snp', 'djsn'] as $module) {
        $schema = Schema::connection('mysql_'.$module);
        $schema->create('tb_record', function (Blueprint $table) use ($module): void {
            $table->id();
            $table->string('id_'.$module)->unique();
            $table->string('nomor_surat');
            $table->date('tanggal_surat');
            $table->text('perihal_surat')->nullable();
            $table->date('jth_tempo')->nullable();
            $table->string('status')->default('draft');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
        $schema->create('tb_butir_'.$module, function (Blueprint $table) use ($module): void {
            $table->id();
            $table->string('id_butir_'.$module)->unique();
            $table->string('id_'.$module);
            $table->text('butir_'.$module);
            $table->string('status')->default('terbit');
            $table->timestamps();
        });
        $schema->create('tb_butir_pic', function (Blueprint $table) use ($module): void {
            $table->id();
            $table->string('id_butir_'.$module);
            $table->unsignedBigInteger('unit_kerja_id')->nullable();
            $table->unsignedBigInteger('komite_id')->nullable();
            $table->string('jenis_pic');
        });
        foreach (['tb_tanggapan', 'tb_tindak_lanjut', 'tb_review', ...($module === 'snp' ? ['tb_kompilasi'] : [])] as $name) {
            $schema->create($name, function (Blueprint $table) use ($module, $name): void {
                $table->id();
                $table->string('id_butir_'.$module);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->text('deliverables')->nullable();
                $table->text('dokumen')->nullable();
                $table->timestamps();
                if ($module === 'snp') {
                    $table->unsignedInteger('putaran_tl')->default(1);
                    if (in_array($name, ['tb_tanggapan', 'tb_tindak_lanjut'])) {
                        $table->unsignedBigInteger('butir_pic_id')->nullable();
                    }
                }
                if ($name === 'tb_tanggapan') {
                    $table->text('tanggapan')->nullable();
                    if ($module === 'snp') {
                        $table->unique(['id_butir_snp', 'butir_pic_id'], 'tb_tanggapan_butir_pic_unique');
                    }
                } elseif ($name === 'tb_tindak_lanjut') {
                    $table->text('tindak_lanjut')->nullable();
                    $table->date('jth_tempo')->nullable();
                } elseif ($name === 'tb_kompilasi') {
                    $table->string('tahap_kompilasi')->default('tanggapan');
                    $table->longText('hasil_kompilasi')->nullable();
                    $table->string('status')->default('belum_dikompilasi');
                } else {
                    $table->string('tahap_review')->default('tanggapan');
                    $table->unsignedBigInteger('id_tanggapan')->nullable();
                    $table->unsignedBigInteger('id_tindak_lanjut')->nullable();
                    $table->unsignedBigInteger('komite_id')->nullable();
                    $table->longText('hasil_review')->nullable();
                    $table->string('status')->default('belum_ditanggapi');
                }
            });
        }

        $db = DB::connection('mysql_'.$module);
        $audit = ['created_by' => 91, 'updated_by' => 92, 'created_at' => '2026-01-05 09:00:00', 'updated_at' => '2026-02-06 11:30:00'];
        foreach ([1, 2] as $id) {
            $db->table('tb_record')->insert($audit + [
                'id' => $id, 'id_'.$module => 'SURAT-'.$id, 'nomor_surat' => 'B/'.$id,
                'tanggal_surat' => '2026-01-01', 'status' => 'dalam_proses',
            ]);
            $db->table('tb_butir_'.$module)->insert([
                'id' => $id, 'id_'.$module => 'SURAT-'.$id, 'id_butir_'.$module => 'BUTIR-'.$id,
                'butir_'.$module => 'Isi butir '.$id,
            ]);
            $db->table('tb_butir_pic')->insert([
                'id' => $id, 'id_butir_'.$module => 'BUTIR-'.$id, 'unit_kerja_id' => 10, 'jenis_pic' => 'utama',
            ]);
        }
        $db->table('tb_tanggapan')->insert($audit + ['id' => 17, 'id_butir_'.$module => 'BUTIR-1', 'tanggapan' => 'Tanggapan asli', ...($module === 'snp' ? ['butir_pic_id' => 1] : [])]);
        $db->table('tb_tindak_lanjut')->insert($audit + ['id' => 29, 'id_butir_'.$module => 'BUTIR-1', 'tindak_lanjut' => 'TL asli', ...($module === 'snp' ? ['putaran_tl' => 3, 'butir_pic_id' => 1] : [])]);
        $db->table('tb_review')->insert($audit + ['id' => 35, 'id_butir_'.$module => 'BUTIR-1', 'id_tanggapan' => 17, 'hasil_review' => 'Reviu asli']);
        $db->table('tb_review')->insert($audit + ['id' => 36, 'id_butir_'.$module => 'BUTIR-1', 'id_tindak_lanjut' => 29, 'tahap_review' => 'tindak_lanjut', ...($module === 'snp' ? ['putaran_tl' => 3] : [])]);
        if ($module === 'snp') {
            $db->table('tb_kompilasi')->insert($audit + ['id' => 23, 'id_butir_snp' => 'BUTIR-1', 'hasil_kompilasi' => '<p>Kompilasi asli</p>']);
        }
    }
    $this->migration = require database_path('migrations/2026_10_21_134321_add_surat_putaran_foundation.php');
});

/** @return array<string, list<array<string, mixed>>> */
function originalPutaranRows(string $module): array
{
    $db = DB::connection('mysql_'.$module);
    $tables = ['tb_record', 'tb_butir_'.$module, 'tb_butir_pic', 'tb_tanggapan', 'tb_review', 'tb_tindak_lanjut'];
    if ($module === 'snp') {
        $tables[] = 'tb_kompilasi';
    }

    return collect($tables)->mapWithKeys(fn ($table) => [$table => $db->table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all()])->all();
}

function assertOriginalPutaranRows(string $module, array $before): void
{
    foreach ($before as $table => $rows) {
        $actual = DB::connection('mysql_'.$module)->table($table)->select(array_keys($rows[0]))->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        expect($actual)->toEqual($rows);
    }
}

test('migrasi mempertahankan setiap kolom asli serta hubungan TL lama', function (string $module) {
    $before = originalPutaranRows($module);
    $this->actingAs(User::factory()->create());
    $this->migration->up();
    assertOriginalPutaranRows($module, $before);
    $db = DB::connection('mysql_'.$module);
    $putaran = SuratPutaran::on('mysql_'.$module)->where('record_id', 1)->firstOrFail();

    expect($putaran->isLegacy())->toBeTrue()
        ->and($putaran->created_by)->toBe(91)
        ->and($putaran->opened_at->format('Y-m-d H:i:s'))->toBe('2026-01-05 09:00:00')
        ->and($putaran->tanggapan()->pluck('id')->all())->toBe([17])
        ->and($putaran->reviu()->pluck('id')->all())->toBe([35, 36])
        ->and($putaran->tindakLanjutLama()->pluck('id')->all())->toBe([29])
        ->and($db->table('tb_record')->whereNotNull('putaran_aktif_id')->count())->toBe(0)
        ->and($db->table('tb_tanggapan')->value('unit_kerja_id'))->toBe($module === 'snp' ? 10 : null);

    if ($module === 'snp') {
        expect($db->table('tb_tindak_lanjut')->value('putaran_tl'))->toBe(3)
            ->and(SnpReview::findOrFail(35)->kompilasiPutaran->id)->toBe(23);
    }
})->with(['snp', 'djsn']);

test('migrasi backfill command dan seeder dapat diulang tanpa menimpa sumber', function () {
    $before = ['snp' => originalPutaranRows('snp'), 'djsn' => originalPutaranRows('djsn')];
    $this->migration->up();
    $this->migration->up();
    $this->artisan('workflow:backfill-putaran')->assertSuccessful();
    $this->artisan('workflow:backfill-putaran --module=rawas')->assertFailed();
    (new SuratPutaranSeeder)->run();
    (new DjsnKompilasiSeeder)->run();

    foreach ($before as $module => $rows) {
        assertOriginalPutaranRows($module, $rows);
        expect(SuratPutaran::on('mysql_'.$module)->count())->toBe(2)
            ->and(array_sum((new SuratPutaranBackfill)->run('mysql_'.$module)))->toBe(0);
    }
});

test('penyimpanan alur lama sesudah migrasi tetap masuk riwayat lama', function (string $module) {
    $this->migration->up();
    $class = $module === 'snp' ? SnpTanggapan::class : DjsnTanggapan::class;
    $user = User::factory()->create();
    $this->actingAs($user);
    $response = $class::create(['id_butir_'.$module => 'BUTIR-2', 'tanggapan' => 'Jawaban baru alur lama', 'butir_pic_id' => 2]);
    $response->update(['tanggapan' => 'Jawaban diedit']);

    expect($response->putaran->nomor_putaran)->toBe(0)
        ->and($response->created_by)->toBe($user->id)
        ->and($response->updated_by)->toBe($user->id)
        ->and($response->fresh()->tanggapan)->toBe('Jawaban diedit');
})->with(['snp', 'djsn']);

test('tiga putaran menyimpan tanggapan kompilasi dan reviu secara terpisah', function (string $module) {
    $this->migration->up();
    $recordClass = $module === 'snp' ? SnpRecord::class : DjsnRecord::class;
    $responseClass = $module === 'snp' ? SnpTanggapan::class : DjsnTanggapan::class;
    $compilationClass = $module === 'snp' ? SnpKompilasi::class : DjsnKompilasi::class;
    $reviewClass = $module === 'snp' ? SnpReview::class : DjsnReview::class;
    $record = $recordClass::findOrFail(1);
    $workflow = new SuratPutaranService;
    $ids = [];
    $previous = [];

    foreach ([1, 2, 3] as $number) {
        $putaran = $workflow->buka($record, 51);
        expect($putaran->nomor_putaran)->toBe($number);
        expect(fn () => $workflow->buka($record))->toThrow(LogicException::class);
        $response = $putaran->tanggapan()->create(['id_butir_'.$module => 'BUTIR-1', 'unit_kerja_id' => 10, 'butir_pic_id' => 1, 'tanggapan' => 'Jawaban putaran '.$number, 'created_by' => 51]);
        $response->update(['tanggapan' => 'Perbaikan putaran '.$number]);
        expect(fn () => $putaran->tanggapan()->create(['id_butir_'.$module => 'BUTIR-1', 'unit_kerja_id' => 10, 'butir_pic_id' => 1]))->toThrow(LogicException::class);
        $compilation = $putaran->kompilasi()->create(['id_butir_'.$module => 'BUTIR-1', 'tahap_kompilasi' => 'tanggapan', 'hasil_kompilasi' => 'Kompilasi '.$number]);
        $review = $putaran->reviu()->create(['id_butir_'.$module => 'BUTIR-1', 'tahap_review' => 'tanggapan', 'id_tanggapan' => $response->id, 'kompilasi_id' => $compilation->id, 'hasil_review' => 'Reviu '.$number]);
        $ids[] = $response->id;
        expect($review->kompilasiPutaran->id)->toBe($compilation->id)
            ->and($responseClass::untukPutaran($putaran)->pluck('id')->all())->toBe([$response->id])
            ->and($compilationClass::untukPutaran($putaran)->count())->toBe(1)
            ->and($reviewClass::untukPutaran($putaran)->count())->toBe(1);

        foreach ($previous as [$old, $snapshot]) {
            expect($old->fresh()->getRawOriginal())->toEqual($snapshot);
            expect(fn () => $old->update(['tanggapan' => 'Tidak boleh menimpa']))->toThrow(LogicException::class);
            expect(fn () => $old->fresh()->delete())->toThrow(LogicException::class);
        }

        $workflow->arsipkan($putaran, 52);
        $previous[] = [$response, $response->fresh()->getRawOriginal()];
        expect(fn () => $compilation->update(['hasil_kompilasi' => 'Ubah arsip']))->toThrow(LogicException::class);
        expect(fn () => $review->update(['hasil_review' => 'Ubah arsip']))->toThrow(LogicException::class);
    }

    expect(array_unique($ids))->toHaveCount(3)
        ->and($record->putaran()->pluck('nomor_putaran')->all())->toBe([0, 1, 2, 3])
        ->and($responseClass::findOrFail(17)->tanggapan)->toBe('Tanggapan asli');
})->with(['snp', 'djsn']);

test('hubungan lintas surat modul atau putaran ditolak', function (string $module) {
    $this->migration->up();
    $class = $module === 'snp' ? SnpRecord::class : DjsnRecord::class;
    $workflow = new SuratPutaranService;
    $first = $workflow->buka($class::findOrFail(1));
    $other = $workflow->buka($class::findOrFail(2));

    expect(fn () => $first->tanggapan()->create(['id_butir_'.$module => 'BUTIR-2', 'unit_kerja_id' => 10]))->toThrow(InvalidArgumentException::class);
    expect(fn () => $first->reviu()->create(['id_butir_'.$module => 'BUTIR-1', 'tahap_review' => 'tanggapan', 'id_tanggapan' => 17]))->toThrow(InvalidArgumentException::class);

    $response = $first->tanggapan()->create(['id_butir_'.$module => 'BUTIR-1', 'unit_kerja_id' => 10]);
    expect(fn () => $response->update(['putaran_id' => $other->id]))->toThrow(LogicException::class);
    $opposite = $module === 'snp' ? DjsnTanggapan::class : SnpTanggapan::class;
    expect(fn () => $opposite::untukPutaran($first)->get())->toThrow(InvalidArgumentException::class);
    expect(fn () => $first->delete())->toThrow(LogicException::class);
})->with(['snp', 'djsn']);

test('status resmi tetap di surat dan sinkronisasi lama tidak menimpanya setelah aktivasi', function (string $module) {
    $this->migration->up();
    $class = $module === 'snp' ? SnpRecord::class : DjsnRecord::class;
    $record = $class::findOrFail(1);
    $record->syncStatusFromButir();
    expect($record->fresh()->status)->toBe('dalam_proses');
    $putaran = (new SuratPutaranService)->buka($record);
    $record->update(['status' => SuratStatus::TindakLanjutBelumSesuai->value]);
    $putaran->tanggapan()->create(['id_butir_'.$module => 'BUTIR-1', 'unit_kerja_id' => 10]);
    $record->syncStatusFromButir();

    expect($record->fresh()->statusSurat())->toBe(SuratStatus::TindakLanjutBelumSesuai)
        ->and($record->statusSurat()->label())->toBe('Proses / Tindak Lanjut Belum Sesuai')
        ->and(SuratStatus::TindakLanjutSesuai->isClosed())->toBeTrue()
        ->and(SuratStatus::TidakDapatDitindaklanjuti->isClosed())->toBeTrue()
        ->and(SuratStatus::Proses->isClosed())->toBeFalse();
})->with(['snp', 'djsn']);

test('factory kompilasi DJSN menautkan butir dan putaran yang benar', function () {
    $this->migration->up();
    $putaran = (new SuratPutaranService)->buka(DjsnRecord::findOrFail(1));
    $compilation = DjsnKompilasi::factory()->forButir(DjsnButir::findOrFail(1))->forPutaran($putaran)->create();
    expect($compilation->putaran->id)->toBe($putaran->id)->and($compilation->butir->id)->toBe(1);
    $factory = SuratPutaran::factory()->legacy()->make(['record_id' => 1]);
    expect($factory->isLegacy())->toBeTrue();
});

test('penomoran TL lama tetap berjalan tanpa dianggap putaran surat baru', function (string $module) {
    $this->migration->up();
    $class = $module === 'snp' ? SnpTindakLanjut::class : DjsnTindakLanjut::class;
    $reviewClass = $module === 'snp' ? SnpReview::class : DjsnReview::class;
    $followUp = $class::create([
        'id_butir_'.$module => 'BUTIR-1', 'tindak_lanjut' => 'TL berikutnya', 'created_by' => 71,
        ...($module === 'snp' ? ['putaran_tl' => 4, 'butir_pic_id' => 1] : []),
    ]);
    $review = $reviewClass::create([
        'id_butir_'.$module => 'BUTIR-1', 'id_tindak_lanjut' => $followUp->id, 'tahap_review' => 'tindak_lanjut',
        ...($module === 'snp' ? ['putaran_tl' => 4] : []),
    ]);

    expect($followUp->putaran->nomor_putaran)->toBe(0)
        ->and($review->putaran_id)->toBe($followUp->putaran_id)
        ->and($review->tindakLanjut->id)->toBe($followUp->id)
        ->and($followUp->created_by)->toBe(71)
        ->and($class::findOrFail(29)->tindak_lanjut)->toBe('TL asli');
})->with(['snp', 'djsn']);

test('setiap unit baru harus sesuai penugasan dan dapat mengisi terpisah pada putaran sama', function (string $module) {
    $db = DB::connection('mysql_'.$module);
    $db->table('tb_butir_pic')->insert(['id' => 3, 'id_butir_'.$module => 'BUTIR-1', 'unit_kerja_id' => 20, 'jenis_pic' => 'pendukung']);
    $this->migration->up();
    $recordClass = $module === 'snp' ? SnpRecord::class : DjsnRecord::class;
    $putaran = (new SuratPutaranService)->buka($recordClass::findOrFail(1));

    expect(fn () => $putaran->tanggapan()->create(['id_butir_'.$module => 'BUTIR-1']))->toThrow(InvalidArgumentException::class);
    expect(fn () => $putaran->tanggapan()->create(['id_butir_'.$module => 'BUTIR-1', 'unit_kerja_id' => 99]))->toThrow(InvalidArgumentException::class);
    expect(fn () => $putaran->tanggapan()->create(['id_butir_'.$module => 'BUTIR-1', 'butir_pic_id' => 2]))->toThrow(InvalidArgumentException::class);
    foreach ([1 => 10, 3 => 20] as $picId => $unitId) {
        $putaran->tanggapan()->create(['id_butir_'.$module => 'BUTIR-1', 'butir_pic_id' => $picId]);
    }
    expect($putaran->tanggapan()->orderBy('unit_kerja_id')->pluck('unit_kerja_id')->all())->toBe([10, 20]);
})->with(['snp', 'djsn']);

test('data lama tanpa PIC tetap tersimpan tanpa menebak unitnya', function () {
    DB::connection('mysql_snp')->table('tb_tanggapan')->insert(['id_butir_snp' => 'BUTIR-1', 'tanggapan' => 'PIC tidak tercatat', 'created_by' => 77]);
    $this->migration->up();
    $unknown = SnpTanggapan::where('created_by', 77)->firstOrFail();
    expect($unknown->unit_kerja_id)->toBeNull()->and($unknown->putaran->isLegacy())->toBeTrue()->and($unknown->tanggapan)->toBe('PIC tidak tercatat');
});

test('data tanpa putaran setelah aktivasi tidak boleh ditebak sebagai data lama', function () {
    $this->migration->up();
    (new SuratPutaranService)->buka(DjsnRecord::findOrFail(1));
    $id = DB::connection('mysql_djsn')->table('tb_tanggapan')->insertGetId(['id_butir_djsn' => 'BUTIR-1', 'tanggapan' => 'Sumber tidak lengkap']);
    expect(fn () => (new SuratPutaranBackfill)->run('mysql_djsn'))->toThrow(RuntimeException::class);
    expect(fn () => DjsnTanggapan::findOrFail($id)->update(['tanggapan' => 'Jangan pindahkan']))->toThrow(LogicException::class);
    expect(DjsnTanggapan::findOrFail($id)->putaran_id)->toBeNull();
});

test('rollback tanpa putaran baru mengembalikan skema dan mempertahankan semua data asli', function () {
    $before = ['snp' => originalPutaranRows('snp'), 'djsn' => originalPutaranRows('djsn')];
    $this->migration->up();
    $this->migration->down();
    foreach ($before as $module => $rows) {
        assertOriginalPutaranRows($module, $rows);
        expect(Schema::connection('mysql_'.$module)->hasTable('tb_surat_putaran'))->toBeFalse();
    }
    $this->migration->up();
});

test('rollback ditolak sebelum menghapus fondasi jika sudah ada putaran baru', function () {
    $this->migration->up();
    (new SuratPutaranService)->buka(DjsnRecord::findOrFail(1));
    expect(fn () => $this->migration->down())->toThrow(RuntimeException::class)
        ->and(Schema::connection('mysql_snp')->hasColumn('tb_tanggapan', 'putaran_id'))->toBeTrue()
        ->and(SuratPutaran::on('mysql_djsn')->where('nomor_putaran', 1)->count())->toBe(1);
});

test('sumber tanpa hubungan surat ditolak sebelum perubahan skema', function () {
    DB::connection('mysql_djsn')->table('tb_tanggapan')->insert(['id_butir_djsn' => 'BUTIR-TIDAK-ADA', 'tanggapan' => 'Jangan hapus']);
    expect(fn () => $this->migration->up())->toThrow(RuntimeException::class)
        ->and(Schema::connection('mysql_snp')->hasTable('tb_surat_putaran'))->toBeFalse()
        ->and(DB::connection('mysql_djsn')->table('tb_tanggapan')->count())->toBe(2);
});
