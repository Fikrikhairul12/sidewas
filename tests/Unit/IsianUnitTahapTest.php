<?php

use App\Enums\SuratStatus;
use App\Enums\TahapProses;
use App\Models\DjsnTanggapan;
use App\Models\DjsnTindakLanjut;
use App\Models\SnpTanggapan;
use App\Models\SnpTindakLanjut;
use App\Models\SuratTahap;
use App\Services\SuratPutaranService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Tests\TestCase;

uses(TestCase::class);

require_once __DIR__.'/WorkflowTestSetup.php';

beforeEach(function () {
    $this->withoutVite();
    setUpWorkflowTestDatabase();
    config(['sidewas.alur_revisi_enabled' => true, 'sidewas.reviu_putaran_enabled' => true]);
    Storage::fake('public');
    DB::table('tb_unit_kerja')->where('id', 1)->update(['kode_unit' => 'A', 'nama_unit' => 'Unit A']);
    DB::table('tb_unit_kerja')->insert(['id' => 2, 'direktorat_id' => 1, 'kode_unit' => 'B', 'nama_unit' => 'Unit B', 'status' => 'active']);
});

function stageUnitFixture(string $module): array
{
    $butir = workflowButir($module);
    $a = workflowUser('pic_'.$module);
    $colleague = workflowUser('pic_'.$module);
    $b = workflowUser('pic_'.$module);
    $b->unitKerja()->sync([2 => ['status' => 'active']]);
    $picA = $butir->butirPics()->where('jenis_pic', 'utama')->firstOrFail()->id;
    $duplicatePic = $butir->butirPics()->create(['jenis_pic' => 'pendukung', 'unit_kerja_id' => 1])->id;
    $picB = $butir->butirPics()->create(['jenis_pic' => 'pendukung', 'unit_kerja_id' => 2])->id;

    return compact('butir', 'a', 'colleague', 'b', 'picA', 'duplicatePic', 'picB');
}

function stageUnitPayload(array $fixture, int $pic, ?SuratTahap $stage = null, string $text = 'Jawaban unit A'): array
{
    return ['butir_id' => $fixture['butir']->id, 'tahap_id' => $stage?->id, 'butir_pic_id' => $pic, 'isi' => $text, 'deliverables' => 'Hasil unit'];
}

/** Simulate the phase transition that R4 will connect to the reviewer decision. */
function stageUnitOpenFollowUp(array $fixture, int $number): SuratTahap
{
    $record = $fixture['butir']->record->fresh();
    $record->tahapAktif?->update(['archived_at' => now()]);
    $rounds = app(SuratPutaranService::class);
    if ($record->putaranAktif !== null) {
        $rounds->arsipkan($record->putaranAktif);
    }
    $round = $rounds->buka($record, $fixture['a']->id);
    $stage = SuratTahap::factory()->forSurat($record)->tindakLanjut($number)->create(['putaran_asal_id' => $round->id]);
    $record->forceFill(['tahap_aktif_id' => $stage->id, 'status' => SuratStatus::TindakLanjutBelumSesuai->value])->save();

    return $stage;
}

test('R2 satu tanggapan awal per unit termasuk akun dan penugasan PIC yang berbeda', function (string $module) {
    $f = stageUnitFixture($module);
    $route = route($module.'.tanggapan.store', $f['butir']);
    $this->actingAs($f['a'])->post($route, stageUnitPayload($f, $f['picA']))->assertRedirect()->assertSessionHasNoErrors();
    $class = $module === 'snp' ? SnpTanggapan::class : DjsnTanggapan::class;
    $response = $class::firstOrFail();
    expect($response->unit_kerja_id)->toBe(1)->and($response->tahapProses->tahap_proses)->toBe(TahapProses::TanggapanAwal);
    foreach ([$f['picA'], $f['duplicatePic']] as $pic) {
        $this->actingAs($f['colleague'])->post($route, stageUnitPayload($f, $pic))->assertSessionHasErrors('butir_pic_id');
    }
    $page = $this->get(route($module.'.tanggapan.index'))->assertOk()->assertDontSee('data-beri-isian="'.$f['butir']->id.'"', false);
    $state = $page->viewData('entries')->first()['state'];
    expect($state['history'][0]['items'][0]['can_edit'])->toBeTrue();
    $this->patch(route($module.'.tanggapan.update', $response), ['isi' => 'Perubahan rekan satu unit', 'deliverables' => 'Hasil edit'])->assertRedirect()->assertSessionHasNoErrors();
    expect($response->fresh()->created_by)->toBe($f['a']->id)
        ->and($response->fresh()->updated_by)->toBe($f['colleague']->id)
        ->and($response->fresh()->tanggapan)->toBe('Perubahan rekan satu unit');
    $this->actingAs($f['b'])->get(route($module.'.tanggapan.index'))->assertOk()->assertSee('data-beri-isian="'.$f['butir']->id.'"', false);
    $this->post($route, stageUnitPayload($f, $f['picB'], text: 'Jawaban unit B'))->assertSessionHasNoErrors();
    expect($class::count())->toBe(2)->and(DB::connection('mysql_'.$module)->table('tb_isian_unit_tahap')->count())->toBe(2);
    $this->patch(route($module.'.tanggapan.update', $response), ['isi' => 'B mengubah A', 'deliverables' => 'X'])->assertForbidden();
})->with(['snp', 'djsn']);

test('R2 Tanggapan hanya awal dan dua putaran perbaikan memakai Tindak Lanjut dengan arsip terkunci', function (string $module) {
    $f = stageUnitFixture($module);
    $this->actingAs($f['a'])->post(route($module.'.tanggapan.store', $f['butir']), stageUnitPayload($f, $f['picA']))->assertSessionHasNoErrors();
    $responseClass = $module === 'snp' ? SnpTanggapan::class : DjsnTanggapan::class;
    $followClass = $module === 'snp' ? SnpTindakLanjut::class : DjsnTindakLanjut::class;
    $initial = $responseClass::firstOrFail();
    $old = $initial->getRawOriginal();
    $stage1 = stageUnitOpenFollowUp($f, 1);
    $this->get(route($module.'.tindak-lanjut.index'))->assertOk()->assertSee('data-beri-isian="'.$f['butir']->id.'"', false);
    $this->post(route($module.'.tindak-lanjut.store'), stageUnitPayload($f, $f['picA'], $stage1, 'Perbaikan 1'))->assertSessionHasNoErrors();
    $follow1 = $followClass::firstOrFail();
    $this->get(route($module.'.tindak-lanjut.index'))->assertDontSee('data-beri-isian="'.$f['butir']->id.'"', false);
    $this->actingAs($f['colleague'])->post(route($module.'.tindak-lanjut.store'), stageUnitPayload($f, $f['duplicatePic'], $stage1))->assertSessionHasErrors('butir_pic_id');
    $this->actingAs($f['b'])->get(route($module.'.tanggapan.index'))->assertDontSee('data-beri-isian="'.$f['butir']->id.'"', false);
    $this->post(route($module.'.tanggapan.store', $f['butir']), stageUnitPayload($f, $f['picB']))->assertSessionHasErrors('tahap_id');
    $this->patch(route($module.'.tanggapan.update', $initial), ['isi' => 'Ubah awal', 'deliverables' => 'X'])->assertForbidden();
    $stage2 = stageUnitOpenFollowUp($f, 2);
    $this->post(route($module.'.tindak-lanjut.store'), stageUnitPayload($f, $f['picB'], $stage1))->assertSessionHasErrors('tahap_id');
    $this->actingAs($f['a'])->patch(route($module.'.tindak-lanjut.update', $follow1), ['isi' => 'Ubah putaran lama', 'deliverables' => 'X'])->assertForbidden();
    $this->post(route($module.'.tindak-lanjut.store'), stageUnitPayload($f, $f['picA'], $stage2, 'Perbaikan 2'))->assertSessionHasNoErrors();
    expect($initial->fresh()->getRawOriginal())->toBe($old)
        ->and($followClass::orderBy('id')->pluck('tahap_id')->all())->toBe([$stage1->id, $stage2->id])
        ->and($f['butir']->record->fresh()->status)->toBe(SuratStatus::TindakLanjutBelumSesuai->value)
        ->and($responseClass::count())->toBe(1);
})->with(['snp', 'djsn']);

test('R2 pembatasan unit berlaku per butir dan pemeriksaan model tidak dapat dilewati', function (string $module) {
    $f = stageUnitFixture($module);
    $this->actingAs($f['a'])->post(route($module.'.tanggapan.store', $f['butir']), stageUnitPayload($f, $f['picA']))->assertSessionHasNoErrors();
    $class = $module === 'snp' ? SnpTanggapan::class : DjsnTanggapan::class;
    $first = $class::firstOrFail();
    expect(fn () => $class::create(['id_butir_'.$module => $f['butir']->{'id_butir_'.$module}, 'tahap_id' => $first->tahap_id,
        'putaran_id' => $first->putaran_id, 'butir_pic_id' => $f['duplicatePic'], 'unit_kerja_id' => 1, 'tanggapan' => 'Ganda']))->toThrow(LogicException::class);
    $other = $f['butir']->newQuery()->create(['id_'.$module => $f['butir']->{'id_'.$module}, 'butir_'.$module => 'Butir kedua']);
    $pic = $other->butirPics()->create(['jenis_pic' => 'utama', 'unit_kerja_id' => 1]);
    $f2 = $f;
    $f2['butir'] = $other;
    $this->post(route($module.'.tanggapan.store', $other), stageUnitPayload($f2, $pic->id))->assertSessionHasNoErrors();
    expect($class::count())->toBe(2);
    expect(fn () => $first->forceFill(['unit_kerja_id' => 2])->save())->toThrow(LogicException::class)
        ->and(fn () => $first->fresh()->delete())->toThrow(LogicException::class);
})->with(['snp', 'djsn']);

test('R2 riwayat looping yang masih berupa Tanggapan dibaca dan diedit melalui Detail Tindak Lanjut', function (string $module) {
    $f = stageUnitFixture($module);
    $this->actingAs($f['a'])->post(route($module.'.tanggapan.store', $f['butir']), stageUnitPayload($f, $f['picA']))->assertSessionHasNoErrors();
    $stage = stageUnitOpenFollowUp($f, 1);
    $db = DB::connection('mysql_'.$module);
    $id = $db->table('tb_tanggapan')->insertGetId([
        'id_butir_'.$module => $f['butir']->{'id_butir_'.$module}, 'tahap_id' => $stage->id, 'putaran_id' => $stage->putaran_asal_id,
        'unit_kerja_id' => 1, 'butir_pic_id' => $f['picA'], 'tanggapan' => 'Looping lama yang dipetakan R1',
        'deliverables' => 'Hasil lama', 'created_by' => $f['a']->id,
    ]);
    $this->get(route($module.'.tindak-lanjut.index'))->assertOk()->assertSee('Looping lama yang dipetakan R1')->assertDontSee('data-beri-isian="'.$f['butir']->id.'"', false);
    $this->post(route($module.'.tindak-lanjut.store'), stageUnitPayload($f, $f['duplicatePic'], $stage))->assertSessionHasErrors('butir_pic_id');
    $this->patch(route($module.'.tanggapan.update', $id), ['isi' => 'Perbaikan sumber lama', 'deliverables' => 'Hasil edit'])->assertRedirect(route($module.'.tindak-lanjut.index'));
    expect($db->table('tb_tanggapan')->where('id', $id)->value('tanggapan'))->toBe('Perbaikan sumber lama')
        ->and($db->table('tb_tindak_lanjut')->count())->toBe(0);
})->with(['snp', 'djsn']);

test('R2 nomor putaran dan identitas sumber Tindak Lanjut tidak dapat dipalsukan atau diubah', function (string $module) {
    $f = stageUnitFixture($module);
    $this->actingAs($f['a'])->post(route($module.'.tanggapan.store', $f['butir']), stageUnitPayload($f, $f['picA']))->assertSessionHasNoErrors();
    $stage = stageUnitOpenFollowUp($f, 1);
    $class = $module === 'snp' ? SnpTindakLanjut::class : DjsnTindakLanjut::class;
    $attributes = ['id_butir_'.$module => $f['butir']->{'id_butir_'.$module}, 'tahap_id' => $stage->id,
        'putaran_id' => $stage->putaran_asal_id, 'unit_kerja_id' => 1, 'butir_pic_id' => $f['picA'], 'putaran_tl' => 99,
        'tindak_lanjut' => 'Putaran salah', 'deliverables' => 'X'];
    expect(fn () => $class::create($attributes))->toThrow(LogicException::class);
    expect($class::count())->toBe(0);
    $this->post(route($module.'.tindak-lanjut.store'), stageUnitPayload($f, $f['picA'], $stage))->assertSessionHasNoErrors();
    $source = $class::firstOrFail();
    foreach (['putaran_tl' => 99, 'created_by' => $f['b']->id, 'created_at' => now()->subDay(),
        'submitted_after_close_at' => now(), 'submitted_after_compilation_at' => now(), 'changed_after_compilation_at' => now()] as $field => $value) {
        expect(fn () => $source->fresh()->forceFill([$field => $value])->save())->toThrow(LogicException::class);
    }
    expect($source->fresh()->putaran_tl)->toBe(1)->and($class::count())->toBe(1);
})->with(['snp', 'djsn']);

test('R2 unit susulan boleh mengisi setelah kompilasi dan kedua hasil Close tanpa mengubah keputusan', function (string $module, string $decision, bool $followUp) {
    $f = stageUnitFixture($module);
    $this->actingAs($f['a'])->post(route($module.'.tanggapan.store', $f['butir']), stageUnitPayload($f, $f['picA']))->assertSessionHasNoErrors();
    $stage = $followUp ? stageUnitOpenFollowUp($f, 1) : $f['butir']->record->fresh()->tahapAktif;
    if ($followUp) {
        $this->post(route($module.'.tindak-lanjut.store'), stageUnitPayload($f, $f['picA'], $stage))->assertSessionHasNoErrors();
    }
    $db = DB::connection('mysql_'.$module);
    $compilation = $db->table('tb_kompilasi')->insertGetId([
        'id_butir_'.$module => $f['butir']->{'id_butir_'.$module}, 'tahap_id' => $stage->id, 'putaran_id' => $stage->putaran_asal_id,
        'tahap_kompilasi' => $followUp ? 'tindak_lanjut' : 'tanggapan', 'hasil_kompilasi' => 'Bahan final', 'status' => 'dalam_proses_reviu_dewas',
    ]);
    $review = $db->table('tb_review')->insertGetId([
        'id_butir_'.$module => $f['butir']->{'id_butir_'.$module}, 'tahap_id' => $stage->id, 'putaran_id' => $stage->putaran_asal_id,
        'kompilasi_id' => $compilation, 'tahap_review' => $followUp ? 'tindak_lanjut' : 'tanggapan', 'status' => $decision, 'hasil_review' => 'Keputusan final',
    ]);
    $record = $f['butir']->record->fresh();
    $record->forceFill(['status' => $decision, 'closed_at' => now(), 'closed_by' => $f['a']->id, 'closed_review_id' => $review, 'closed_putaran_id' => $stage->putaran_asal_id])->save();
    $before = $record->fresh()->getRawOriginal();
    $compBefore = (array) $db->table('tb_kompilasi')->where('id', $compilation)->first();
    $route = $followUp ? route($module.'.tindak-lanjut.store') : route($module.'.tanggapan.store', $f['butir']);
    $index = route($module.($followUp ? '.tindak-lanjut.index' : '.tanggapan.index'));
    $this->actingAs($f['b'])->get($index)->assertOk()->assertSee('data-beri-isian="'.$f['butir']->id.'"', false);
    $this->post($route, stageUnitPayload($f, $f['picB'], $stage, 'Kelengkapan setelah Close'))->assertSessionHasNoErrors();
    $class = $followUp ? ($module === 'snp' ? SnpTindakLanjut::class : DjsnTindakLanjut::class) : ($module === 'snp' ? SnpTanggapan::class : DjsnTanggapan::class);
    $late = $class::where('unit_kerja_id', 2)->firstOrFail();
    expect($late->submitted_after_close_at)->not->toBeNull()
        ->and($late->submitted_after_compilation_at)->not->toBeNull()
        ->and($record->fresh()->getRawOriginal())->toBe($before)
        ->and((array) $db->table('tb_kompilasi')->where('id', $compilation)->first())->toBe($compBefore);
    $this->post($route, stageUnitPayload($f, $f['picB'], $stage))->assertSessionHasErrors('butir_pic_id');
    $this->get($index)->assertDontSee('data-beri-isian="'.$f['butir']->id.'"', false);
    $this->patch(route($module.($followUp ? '.tindak-lanjut.update' : '.tanggapan.update'), $late), ['isi' => 'Ubah Close', 'deliverables' => 'X'])->assertForbidden();
})->with(['snp', 'djsn'])->with(['tindak_lanjut_sesuai', 'tidak_dapat_ditindaklanjuti'])->with([false, true]);

test('R2 edit setelah kompilasi mempertahankan berkas lama dan penanda susulan', function (string $module) {
    $f = stageUnitFixture($module);
    $payload = stageUnitPayload($f, $f['picA']);
    $payload['dokumen'] = UploadedFile::fake()->create('asli.pdf', 5, 'application/pdf');
    $this->actingAs($f['a'])->post(route($module.'.tanggapan.store', $f['butir']), $payload)->assertSessionHasNoErrors();
    $class = $module === 'snp' ? SnpTanggapan::class : DjsnTanggapan::class;
    $response = $class::firstOrFail();
    $oldFile = $response->dokumen;
    DB::connection('mysql_'.$module)->table('tb_kompilasi')->insert([
        'id_butir_'.$module => $f['butir']->{'id_butir_'.$module}, 'tahap_id' => $response->tahap_id,
        'putaran_id' => $response->putaran_id, 'hasil_kompilasi' => 'Kompilasi A', 'status' => 'dalam_proses_reviu_dewas',
    ]);
    $this->patch(route($module.'.tanggapan.update', $response), ['isi' => 'Edit sesudah kompilasi', 'deliverables' => 'Hasil baru',
        'dokumen' => UploadedFile::fake()->create('pengganti.pdf', 5, 'application/pdf')])->assertSessionHasNoErrors();
    Storage::disk('public')->assertExists($oldFile);
    expect($response->fresh()->changed_after_compilation_at)->not->toBeNull();
    $this->actingAs($f['b'])->post(route($module.'.tanggapan.store', $f['butir']), stageUnitPayload($f, $f['picB']))->assertSessionHasNoErrors();
    expect($class::where('unit_kerja_id', 2)->firstOrFail()->submitted_after_compilation_at)->not->toBeNull();
})->with(['snp', 'djsn']);

test('R2 tetap mengikuti hak akses unit dan mencegah isian sebelum tahap TL dibuka', function (string $module) {
    $f = stageUnitFixture($module);
    $viewer = workflowUser('viewer_'.$module);
    $this->actingAs($viewer)->get(route($module.'.tanggapan.index'))->assertOk()->assertDontSee('data-beri-isian=', false);
    $this->post(route($module.'.tanggapan.store', $f['butir']), stageUnitPayload($f, $f['picA']))->assertForbidden();
    $this->actingAs($f['a'])->post(route($module.'.tanggapan.store', $f['butir']), stageUnitPayload($f, $f['picB']))->assertForbidden();
    $this->post(route($module.'.tindak-lanjut.store'), stageUnitPayload($f, $f['picA']))->assertSessionHasErrors('tahap_id');
    $this->get(route($module.'.tindak-lanjut.index'))->assertOk()->assertSee('Belum ada putaran Tindak Lanjut');
    $foreign = workflowUser($module === 'snp' ? 'pic_djsn' : 'pic_snp');
    $this->actingAs($foreign)->get(route($module.'.tindak-lanjut.index'))->assertForbidden();
})->with(['snp', 'djsn']);

test('R2 menu TL dipulihkan hanya pada mode revisi dan alur berjalan tetap tersedia', function () {
    $user = workflowUser('super_admin');
    $this->actingAs($user);
    foreach (['snp', 'djsn'] as $module) {
        $this->get(route($module.'.tanggapan.index'))->assertOk()->assertSee('href="'.route($module.'.tindak-lanjut.index').'"', false);
    }
    config(['sidewas.alur_revisi_enabled' => false]);
    foreach (['snp', 'djsn'] as $module) {
        $this->get(route($module.'.tanggapan.index'))->assertOk()->assertDontSee('href="'.route($module.'.tindak-lanjut.index').'"', false);
        $this->get(route($module.'.tindak-lanjut.index'))->assertRedirect(route($module.'.tanggapan.index'));
    }
});

test('R2 filter isian mengikuti unit dan pencarian tidak mencampur tahap awal dengan TL', function (string $module) {
    $f = stageUnitFixture($module);
    $this->actingAs($f['a'])->post(route($module.'.tanggapan.store', $f['butir']), stageUnitPayload($f, $f['picA'], text: 'KataKhususAwal'))->assertSessionHasNoErrors();
    $this->get(route($module.'.tanggapan.index', ['status' => 'sudah']))->assertViewHas('entries', fn ($rows): bool => $rows->total() === 1);
    $this->actingAs($f['b'])->get(route($module.'.tanggapan.index', ['status' => 'belum']))->assertViewHas('entries', fn ($rows): bool => $rows->total() === 1);
    $this->get(route($module.'.tanggapan.index', ['status' => 'sudah']))->assertViewHas('entries', fn ($rows): bool => $rows->total() === 0);
    $stage = stageUnitOpenFollowUp($f, 1);
    $this->actingAs($f['a'])->post(route($module.'.tindak-lanjut.store'), stageUnitPayload($f, $f['picA'], $stage, 'KataKhususPerbaikan'))->assertSessionHasNoErrors();
    $this->get(route($module.'.tindak-lanjut.index', ['status' => 'sudah']))->assertViewHas('entries', fn ($rows): bool => $rows->total() === 1);
    $this->get(route($module.'.tanggapan.index', ['keyword' => 'KataKhususPerbaikan']))->assertViewHas('entries', fn ($rows): bool => $rows->total() === 0);
    $this->get(route($module.'.tindak-lanjut.index', ['keyword' => 'KataKhususPerbaikan']))->assertViewHas('entries', fn ($rows): bool => $rows->total() === 1);
    $this->actingAs($f['b'])->get(route($module.'.tindak-lanjut.index', ['status' => 'belum']))->assertViewHas('entries', fn ($rows): bool => $rows->total() === 1);
})->with(['snp', 'djsn']);

test('R2 sumber dengan unit tak terbukti tetap terbaca dan menunggu rekonsiliasi', function (string $module) {
    $f = stageUnitFixture($module);
    $this->actingAs($f['a'])->post(route($module.'.tanggapan.store', $f['butir']), stageUnitPayload($f, $f['picA']))->assertSessionHasNoErrors();
    $db = DB::connection('mysql_'.$module);
    $db->table('tb_tanggapan')->update(['unit_kerja_id' => null, 'butir_pic_id' => null]);
    $page = $this->actingAs($f['b'])->get(route($module.'.tanggapan.index'))->assertOk()->assertSee('Riwayat perlu rekonsiliasi')
        ->assertDontSee('data-beri-isian=', false);
    expect($page->viewData('entries')->first()['state']['history'][0]['items'][0]['isi'])->toBe('Jawaban unit A');
    $this->post(route($module.'.tanggapan.store', $f['butir']), stageUnitPayload($f, $f['picB']))->assertSessionHasErrors('tahap_id');
    expect($db->table('tb_tanggapan')->count())->toBe(1);
})->with(['snp', 'djsn']);

test('R2 penguncian keputusan reviu tetap membolehkan unit yang belum mengisi melengkapi tahap aktif', function (string $module) {
    $f = stageUnitFixture($module);
    $this->actingAs($f['a'])->post(route($module.'.tanggapan.store', $f['butir']), stageUnitPayload($f, $f['picA']))->assertSessionHasNoErrors();
    $class = $module === 'snp' ? SnpTanggapan::class : DjsnTanggapan::class;
    $response = $class::firstOrFail();
    DB::connection('mysql_'.$module)->table('tb_review')->insert([
        'id_butir_'.$module => $f['butir']->{'id_butir_'.$module}, 'tahap_id' => $response->tahap_id,
        'putaran_id' => $response->putaran_id, 'hasil_review' => 'Catatan reviu tersimpan', 'status' => 'belum_ditanggapi',
    ]);
    $this->patch(route($module.'.tanggapan.update', $response), ['isi' => 'Tidak boleh', 'deliverables' => 'X'])->assertForbidden();
    $this->actingAs($f['b'])->post(route($module.'.tanggapan.store', $f['butir']), stageUnitPayload($f, $f['picB']))->assertSessionHasNoErrors();
    expect($class::count())->toBe(2);
})->with(['snp', 'djsn']);

test('R2 browser menampilkan tombol unit yang benar dan edit hanya melalui Detail', function (string $module) {
    $f = stageUnitFixture($module);
    $this->actingAs($f['a']);
    $initialRoute = route($module.'.tanggapan.index');
    $tlRoute = route($module.'.tindak-lanjut.index');
    $pages = [['mode' => 'pending', 'canCreate' => true, 'canEdit' => false, 'butirId' => $f['butir']->id, 'picId' => $f['picA'],
        'stageId' => $f['butir']->record->fresh()->tahapanProses->firstWhere('nomor_putaran', 0)?->id,
        'html' => $this->get($initialRoute)->assertOk()->getContent()]];
    $this->post(route($module.'.tanggapan.store', $f['butir']), stageUnitPayload($f, $f['picA']))->assertSessionHasNoErrors();
    $responseClass = $module === 'snp' ? SnpTanggapan::class : DjsnTanggapan::class;
    $source = $responseClass::firstOrFail();
    $pages[] = ['mode' => 'answered', 'canCreate' => false, 'canEdit' => true, 'content' => 'Jawaban unit A',
        'editPath' => '/'.$module.'/tanggapan/'.$source->id, 'html' => $this->get($initialRoute)->assertOk()->getContent()];
    $stage1 = stageUnitOpenFollowUp($f, 1);
    $pages[] = ['mode' => 'follow-up', 'canCreate' => true, 'canEdit' => false, 'butirId' => $f['butir']->id,
        'stageId' => $stage1->id, 'picId' => $f['picA'], 'html' => $this->get($tlRoute)->assertOk()->getContent()];
    $this->post(route($module.'.tindak-lanjut.store'), stageUnitPayload($f, $f['picA'], $stage1, 'TL 1 unit A'))->assertSessionHasNoErrors();
    $this->actingAs($f['b'])->post(route($module.'.tindak-lanjut.store'), stageUnitPayload($f, $f['picB'], $stage1, 'TL 1 unit B'))->assertSessionHasNoErrors();
    $stage2 = stageUnitOpenFollowUp($f, 2);
    $this->actingAs($f['a'])->post(route($module.'.tindak-lanjut.store'), stageUnitPayload($f, $f['picA'], $stage2, 'TL 2 unit A'))->assertSessionHasNoErrors();
    $followClass = $module === 'snp' ? SnpTindakLanjut::class : DjsnTindakLanjut::class;
    $follow = $followClass::where('tahap_id', $stage2->id)->firstOrFail();
    $pages[] = ['mode' => 'latest', 'canCreate' => false, 'canEdit' => true, 'content' => 'TL 2 unit A', 'oldStageId' => $stage1->id,
        'editPath' => '/'.$module.'/tindak-lanjut/'.$follow->id, 'html' => $this->get($tlRoute)->assertOk()->getContent()];
    $record = $f['butir']->record->fresh();
    $record->forceFill(['status' => 'tindak_lanjut_sesuai', 'closed_at' => now(), 'closed_by' => $f['a']->id, 'closed_putaran_id' => $stage2->putaran_asal_id])->save();
    $this->actingAs($f['b'])->post(route($module.'.tindak-lanjut.store'), stageUnitPayload($f, $f['picB'], $stage2, 'Kelengkapan unit B'))->assertSessionHasNoErrors();
    $pages[] = ['mode' => 'late', 'canCreate' => false, 'canEdit' => false, 'html' => $this->get($tlRoute)->assertOk()->getContent()];
    $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
    $process = new Process(['node', base_path('tests/Unit/IsianUnitTahap.browser.mjs')], base_path());
    $screenshotDirectory = getenv('SIDEWAS_R2_SCREENSHOT_DIR');
    $process->setInput(json_encode(['pages' => $pages, 'css' => $manifest['resources/css/app.css']['file'],
        'js' => $manifest['resources/js/app.js']['file'], 'screenshot' => $screenshotDirectory ? $screenshotDirectory.'/r2-'.$module.'.png' : null]));
    $process->setTimeout(60)->run();
    expect($process->isSuccessful())->toBeTrue($process->getOutput().$process->getErrorOutput());
})->with(['snp', 'djsn']);
