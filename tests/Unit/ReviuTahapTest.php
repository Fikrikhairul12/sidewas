<?php

use App\Enums\TahapProses;
use App\Models\LogActivity;
use App\Models\SuratTahap;
use App\Services\ReviuBahanPreviewService;
use App\Services\ReviuPutaranService;
use App\Services\SnpButirContent;
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

function reviewStageFixture(string $module): array
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

    return compact('module', 'butir', 'actor', 'users', 'pics');
}

function reviewStageInput(array $f, int $unit, ?SuratTahap $stage = null, string $body = 'Jawaban resmi'): Model
{
    $followUp = $stage?->tahap_proses === TahapProses::TindakLanjut;
    test()->actingAs($f['users'][$unit])->post($followUp ? route($f['module'].'.tindak-lanjut.store') : route($f['module'].'.tanggapan.store', $f['butir']), [
        'butir_id' => $f['butir']->id, 'tahap_id' => $stage?->id, 'butir_pic_id' => $f['pics'][$unit], 'isi' => $body, 'deliverables' => 'Hasil unit',
    ])->assertRedirect()->assertSessionHasNoErrors();

    return ($followUp ? $f['butir']->tindakLanjuts() : $f['butir']->tanggapanUnit())->latest('id')->firstOrFail();
}

function reviewStageCompile(array $f, ?SuratTahap $stage = null, array $extra = []): Model
{
    test()->actingAs($f['actor'])->post(route($f['module'].'.kompilasi.store', $f['butir']), array_merge([
        'tahap_kompilasi' => $stage?->tahap_proses === TahapProses::TindakLanjut ? 'tindak_lanjut' : 'tanggapan',
        'tahap_id' => $stage?->id, 'putaran_id' => $stage?->putaran_asal_id, 'hasil_kompilasi' => 'Kompilasi resmi',
    ], $extra))->assertRedirect()->assertSessionHasNoErrors();

    return $f['butir']->reviews()->latest('id')->firstOrFail();
}

function reviewStageDecide(array $f, Model $review, string $status, array $extra = []): void
{
    test()->actingAs($f['actor'])->patch(route($f['module'].'.reviu.update', $review), array_merge([
        'status' => $status, 'hasil_review' => 'Hasil pemeriksaan resmi',
    ], $extra))->assertRedirect()->assertSessionHasNoErrors();
}

test('R4 dua kali looping membuka Tindak Lanjut seluruh surat tanpa membuka Tanggapan Awal', function (string $module) {
    $f = reviewStageFixture($module);
    $initialSource = reviewStageInput($f, 1);
    $review = reviewStageCompile($f);
    $initial = $f['butir']->record->fresh()->tahapAktif;
    $other = workflowButir($module);
    $other->forceFill(['id_'.$module => $f['butir']->record->{'id_'.$module}])->save();
    $otherFixture = $f;
    $otherFixture['butir'] = $other;
    $otherFixture['pics'] = [1 => $other->butirPics()->firstWhere('jenis_pic', 'utama')->id];
    $otherReview = reviewStageCompile($otherFixture, $initial);

    foreach ([1, 2] as $number) {
        reviewStageDecide($f, $review, 'tindak_lanjut_belum_sesuai');
        $record = $f['butir']->record->fresh();
        $stage = $record->tahapAktif;
        expect($stage->tahap_proses)->toBe(TahapProses::TindakLanjut)->and($stage->nomor_putaran)->toBe($number)
            ->and($record->status)->toBe('proses_tindak_lanjut_belum_sesuai')
            ->and($stage->bukti_pemetaan['review_id'])->toBe($review->id)
            ->and(array_keys($stage->penugasan_unit))->toContain($other->{'id_butir_'.$module});
        $this->actingAs($f['users'][2])->postJson(route($module.'.tanggapan.store', $f['butir']), [
            'butir_pic_id' => $f['pics'][2], 'isi' => 'Jangan buka Tanggapan Awal lagi',
        ])->assertUnprocessable();
        $source = reviewStageInput($f, 3, $stage, 'Perbaikan '.$number);
        expect($source->tahap_id)->toBe($stage->id)->and($f['butir']->record->fresh()->status)->toBe('proses_tindak_lanjut_belum_sesuai');
        reviewStageInput($otherFixture, 1, $stage, 'Butir lain ikut tahap perbaikan');
        $this->actingAs($f['actor'])->patchJson(route($module.'.reviu.update', $review), [
            'status' => 'tindak_lanjut_belum_sesuai', 'hasil_review' => 'Klik ulang',
        ])->assertUnprocessable();
        expect($record->tahapanProses()->count())->toBe($number + 1);
        $review = reviewStageCompile($f, $stage);
        expect($review->tahap_review)->toBe('tindak_lanjut');
    }
    expect($initial->fresh()->archived_at)->not->toBeNull()->and($f['butir']->tanggapanUnit()->count())->toBe(1);
    $this->actingAs($f['users'][1])->patchJson(route($module.'.tanggapan.update', $initialSource), ['isi' => 'Ubah riwayat'])->assertUnprocessable();
    $this->actingAs($f['actor'])->patchJson(route($module.'.reviu.update', $otherReview), ['status' => 'tindak_lanjut_sesuai', 'hasil_review' => 'Putusan lama'])->assertUnprocessable();
    expect(fn () => $initial->fresh()->update(['archived_at' => null]))->toThrow(LogicException::class);
    $this->get(route($module.'.tindak-lanjut.index'))->assertOk()->assertSee('Putaran 2');
    $this->get(route($module.'.reviu.index'))->assertOk()->assertSee('Tindak Lanjut Putaran 2');
})->with(['snp', 'djsn']);

test('R4 kedua keputusan Close menerima sekali kelengkapan tahap terakhir tanpa mengubah salinan', function (string $module, string $decision, bool $followUp) {
    $f = reviewStageFixture($module);
    reviewStageInput($f, 1);
    $review = reviewStageCompile($f);
    if ($followUp) {
        reviewStageDecide($f, $review, 'tindak_lanjut_belum_sesuai');
        $stage = $f['butir']->record->fresh()->tahapAktif;
        reviewStageInput($f, 1, $stage);
        $review = reviewStageCompile($f, $stage);
    }
    $stage = $f['butir']->record->fresh()->tahapAktif;
    $snapshot = $review->kompilasiPutaran->salinan_konteks;
    reviewStageDecide($f, $review, $decision);
    $record = $f['butir']->record->fresh();
    $closed = $record->getRawOriginal();
    $decisionRow = $review->fresh()->getRawOriginal();
    expect($record->status)->toBe($decision)->and($record->closed_at)->not->toBeNull()
        ->and($record->closed_review_id)->toBe($review->id)->and($record->closed_putaran_id)->toBe($stage->putaran_asal_id)
        ->and($record->tahap_aktif_id)->toBe($stage->id)->and($stage->fresh()->archived_at)->toBeNull();
    $late = reviewStageInput($f, 3, $stage, 'Kelengkapan setelah Close');
    expect($late->submitted_after_close_at)->not->toBeNull()->and($late->submitted_after_compilation_at)->not->toBeNull()
        ->and($record->fresh()->getRawOriginal())->toBe($closed)->and($review->fresh()->getRawOriginal())->toBe($decisionRow)
        ->and($review->kompilasiPutaran->fresh()->salinan_konteks)->toBe($snapshot)
        ->and($review->kompilasiPutaran->bahan()->count())->toBe(1);
    $this->actingAs($f['users'][3])->postJson($followUp ? route($module.'.tindak-lanjut.store') : route($module.'.tanggapan.store', $f['butir']), [
        'butir_id' => $f['butir']->id, 'tahap_id' => $stage->id, 'butir_pic_id' => $f['pics'][3], 'isi' => 'Ganda',
    ])->assertUnprocessable();
    $this->patchJson(route($module.($followUp ? '.tindak-lanjut.update' : '.tanggapan.update'), $late), ['isi' => 'Ubah susulan'])->assertUnprocessable();
    $this->actingAs($f['actor'])->patchJson(route($module.'.reviu.update', $review), ['hasil_review' => 'Buka lagi', 'status' => 'tindak_lanjut_belum_sesuai'])->assertUnprocessable();
    expect($record->tahapanProses()->count())->toBe($followUp ? 2 : 1);
    $this->get(route($module.($followUp ? '.tindak-lanjut.index' : '.tanggapan.index')))->assertOk()->assertSee('Setelah penutupan')->assertSee('Tidak termasuk kompilasi');
})->with(['snp', 'djsn'])->with(['tindak_lanjut_sesuai', 'tidak_dapat_ditindaklanjuti'])->with([false, true]);

test('R4 reviewer melihat salinan bukan perubahan atau isian setelah kompilasi', function (string $module) {
    $f = reviewStageFixture($module);
    $source = reviewStageInput($f, 1, body: 'Bahan yang dibekukan');
    $review = reviewStageCompile($f, extra: ['dokumen' => UploadedFile::fake()->create('kompilasi.pdf', 5, 'application/pdf')]);
    $this->actingAs($f['users'][1])->patch(route($module.'.tanggapan.update', $source), ['isi' => 'Isi edit setelah kompilasi', 'deliverables' => 'Hasil edit'])->assertSessionHasNoErrors();
    reviewStageInput($f, 2, body: 'Susulan tidak resmi');
    DB::table('tb_unit_kerja')->where('id', 1)->update(['nama_unit' => 'Nama unit berubah']);
    $f['users'][1]->update(['name' => 'Nama pengisi berubah']);
    $f['butir']->update(['butir_'.$module => 'Butir setelah kompilasi']);
    $preview = app(ReviuBahanPreviewService::class)->forReview($review->fresh(), $f['actor']);
    expect(count($preview['responses']))->toBe(1)->and($preview['responses'][0]['content'])->toBe('Bahan yang dibekukan')
        ->and($preview['responses'][0]['unit'])->toBe('A - Unit A')
        ->and($preview['responses'][0]['author'])->not->toBe('Nama pengisi berubah')
        ->and($preview['butir_content'])->not->toBe('Butir setelah kompilasi')
        ->and($preview['missing_units'])->toBe(['Unit B', 'Unit C']);
    Storage::disk('public')->delete($review->kompilasiPutaran->dokumen);
    $this->actingAs($f['actor'])->get(route($module.'.reviu.dokumen', $review))->assertOk();
    $this->get(route($module.'.reviu.index'))->assertOk()->assertSee('Salinan bahan saat kompilasi')
        ->assertDontSee('Isi edit setelah kompilasi')->assertDontSee('Susulan tidak resmi')->assertDontSee('Butir setelah kompilasi');
    reviewStageDecide($f, $review, 'tindak_lanjut_sesuai');
})->with(['snp', 'djsn']);

test('R4 Belum Ditanggapi tetap dapat disimpan tanpa transisi termasuk kompilasi kosong', function (string $module) {
    $f = reviewStageFixture($module);
    $review = reviewStageCompile($f);
    $stage = $f['butir']->record->fresh()->tahapAktif;
    foreach (['Catatan pertama', 'Catatan lanjutan'] as $body) {
        reviewStageDecide($f, $review, 'belum_ditanggapi', ['hasil_review' => $body]);
        expect($review->fresh()->hasil_review)->toBe($body)->and($f['butir']->record->fresh()->tahap_aktif_id)->toBe($stage->id)
            ->and($f['butir']->record->fresh()->closed_at)->toBeNull()->and($stage->fresh()->archived_at)->toBeNull();
    }
    reviewStageDecide($f, $review, 'tindak_lanjut_belum_sesuai');
    expect($f['butir']->record->fresh()->tahapAktif->nomor_putaran)->toBe(1);
})->with(['snp', 'djsn']);

test('R4 kewenangan komite lama tetap berlaku untuk baca tulis dan unggah', function (string $module, string $role, bool $allowed) {
    $f = reviewStageFixture($module);
    $review = reviewStageCompile($f);
    $user = workflowUser($role === 'super_admin' ? $role : $role.'_'.$module);
    $response = $this->actingAs($user)->patchJson(route($module.'.reviu.update', $review), [
        'status' => 'belum_ditanggapi', 'hasil_review' => 'Uji kewenangan',
    ]);
    $allowed ? $response->assertRedirect() : $response->assertForbidden();
    $response = $this->postJson(route($module.'.reviu.images.store', $review), ['image' => UploadedFile::fake()->image('gambar.png')]);
    $allowed ? $response->assertCreated() : $response->assertForbidden();
    $user->komite()->detach();
    if ($role !== 'super_admin') {
        $this->get(route($module.'.reviu.index'))->assertOk()->assertDontSee('data-reviu-putaran', false);
        $this->patchJson(route($module.'.reviu.update', $review), ['status' => 'tindak_lanjut_sesuai', 'hasil_review' => 'Tidak berhak'])->assertForbidden();
        $this->get(route($module.'.reviu.dokumen', $review))->assertForbidden();
    }
})->with(['snp', 'djsn'])->with([['super_admin', true], ['pic', true], ['moderator', true], ['admin', false], ['viewer', false]]);

test('R4 salinan tidak valid atau lintas tahap menolak reviu dan unggah', function (string $module, string $damage) {
    $f = reviewStageFixture($module);
    reviewStageInput($f, 1);
    $review = reviewStageCompile($f);
    $db = $review->getConnection();
    $compilation = $review->kompilasiPutaran;
    if ($damage === 'material') {
        $db->table('tb_kompilasi_bahan')->where('kompilasi_id', $compilation->id)->delete();
    } elseif ($damage === 'phase') {
        $db->table('tb_review')->where('id', $review->id)->update(['tahap_id' => null]);
    } elseif ($damage === 'committee') {
        $db->table('tb_review')->where('id', $review->id)->update(['komite_id' => 99]);
    } elseif ($damage === 'context') {
        $snapshot = $compilation->salinan_konteks;
        unset($snapshot['butir']['isi']);
        $db->table('tb_kompilasi')->where('id', $compilation->id)->update(['salinan_konteks' => json_encode($snapshot)]);
    } else {
        $db->table('tb_kompilasi')->where('id', $compilation->id)->update(['status_bahan' => 'perlu_rekonsiliasi']);
    }
    expect(app(ReviuPutaranService::class)->canReview($f['actor'], $review->fresh()))->toBeFalse();
    $this->actingAs($f['actor'])->patchJson(route($module.'.reviu.update', $review), ['status' => 'tindak_lanjut_sesuai', 'hasil_review' => 'Ditolak'])->assertUnprocessable();
    $this->postJson(route($module.'.reviu.images.store', $review), ['image' => UploadedFile::fake()->image('gambar.png')])->assertUnprocessable();
    $this->get(route($module.'.reviu.index'))->assertOk()->assertSee('Salinan bahan perlu rekonsiliasi');
    expect($f['butir']->record->fresh()->closed_at)->toBeNull();
})->with(['snp', 'djsn'])->with(['material', 'phase', 'committee', 'context', 'unverified']);

test('R4 keputusan langsung dan perubahan riwayat model ditolak', function (string $module) {
    $f = reviewStageFixture($module);
    $review = reviewStageCompile($f);
    expect(fn () => $review->fresh()->update(['status' => 'tindak_lanjut_sesuai']))->toThrow(LogicException::class);
    expect(fn () => $review->fresh()->update(['komite_id' => 99]))->toThrow(LogicException::class);
    expect(fn () => $review->fresh()->delete())->toThrow(LogicException::class);
    reviewStageDecide($f, $review, 'tindak_lanjut_sesuai');
    config(['sidewas.alur_revisi_enabled' => false]);
    expect(fn () => $review->fresh()->update(['hasil_review' => 'Timpa keputusan']))->toThrow(LogicException::class);
})->with(['snp', 'djsn']);

test('R4 kegagalan audit membatalkan keputusan transisi dan unggahan', function (string $module, string $decision) {
    $f = reviewStageFixture($module);
    $review = reviewStageCompile($f);
    $record = $f['butir']->record->fresh();
    $old = $record->getRawOriginal();
    LogActivity::creating(function ($log): void {
        if (in_array($log->action, ['buka_tahap_tindak_lanjut', 'close_surat_tahap'], true)) {
            throw new RuntimeException('Audit sengaja gagal');
        }
    });
    try {
        $this->actingAs($f['actor'])->patchJson(route($module.'.reviu.update', $review), [
            'status' => $decision, 'hasil_review' => 'Keputusan gagal', 'dokumen' => UploadedFile::fake()->create('hasil.pdf', 5, 'application/pdf'),
        ])->assertStatus(500);
        expect($review->fresh()->status)->toBe('belum_ditanggapi')->and($record->fresh()->getRawOriginal())->toBe($old)
            ->and($record->tahapanProses()->count())->toBe(1)->and($record->tahapAktif->archived_at)->toBeNull()
            ->and(Storage::disk('public')->allFiles('dokumen/reviu-'.$module))->toBe([]);
    } finally {
        LogActivity::flushEventListeners();
    }
})->with(['snp', 'djsn'])->with(['tindak_lanjut_belum_sesuai', 'tindak_lanjut_sesuai']);

test('R4 browser menampilkan bahan resmi Tindak Lanjut putaran kedua dan mempertahankan formulir', function (string $module) {
    $pages = [];
    $images = [];
    foreach ([0, 1, 3] as $count) {
        $f = reviewStageFixture($module);
        $f['actor']->update(['name' => 'Penyusun resmi']);
        foreach ($f['users'] as $unit => $user) {
            $user->update(['name' => 'Pengisi '.chr(64 + $unit)]);
        }
        $review = reviewStageCompile($f);
        foreach ([1, 2] as $number) {
            reviewStageDecide($f, $review, 'tindak_lanjut_belum_sesuai');
            $stage = $f['butir']->record->fresh()->tahapAktif;
            if ($number === 1) {
                $review = reviewStageCompile($f, $stage);
            }
        }
        $imageHtml = '';
        if ($count === 3) {
            $filename = Str::uuid().'.png';
            $imagePath = $module.'-images/'.$f['butir']->record->id.'/'.$filename;
            Storage::disk('local')->put($imagePath, UploadedFile::fake()->image('bukti.png', 80, 40)->getContent());
            $imageHtml = '<img src="'.route($module.'.butir-images.show', ['record' => $f['butir']->record->id, 'filename' => $filename], false).'" data-width="50">';
        }
        $dates = [];
        for ($unit = 1; $unit <= $count; $unit++) {
            $content = $count === 3 && $unit === 1
                ? SnpButirContent::PREFIX.'<p><strong>Tanggapan panjang sebagai bahan Tindak Lanjut</strong></p>'.str_repeat('<p>Bahan resmi yang dapat digulir.</p>', 40).$imageHtml
                : 'Jawaban '.$unit.' <b>literal</b>';
            $source = reviewStageInput($f, $unit, $stage, $content);
            $dates[] = $source->created_at->format('d/m/Y H:i');
        }
        $review = reviewStageCompile($f, $stage, ['hasil_kompilasi' => SnpButirContent::PREFIX.'<p><em>Kompilasi referensi</em></p>'.$imageHtml]);
        $compilation = $review->kompilasiPutaran;
        foreach ($compilation->bahan as $material) {
            foreach ($material->salinan['gambar'] as $image) {
                $images[$image['url']] = base64_encode(Storage::disk($image['disk'])->get($image['path']));
            }
        }
        foreach ($compilation->salinan_konteks['kompilasi']['gambar'] as $image) {
            $images[$image['url']] = base64_encode(Storage::disk($image['disk'])->get($image['path']));
        }
        $pages[] = ['kind' => 'reviu', 'count' => $count, 'dates' => $dates, 'butirId' => $f['butir']->{'id_butir_'.$module},
            'kindLabel' => 'Tindak Lanjut', 'roundLabel' => 'Tindak Lanjut Putaran 2',
            'action' => '/'.$module.'/reviu/'.$review->id, 'compilationDate' => $compilation->bahan_dicatat_at->format('d/m/Y H:i'),
            'html' => $this->get(route($module.'.reviu.index', ['keyword' => $f['butir']->{'id_butir_'.$module}]))->assertOk()->getContent()];
    }
    $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
    $process = new Process(['node', base_path('tests/Unit/ReviuBahanPreview.browser.mjs')], base_path());
    $upload = UploadedFile::fake()->create('bahan.pdf', 1, 'application/pdf');
    $process->setInput(json_encode(['pages' => $pages, 'images' => $images, 'compiler' => 'Penyusun resmi', 'upload' => $upload->getPathname(),
        'css' => $manifest['resources/css/app.css']['file'], 'js' => $manifest['resources/js/app.js']['file'],
        'screenshot' => base_path('tmp/reviu-r4-'.$module)]));
    $process->setTimeout(90)->run();
    expect($process->isSuccessful())->toBeTrue($process->getOutput().$process->getErrorOutput());
})->with(['snp', 'djsn']);
