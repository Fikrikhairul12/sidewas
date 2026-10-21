<?php

use App\Models\DjsnButir;
use App\Models\DjsnRecord;
use App\Models\DjsnTanggapan;
use App\Models\SnpButir;
use App\Models\SnpRecord;
use App\Models\SnpTanggapan;
use App\Services\SuratAwalStatusService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->travelTo(now('Asia/Jakarta')->setDate(2026, 10, 21)->startOfDay());
    Mail::fake();
    Notification::fake();
    foreach (['snp', 'djsn'] as $module) {
        $connection = 'mysql_'.$module;
        config(['database.connections.'.$connection => ['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true]]);
        DB::purge($connection);
        $schema = Schema::connection($connection);
        $schema->create('tb_record', function (Blueprint $table) use ($module): void {
            $table->id();
            $table->string('id_'.$module)->unique();
            $table->string('nomor_surat');
            $table->date('tanggal_surat')->nullable();
            $table->date('jth_tempo')->nullable();
            $table->string('status')->default('draft');
            $table->unsignedBigInteger('putaran_aktif_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
        $schema->create('tb_butir_'.$module, function (Blueprint $table) use ($module): void {
            $table->id();
            $table->string('id_butir_'.$module)->unique();
            $table->string('id_'.$module);
            $table->text('butir_'.$module)->nullable();
            $table->string('status')->default('terbit');
            $table->timestamps();
        });
        $schema->create('tb_tanggapan', function (Blueprint $table) use ($module): void {
            $table->id();
            $table->string('id_butir_'.$module);
            $table->text('tanggapan')->nullable();
            $table->unsignedBigInteger('butir_pic_id')->nullable();
            $table->unsignedBigInteger('unit_kerja_id')->nullable();
            $table->timestamps();
        });
    }
    $this->migration = require database_path('migrations/2026_10_21_141454_enable_snp_djsn_initial_letter_statuses.php');
    $this->migration->up();
});

afterEach(function () {
    $this->travelBack();
});

function awalStatusRecord(string $module, string $date = '2026-10-21'): SnpRecord|DjsnRecord
{
    $class = $module === 'snp' ? SnpRecord::class : DjsnRecord::class;

    return $class::create(['nomor_surat' => 'B/UJI-'.bin2hex(random_bytes(3)), 'tanggal_surat' => $date]);
}

function awalStatusButir(string $module, SnpRecord|DjsnRecord $record): SnpButir|DjsnButir
{
    $class = $module === 'snp' ? SnpButir::class : DjsnButir::class;

    return $class::create(['id_'.$module => $record->{'id_'.$module}, 'butir_'.$module => 'Butir uji']);
}

test('batas status tepat tanggal surat tambah 14 kalender termasuk akhir pekan', function (string $module, string $date, string $expected) {
    $record = awalStatusRecord($module, $date);
    expect($record->fresh()->status)->toBe($expected);
})->with(['snp', 'djsn'])->with([
    'baru' => ['2026-10-21', 'draft'],
    'hari ketiga belas' => ['2026-10-08', 'draft'],
    'tepat empat belas hari' => ['2026-10-07', 'belum_ditindaklanjuti'],
    'lebih dari empat belas' => ['2026-10-06', 'belum_ditindaklanjuti'],
    'tanggal masa depan' => ['2026-10-22', 'draft'],
]);

test('pergantian hari Jakarta memperbarui status tanpa mengubah jejak pengguna atau mengirim reminder', function (string $module) {
    $record = awalStatusRecord($module, '2026-10-08');
    $record->update(['created_by' => 51, 'updated_by' => 52]);
    $before = $record->fresh()->getRawOriginal();
    $this->travelTo(now('Asia/Jakarta')->setDate(2026, 10, 22)->startOfDay());
    $this->artisan('workflow:sync-status', ['--module' => [$module]])->assertSuccessful();
    $after = $record->fresh();

    expect($after->status)->toBe('belum_ditindaklanjuti')
        ->and($after->getRawOriginal('created_at'))->toBe($before['created_at'])
        ->and($after->getRawOriginal('updated_at'))->toBe($before['updated_at'])
        ->and($after->created_by)->toBe(51)->and($after->updated_by)->toBe(52)
        ->and($after->getRawOriginal('jth_tempo'))->toBe($before['jth_tempo']);
    Mail::assertNothingSent();
    Mail::assertNothingQueued();
    Notification::assertNothingSent();
})->with(['snp', 'djsn']);

test('butir pertama menerbitkan surat dan satu tanggapan sudah membuat proses', function (string $module) {
    $record = awalStatusRecord($module, '2026-01-01');
    expect($record->status)->toBe('belum_ditindaklanjuti');
    $butir = awalStatusButir($module, $record);
    $second = awalStatusButir($module, $record);
    expect($record->fresh()->status)->toBe('terbit')->and($record->fresh()->pernah_terbit)->toBeTrue()
        ->and($record->fresh()->batasStatusAwal())->toBeNull();
    $class = $module === 'snp' ? SnpTanggapan::class : DjsnTanggapan::class;
    $class::create(['id_butir_'.$module => $butir->{'id_butir_'.$module}, 'tanggapan' => 'Jawaban Unit A', 'unit_kerja_id' => 10]);

    expect($record->fresh()->status)->toBe('proses')
        ->and(DB::connection('mysql_'.$module)->table('tb_tanggapan')->where('id_butir_'.$module, $second->{'id_butir_'.$module})->count())->toBe(0);
    $this->travelTo(now('Asia/Jakarta')->addYears(2));
    (new SuratAwalStatusService)->synchronizeModule($module);
    expect($record->fresh()->status)->toBe('proses');
})->with(['snp', 'djsn']);

test('placeholder kosong belum dianggap tanggapan dan kompilasi bukan pemicu proses', function (string $module) {
    $record = awalStatusRecord($module, '2026-01-01');
    $butir = awalStatusButir($module, $record);
    $db = DB::connection('mysql_'.$module);
    $db->table('tb_tanggapan')->insert(['id_butir_'.$module => $butir->{'id_butir_'.$module}, 'tanggapan' => '  ']);
    (new SuratAwalStatusService)->synchronizeModule($module);
    expect($record->fresh()->status)->toBe('terbit');
    $butir->update(['status' => 'dalam_proses']);
    expect($record->fresh()->status)->toBe('terbit');
})->with(['snp', 'djsn']);

test('penanda penerbitan mencegah batas awal aktif lagi walau butir tidak tersisa', function (string $module) {
    $record = awalStatusRecord($module, '2026-01-01');
    $butir = awalStatusButir($module, $record);
    $butir->delete();
    $this->travelTo(now('Asia/Jakarta')->addYears(3));
    (new SuratAwalStatusService)->synchronizeModule($module);
    expect($record->fresh()->status)->toBe('terbit')->and($record->fresh()->pernah_terbit)->toBeTrue();
})->with(['snp', 'djsn']);

test('hasil reviu dan status selesai tidak ditimpa saat tanggal berubah atau tanggapan baru masuk', function (string $module, string $status) {
    $record = awalStatusRecord($module, '2026-01-01');
    $butir = awalStatusButir($module, $record);
    $record->forceFill(['status' => $status, 'putaran_aktif_id' => 2])->save();
    $this->travelTo(now('Asia/Jakarta')->addYears(2));
    $class = $module === 'snp' ? SnpTanggapan::class : DjsnTanggapan::class;
    $class::create(['id_butir_'.$module => $butir->{'id_butir_'.$module}, 'tanggapan' => 'Jawaban putaran berikutnya']);
    $record->refresh()->syncStatusFromButir();

    expect($record->fresh()->status)->toBe($status)
        ->and($record->fresh()->putaran_aktif_id)->toBe(2)
        ->and($record->fresh()->batasStatusAwal())->toBeNull();
})->with(['snp', 'djsn'])->with(['proses_tindak_lanjut_belum_sesuai', 'tindak_lanjut_sesuai', 'tidak_dapat_ditindaklanjuti', 'tuntas']);

test('migrasi konversi status lama hanya mengubah status dan penanda penerbitan', function (string $module) {
    $db = DB::connection('mysql_'.$module);
    $record = awalStatusRecord($module, '2026-10-21');
    $butir = awalStatusButir($module, $record);
    $db->table('tb_record')->where('id', $record->id)->update(['status' => 'dalam_proses', 'pernah_terbit' => false]);
    $before = (array) $db->table('tb_record')->where('id', $record->id)->first();
    $this->migration->up();
    $after = (array) $db->table('tb_record')->where('id', $record->id)->first();
    expect($after['status'])->toBe('terbit')->and((bool) $after['pernah_terbit'])->toBeTrue();
    unset($before['status'], $before['pernah_terbit'], $after['status'], $after['pernah_terbit']);
    expect($after)->toEqual($before)
        ->and((new SuratAwalStatusService)->synchronizeModule($module))->toBe(0);
})->with(['snp', 'djsn']);

test('sinkronisasi status tidak menerima modul lain dan rollback menjaga status yang sudah dipakai', function () {
    $this->artisan('workflow:sync-status', ['--module' => ['ragab']])->assertFailed();
    expect(fn () => (new SuratAwalStatusService)->synchronizeModule('ragab'))->toThrow(InvalidArgumentException::class);
    awalStatusRecord('snp', '2026-01-01');
    expect(fn () => $this->migration->down())->toThrow(RuntimeException::class)
        ->and(Schema::connection('mysql_snp')->hasColumn('tb_record', 'pernah_terbit'))->toBeTrue();
});
