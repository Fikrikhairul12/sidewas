<?php

use App\Enums\TahapProses;
use App\Models\DeleteRequest;
use App\Models\KompilasiBahan;
use App\Models\SuratTahap;
use App\Services\SnpButirContent;
use App\Services\SuratPutaranService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

uses(TestCase::class);

require_once __DIR__.'/WorkflowTestSetup.php';

beforeEach(function () {
    $this->withoutVite();
    setUpWorkflowTestDatabase();
    config(['sidewas.alur_revisi_enabled' => true, 'sidewas.reviu_putaran_enabled' => true]);
    Storage::fake('public');
});

function compilationFixture(string $module): array
{
    $butir = workflowButir($module);
    $actor = workflowUser('super_admin');
    $users = [];
    $pics = [];
    foreach ([1 => 'A', 2 => 'B', 3 => 'C'] as $id => $name) {
        DB::table('tb_unit_kerja')->updateOrInsert(['id' => $id], ['nama_unit' => 'Unit '.$name, 'kode_unit' => $name, 'direktorat_id' => 1, 'status' => 'active']);
        $users[$id] = workflowUser('pic_'.$module);
        $users[$id]->unitKerja()->sync([$id => ['status' => 'active']]);
        $pics[$id] = $butir->butirPics()->firstOrCreate(['jenis_pic' => $id === 1 ? 'utama' : 'pendukung', 'unit_kerja_id' => $id])->id;
    }

    return compact('butir', 'actor', 'users', 'pics', 'module');
}

function compilationRespond(array $f, int $unit, ?SuratTahap $stage = null, ?array $extra = null): void
{
    $followUp = $stage?->tahap_proses === TahapProses::TindakLanjut;
    test()->actingAs($f['users'][$unit])->post($followUp ? route($f['module'].'.tindak-lanjut.store') : route($f['module'].'.tanggapan.store', $f['butir']),
        array_merge(['butir_id' => $f['butir']->id, 'tahap_id' => $stage?->id, 'butir_pic_id' => $f['pics'][$unit],
            'isi' => 'Jawaban Unit '.chr(64 + $unit), 'deliverables' => 'Hasil Unit '.chr(64 + $unit)], $extra ?? []))
        ->assertRedirect()->assertSessionHasNoErrors();
}

function compilationSave(array $f, ?SuratTahap $stage = null, array $extra = []): Model
{
    test()->actingAs($f['actor'])->post(route($f['module'].'.kompilasi.store', $f['butir']), array_merge([
        'tahap_kompilasi' => $stage?->tahap_proses === TahapProses::TindakLanjut ? 'tindak_lanjut' : 'tanggapan',
        'tahap_id' => $stage?->id, 'putaran_id' => $stage?->putaran_asal_id,
        'hasil_kompilasi' => 'Hasil kompilasi resmi', 'deliverables' => 'Deliverables resmi',
    ], $extra))->assertRedirect()->assertSessionHasNoErrors();

    return $f['butir']->kompilasis()->latest('id')->firstOrFail();
}

/** Simulate only the phase opening that R4 will wire to the reviewer. */
function compilationOpenFollowUp(array $f, int $number): SuratTahap
{
    $record = $f['butir']->record->fresh();
    $record->tahapAktif?->update(['archived_at' => now()]);
    if ($record->putaranAktif) {
        app(SuratPutaranService::class)->arsipkan($record->putaranAktif, $f['actor']->id);
    }
    $round = app(SuratPutaranService::class)->buka($record, $f['actor']->id);
    $stage = SuratTahap::factory()->forSurat($record)->tindakLanjut($number)->create(['putaran_asal_id' => $round->id]);
    $record->forceFill(['tahap_aktif_id' => $stage->id, 'status' => 'proses_tindak_lanjut_belum_sesuai'])->save();

    return $stage;
}

test('R3 A dan B masuk salinan sedangkan C susulan hanya masuk aplikasi', function (string $module) {
    $f = compilationFixture($module);
    compilationRespond($f, 1);
    compilationRespond($f, 2);
    $compilation = compilationSave($f);
    $context = $compilation->salinan_konteks;
    expect($compilation->status_bahan)->toBe('terverifikasi')->and($compilation->bahan_dicatat_at)->not->toBeNull()
        ->and($context['jumlah_bahan'])->toBe(2)->and(array_column($context['kelengkapan_unit'], 'terisi'))->toBe([true, true, false]);
    $materials = $compilation->bahan()->orderBy('unit_kerja_id')->get();
    expect($materials->pluck('unit_kerja_id')->all())->toBe([1, 2])
        ->and($materials[0]->salinan['isi'])->toBe('Jawaban Unit A')
        ->and($materials[0]->salinan['pengisi']['id'])->toBe($f['users'][1]->id)
        ->and($materials[0]->salinan['unit']['nama'])->toBe('Unit A')
        ->and($materials[0]->salinan['versi_sumber'])->toHaveLength(64);
    $review = $f['butir']->reviews()->firstOrFail();
    expect($review->kompilasi_id)->toBe($compilation->id)->and($review->tahap_id)->toBe($compilation->tahap_id);
    compilationRespond($f, 3);
    $late = $f['butir']->tanggapanUnit()->where('unit_kerja_id', 3)->firstOrFail();
    expect($late->submitted_after_compilation_at)->not->toBeNull()
        ->and($compilation->fresh()->salinan_konteks)->toBe($context)->and($compilation->bahan()->count())->toBe(2);
    $this->get(route($module.'.tanggapan.index'))->assertSee('Tidak termasuk kompilasi');
    $this->actingAs($f['actor'])->postJson(route($module.'.kompilasi.store', $f['butir']), [
        'tahap_kompilasi' => 'tanggapan', 'hasil_kompilasi' => 'Percobaan timpa',
    ])->assertUnprocessable();
    expect($f['butir']->kompilasis()->count())->toBe(1)->and($f['butir']->reviews()->count())->toBe(1);
})->with(['snp', 'djsn']);

test('R3 kompilasi nol atau sebagian isian menyimpan kelengkapan saat itu', function (string $module, int $count) {
    $f = compilationFixture($module);
    for ($i = 1; $i <= $count; $i++) {
        compilationRespond($f, $i);
    }
    $compilation = compilationSave($f);
    expect($compilation->salinan_konteks['jumlah_bahan'])->toBe($count)
        ->and($compilation->bahan()->count())->toBe($count)
        ->and(count(array_filter(array_column($compilation->salinan_konteks['kelengkapan_unit'], 'terisi'))))->toBe($count);
    compilationRespond($f, 3);
    expect($f['butir']->tanggapanUnit()->where('unit_kerja_id', 3)->firstOrFail()->submitted_after_compilation_at)->not->toBeNull();
})->with(['snp', 'djsn'])->with([0, 1]);

test('R3 edit sumber dan pergantian dokumen gambar serta penugasan tidak mengubah salinan', function (string $module) {
    $f = compilationFixture($module);
    $image = (string) Str::uuid().'.png';
    $imagePath = $module.'-images/'.$f['butir']->record->id.'/'.$image;
    Storage::disk('local')->put($imagePath, UploadedFile::fake()->image('bukti.png', 20, 20)->getContent());
    $url = route($module.'.butir-images.show', ['record' => $f['butir']->record->id, 'filename' => $image], false);
    $content = SnpButirContent::PREFIX.'<p><strong>Bahan asli</strong><img src="'.$url.'" data-width="50"></p>';
    $f['butir']->update(['butir_'.$module => $content]);
    compilationRespond($f, 1, extra: ['isi' => $content, 'dokumen' => UploadedFile::fake()->create('asal.pdf', 5, 'application/pdf')]);
    $compilation = compilationSave($f, extra: ['hasil_kompilasi' => $content, 'dokumen' => UploadedFile::fake()->create('kompilasi.pdf', 5, 'application/pdf')]);
    $source = $f['butir']->tanggapanUnit()->firstOrFail();
    $material = $compilation->bahan()->firstOrFail();
    $frozen = $material->salinan;
    $context = $compilation->salinan_konteks;
    expect($frozen['gambar'][0]['path'])->not->toBe($imagePath)
        ->and($frozen['isi'])->toContain('<strong>Bahan asli</strong>')->not->toContain($url)
        ->and($frozen['dokumen']['path'])->not->toBe($source->dokumen);
    $this->actingAs($f['users'][1])->patch(route($module.'.tanggapan.update', $source), [
        'isi' => 'Sumber terbaru', 'deliverables' => 'Hasil baru', 'dokumen' => UploadedFile::fake()->create('baru.pdf', 5, 'application/pdf'),
    ])->assertSessionHasNoErrors();
    expect($source->fresh()->changed_after_compilation_at)->not->toBeNull();
    Storage::disk('public')->assertExists($source->dokumen);
    Storage::disk('public')->delete($source->dokumen);
    Storage::disk('local')->delete($imagePath);
    DB::table('tb_unit_kerja')->where('id', 1)->update(['nama_unit' => 'Nama baru']);
    $f['butir']->update(['butir_'.$module => 'Butir terbaru']);
    foreach ([$frozen['dokumen'], $context['kompilasi']['dokumen'], $frozen['gambar'][0]] as $copy) {
        Storage::disk($copy['disk'])->assertExists($copy['path']);
        expect(hash('sha256', Storage::disk($copy['disk'])->get($copy['path'])))->toBe($copy['sha256']);
    }
    expect($material->fresh()->salinan)->toBe($frozen)->and($compilation->fresh()->salinan_konteks)->toBe($context);
})->with(['snp', 'djsn']);

test('R3 salinan kompilasi serta daftar bahan termasuk kompilasi kosong tidak boleh ditimpa', function (string $module, int $count) {
    $f = compilationFixture($module);
    Storage::disk('public')->put('dokumen/surat-dipertahankan.pdf', 'Berkas surat');
    $f['butir']->record->update(['dokumen' => 'dokumen/surat-dipertahankan.pdf']);
    if ($count) {
        compilationRespond($f, 1);
    }
    $compilation = compilationSave($f);
    foreach (['hasil_kompilasi' => 'Ubah', 'deliverables' => 'Ubah', 'salinan_konteks' => ['jumlah_bahan' => 99],
        'kunci_salinan' => null, 'status_bahan' => 'perlu_rekonsiliasi', 'bahan_dicatat_at' => null] as $field => $value) {
        expect(fn () => $compilation->fresh()->forceFill([$field => $value])->save())->toThrow(LogicException::class);
    }
    expect(fn () => $compilation->fresh()->delete())->toThrow(LogicException::class);
    compilationRespond($f, 3);
    $late = $f['butir']->tanggapanUnit()->where('unit_kerja_id', 3)->firstOrFail();
    expect(fn () => KompilasiBahan::factory()->forSumber($compilation, $late)->create())->toThrow(LogicException::class);
    $this->actingAs($f['actor'])->delete(route($module.'.perekaman.destroy.request', $f['butir']->record))->assertUnprocessable();
    $deleteRequest = DeleteRequest::create([
        'type_code' => $module, 'database_name' => 'sidewas_'.$module, 'table_name' => 'tb_record',
        'record_key' => $f['butir']->record->{'id_'.$module}, 'record_label' => 'Surat uji',
        'reason' => 'Permintaan hapus lama', 'requested_by' => $f['actor']->id, 'status' => 'pending_super_admin_approval',
    ]);
    $this->patchJson(route('administrasi.pengajuan.approve', $deleteRequest))->assertUnprocessable();
    expect($deleteRequest->fresh()->status)->toBe('pending_super_admin_approval');
    Storage::disk('public')->assertExists('dokumen/surat-dipertahankan.pdf');
    expect($compilation->fresh()->salinan_konteks['jumlah_bahan'])->toBe($count);
})->with(['snp', 'djsn'])->with([0, 1]);

test('R3 bahan Tindak Lanjut hanya mengambil putaran yang dikompilasi termasuk sumber looping lama', function (string $module) {
    $f = compilationFixture($module);
    compilationRespond($f, 1);
    $initial = compilationSave($f);
    $stage1 = compilationOpenFollowUp($f, 1);
    compilationRespond($f, 1, $stage1, ['isi' => 'Perbaikan pertama']);
    $first = compilationSave($f, $stage1);
    $stage2 = compilationOpenFollowUp($f, 2);
    $db = DB::connection('mysql_'.$module);
    $db->table('tb_tanggapan')->insert([
        'id_butir_'.$module => $f['butir']->{'id_butir_'.$module}, 'tahap_id' => $stage2->id, 'putaran_id' => $stage2->putaran_asal_id,
        'butir_pic_id' => $f['pics'][1], 'unit_kerja_id' => 1, 'tanggapan' => 'Perbaikan dari sumber lama', 'deliverables' => 'Hasil lama', 'created_by' => $f['users'][1]->id,
    ]);
    $second = compilationSave($f, $stage2);
    expect($initial->bahan()->firstOrFail()->salinan['isi'])->toBe('Jawaban Unit A')
        ->and($first->bahan()->firstOrFail()->salinan['isi'])->toBe('Perbaikan pertama')
        ->and($second->bahan()->firstOrFail()->salinan['isi'])->toBe('Perbaikan dari sumber lama')
        ->and($second->bahan()->firstOrFail()->jenis_sumber)->toBe('tb_tanggapan')
        ->and($second->salinan_konteks['tahap']['nomor_putaran'])->toBe(2)
        ->and($second->reviews()->firstOrFail()->tahap_review)->toBe('tindak_lanjut');
    $this->actingAs($f['actor'])->postJson(route($module.'.kompilasi.store', $f['butir']), [
        'tahap_id' => $stage1->id, 'putaran_id' => $stage1->putaran_asal_id,
        'tahap_kompilasi' => 'tindak_lanjut', 'hasil_kompilasi' => 'Stale',
    ])->assertUnprocessable();
})->with(['snp', 'djsn']);

test('R3 kegagalan penyimpanan bahan mengembalikan seluruh transaksi dan membersihkan berkas baru', function (string $module) {
    $f = compilationFixture($module);
    compilationRespond($f, 1, extra: ['dokumen' => UploadedFile::fake()->create('sumber.pdf', 5, 'application/pdf')]);
    $source = $f['butir']->tanggapanUnit()->firstOrFail();
    $beforeFiles = Storage::disk('public')->allFiles();
    KompilasiBahan::creating(function (): void {
        throw new RuntimeException('Kegagalan bahan untuk pengujian');
    });
    try {
        $this->withoutExceptionHandling()->actingAs($f['actor']);
        expect(fn () => $this->post(route($module.'.kompilasi.store', $f['butir']), [
            'tahap_kompilasi' => 'tanggapan', 'hasil_kompilasi' => 'Kompilasi dibatalkan',
            'dokumen' => UploadedFile::fake()->create('unggahan.pdf', 5, 'application/pdf'),
        ]))->toThrow(RuntimeException::class, 'Kegagalan bahan untuk pengujian');
    } finally {
        KompilasiBahan::flushEventListeners();
        KompilasiBahan::clearBootedModels();
    }
    expect($f['butir']->kompilasis()->count())->toBe(0)->and($f['butir']->reviews()->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe($beforeFiles)
        ->and($source->fresh()->submitted_after_compilation_at)->toBeNull();
})->with(['snp', 'djsn']);

test('R3 hak kompilasi dan validasi bahan lama tetap dipertahankan', function (string $module) {
    $f = compilationFixture($module);
    $data = ['tahap_kompilasi' => 'tanggapan', 'hasil_kompilasi' => 'Isi'];
    $this->actingAs($f['users'][1])->postJson(route($module.'.kompilasi.store', $f['butir']), $data)->assertForbidden();
    compilationRespond($f, 1);
    DB::connection('mysql_'.$module)->table('tb_tanggapan')->update(['unit_kerja_id' => null, 'butir_pic_id' => null]);
    $this->actingAs($f['actor'])->postJson(route($module.'.kompilasi.store', $f['butir']), $data)->assertUnprocessable();
    expect($f['butir']->kompilasis()->count())->toBe(0);
})->with(['snp', 'djsn']);

test('R3 gambar kompilasi mengikuti tahap dan putaran serta terkunci sesudah simpan', function (string $module) {
    $f = compilationFixture($module);
    $this->actingAs($f['actor']);
    $upload = $this->postJson(route($module.'.kompilasi.images.store', $f['butir']), [
        'tahap_kompilasi' => 'tanggapan', 'image' => UploadedFile::fake()->image('awal.png'),
    ])->assertCreated();
    expect($f['butir']->record->fresh()->tahap_aktif_id)->toBeNull();
    $compilation = compilationSave($f, extra: ['hasil_kompilasi' => SnpButirContent::PREFIX.'<p><img src="'.$upload->json('url').'" data-width="75"></p>']);
    $this->postJson(route($module.'.kompilasi.images.store', $f['butir']), [
        'tahap_kompilasi' => 'tanggapan', 'image' => UploadedFile::fake()->image('terkunci.png'),
    ])->assertUnprocessable();
    $stage = compilationOpenFollowUp($f, 1);
    $this->postJson(route($module.'.kompilasi.images.store', $f['butir']), [
        'tahap_id' => $compilation->tahap_id, 'tahap_kompilasi' => 'tanggapan', 'image' => UploadedFile::fake()->image('salah.png'),
    ])->assertUnprocessable();
    $this->postJson(route($module.'.kompilasi.images.store', $f['butir']), [
        'tahap_id' => $stage->id, 'putaran_id' => $stage->putaran_asal_id,
        'tahap_kompilasi' => 'tindak_lanjut', 'image' => UploadedFile::fake()->image('perbaikan.png'),
    ])->assertCreated();
})->with(['snp', 'djsn']);

test('R3 halaman dan formulir browser mengirim konteks awal dan Tindak Lanjut yang tepat', function (string $module) {
    $f = compilationFixture($module);
    $this->actingAs($f['actor']);
    $action = '/'.$module.'/kompilasi/'.$f['butir']->id;
    $pages = [['kind' => 'kompilasi', 'action' => $action, 'stageType' => 'tanggapan', 'stageId' => null, 'roundId' => null,
        'html' => $this->get(route($module.'.kompilasi.index'))->assertOk()->getContent()]];
    compilationSave($f);
    $stage = compilationOpenFollowUp($f, 1);
    compilationRespond($f, 1, $stage, ['isi' => 'Bahan Tindak Lanjut putaran aktif']);
    $this->actingAs($f['actor']);
    $response = $this->get(route($module.'.kompilasi.index'))->assertOk()->assertSee('Tindak Lanjut Putaran 1');
    expect($response->viewData('previewBahan')[$f['butir']->id]['responses'][0]['content'])->toBe('Bahan Tindak Lanjut putaran aktif');
    $pages[] = ['kind' => 'kompilasi', 'action' => $action, 'stageType' => 'tindak_lanjut', 'stageId' => $stage->id, 'roundId' => $stage->putaran_asal_id,
        'html' => $response->getContent()];
    $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
    $process = new Process(['node', base_path('tests/Unit/ReviuPutaran.browser.mjs')], base_path());
    $process->setInput(json_encode(['pages' => $pages, 'css' => $manifest['resources/css/app.css']['file'], 'js' => $manifest['resources/js/app.js']['file']]));
    $process->setTimeout(60)->run();
    expect($process->isSuccessful())->toBeTrue($process->getOutput().$process->getErrorOutput());
})->with(['snp', 'djsn']);

test('R3 kelengkapan sesudah kedua hasil Close tidak mengubah salinan terakhir', function (string $module, string $decision, bool $followUp) {
    $f = compilationFixture($module);
    compilationRespond($f, 1);
    $stage = $followUp ? compilationOpenFollowUp($f, 1) : $f['butir']->record->fresh()->tahapAktif;
    if ($followUp) {
        compilationRespond($f, 1, $stage);
    }
    $compilation = compilationSave($f, $stage);
    $context = $compilation->salinan_konteks;
    $review = $compilation->reviews()->firstOrFail();
    $record = $f['butir']->record->fresh();
    $record->forceFill(['status' => $decision, 'closed_at' => now(), 'closed_by' => $f['actor']->id,
        'closed_review_id' => $review->id, 'closed_putaran_id' => $stage->putaran_asal_id])->save();
    $decisionBefore = $record->fresh()->getRawOriginal();
    compilationRespond($f, 3, $stage);
    $source = $stage->relatedClass($followUp ? 'TindakLanjut' : 'Tanggapan')::where('unit_kerja_id', 3)->firstOrFail();
    expect($source->submitted_after_close_at)->not->toBeNull()->and($source->submitted_after_compilation_at)->not->toBeNull()
        ->and($compilation->fresh()->salinan_konteks)->toBe($context)->and($compilation->bahan()->count())->toBe(1)
        ->and($record->fresh()->getRawOriginal())->toBe($decisionBefore);
})->with(['snp', 'djsn'])->with(['tindak_lanjut_sesuai', 'tidak_dapat_ditindaklanjuti'])->with([false, true]);

test('R3 berkas hilang membatalkan salinan dan tidak meninggalkan sebagian bahan', function (string $module) {
    $f = compilationFixture($module);
    compilationRespond($f, 1, extra: ['dokumen' => UploadedFile::fake()->create('ada.pdf', 2, 'application/pdf')]);
    compilationRespond($f, 2, extra: ['dokumen' => UploadedFile::fake()->create('hilang.pdf', 2, 'application/pdf')]);
    $missing = $f['butir']->tanggapanUnit()->where('unit_kerja_id', 2)->firstOrFail();
    Storage::disk('public')->delete($missing->dokumen);
    $filesBefore = Storage::disk('public')->allFiles();
    $this->actingAs($f['actor'])->postJson(route($module.'.kompilasi.store', $f['butir']), [
        'tahap_kompilasi' => 'tanggapan', 'hasil_kompilasi' => 'Gagal',
    ])->assertUnprocessable()->assertJsonValidationErrors('hasil_kompilasi');
    expect($f['butir']->kompilasis()->count())->toBe(0)->and($f['butir']->reviews()->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe($filesBefore);
})->with(['snp', 'djsn']);

test('R3 migrasi tidak menebak bahan historis dan rollback menolak salinan yang sudah digunakan', function (string $module) {
    $f = compilationFixture($module);
    $db = DB::connection('mysql_'.$module);
    $legacyId = $db->table('tb_kompilasi')->insertGetId(['id_butir_'.$module => $f['butir']->{'id_butir_'.$module},
        'hasil_kompilasi' => 'Hasil lama', 'status' => 'dalam_proses_reviu_dewas']);
    $migration = require database_path('migrations/2026_10_23_131534_add_snp_djsn_compilation_snapshots.php');
    $migration->up();
    $migration->up();
    expect($db->table('tb_kompilasi')->where('id', $legacyId)->value('salinan_konteks'))->toBeNull();
    $this->actingAs($f['actor'])->postJson(route($module.'.kompilasi.store', $f['butir']), [
        'tahap_kompilasi' => 'tanggapan', 'hasil_kompilasi' => 'Bahan tebakan',
    ])->assertUnprocessable();
    $second = compilationFixture($module);
    compilationSave($second);
    expect(fn () => $migration->down())->toThrow(RuntimeException::class, 'Rollback R3 dibatalkan');
})->with(['snp', 'djsn']);
