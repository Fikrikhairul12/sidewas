<?php

use App\Models\DeleteRequest;
use App\Models\DjsnButir;
use App\Models\DjsnReview;
use App\Models\LogActivity;
use App\Models\SnpButir;
use App\Models\SnpReview;
use App\Models\User;
use App\Services\ReviuPutaranService;
use App\Services\SuratAwalStatusService;
use App\Services\SuratPutaranService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Tests\TestCase;

uses(TestCase::class);

require_once __DIR__.'/WorkflowTestSetup.php';

beforeEach(function () {
    $this->withoutVite();
    setUpWorkflowTestDatabase();
    (require database_path('migrations/2026_10_22_085515_expand_snp_djsn_review_status_choices.php'))->up();
    config(['sidewas.reviu_putaran_enabled' => true]);
    Storage::fake('public');
});

/** @return array{User, User, User, User, SnpButir|DjsnButir} */
function closureFixture(string $module): array
{
    $compiler = workflowUser('super_admin');
    test()->actingAs($compiler);
    $butir = workflowButir($module);
    DB::table('tb_unit_kerja')->insert(['id' => 2, 'direktorat_id' => 1, 'nama_unit' => 'Unit B', 'kode_unit' => 'B', 'status' => 'active']);
    $butir->butirPics()->create(['jenis_pic' => 'pendukung', 'unit_kerja_id' => 2]);
    $a = workflowUser('pic_'.$module);
    $b = workflowUser('pic_'.$module);
    $b->unitKerja()->sync([2 => ['status' => 'active']]);
    $reviewer = workflowUser('moderator_'.$module);
    $typeId = DB::table('tb_type')->insertGetId(['code' => $module, 'name' => strtoupper($module)]);
    DB::table('tb_role_type')->whereIn('name', ['pic_'.$module, 'moderator_'.$module])->update(['type_id' => $typeId]);

    return [$compiler, $a, $b, $reviewer, $butir];
}

function closureCompile(string $module, SnpButir|DjsnButir $butir, User $compiler, ?int $roundId = null): SnpReview|DjsnReview
{
    test()->actingAs($compiler)->post(route($module.'.kompilasi.store', $butir), [
        'tahap_kompilasi' => 'tanggapan', 'putaran_id' => $roundId, 'hasil_kompilasi' => 'Bahan kompilasi yang diperiksa',
    ])->assertRedirect()->assertSessionHasNoErrors();

    return $butir->reviews()->latest('id')->firstOrFail();
}

function closureResponse(string $module, SnpButir|DjsnButir $butir, User $user, ?int $roundId = null, string $text = 'Jawaban A sebelum penutupan'): void
{
    $pic = $butir->butirPics()->where('unit_kerja_id', $user->unitKerjaIds()[0])->firstOrFail();
    test()->actingAs($user)->post(route($module.'.tanggapan.store', $butir), [
        'putaran_id' => $roundId, 'butir_pic_id' => $pic->id, 'tanggapan' => $text, 'deliverables' => 'Hasil unit',
        'submitted_after_close_at' => '2000-01-01', 'status' => 'proses', 'closed_at' => null,
    ])->assertRedirect()->assertSessionHasNoErrors();
}

test('final decisions close the whole letter and late responses only complete the last round without changing reviewed material', function (string $module, string $decision, bool $afterLoop) {
    [$compiler, $a, $b, $reviewer, $butir] = closureFixture($module);
    $control = workflowButir($module);
    $controlSnapshot = $control->record->fresh()->getRawOriginal();
    $second = $butir->newQuery()->create(['id_'.$module => $butir->{'id_'.$module}, 'butir_'.$module => 'Butir lain surat yang sama']);
    $second->butirPics()->create(['jenis_pic' => 'komite', 'komite_id' => 1]);
    $second->butirPics()->create(['jenis_pic' => 'utama', 'unit_kerja_id' => 1]);
    $second->butirPics()->create(['jenis_pic' => 'pendukung', 'unit_kerja_id' => 2]);

    closureResponse($module, $butir, $a);
    $oldResponse = $butir->tanggapanUnit()->firstOrFail();
    expect($oldResponse->submitted_after_close_at)->toBeNull();
    expect(fn () => $oldResponse->fresh()->forceFill(['submitted_after_close_at' => now()])->save())->toThrow(LogicException::class);
    $review = closureCompile($module, $butir, $compiler);
    $oldRound = $review->putaran_id;
    if ($afterLoop) {
        $this->actingAs($reviewer)->patch(route($module.'.reviu.update', $review), [
            'hasil_review' => 'Perbaikan', 'status' => 'tindak_lanjut_belum_sesuai',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $roundId = $butir->record->fresh()->putaran_aktif_id;
        closureResponse($module, $butir, $a, $roundId);
        $review = closureCompile($module, $butir, $compiler, $roundId);
    }
    $pending = closureCompile($module, $second, $compiler, $review->putaran_id);
    $material = $butir->tanggapanAktif()->firstOrFail();
    $materialSnapshot = $material->getRawOriginal();
    $compilationSnapshot = $review->kompilasiPutaran->getRawOriginal();
    $pendingSnapshot = $pending->getRawOriginal();
    Storage::disk('public')->put('dokumen/surat-uji.pdf', 'surat sumber');
    $butir->record->fresh()->update(['dokumen' => 'dokumen/surat-uji.pdf']);
    $deleteRequest = DeleteRequest::create([
        'type_code' => $module, 'table_name' => 'tb_record', 'record_key' => $butir->{'id_'.$module},
        'status' => 'pending_super_admin_approval', 'requested_by' => $reviewer->id, 'reason' => 'Pengajuan sebelum Close',
    ]);
    $this->travelTo(now()->startOfSecond());
    $this->actingAs($reviewer)->patch(route($module.'.reviu.update', $review), [
        'hasil_review' => 'Keputusan final', 'status' => $decision, 'closed_by' => 999, 'closed_at' => '2000-01-01',
    ])->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('success');
    $record = $butir->record->fresh();
    expect($record->status)->toBe($decision)->and($record->closed_review_id)->toBe($review->id)
        ->and($record->closed_putaran_id)->toBe($review->putaran_id)->and($record->closed_by)->toBe($reviewer->id)
        ->and($record->closed_at->equalTo(now()))->toBeTrue()
        ->and($record->putaran()->count())->toBe($afterLoop ? 2 : 1)
        ->and($review->putaran->fresh()->archived_at)->toBeNull()
        ->and($control->record->fresh()->getRawOriginal())->toBe($controlSnapshot)
        ->and(LogActivity::where('action', 'close_surat_putaran')->count())->toBe(1);
    $recordSnapshot = $record->getRawOriginal();
    $reviewSnapshot = $review->fresh()->getRawOriginal();
    foreach ([$review, $pending] as $closedReview) {
        foreach (['tindak_lanjut_belum_sesuai', 'tindak_lanjut_sesuai', 'tidak_dapat_ditindaklanjuti'] as $status) {
            $this->patchJson(route($module.'.reviu.update', $closedReview), ['hasil_review' => 'Timpa', 'status' => $status])->assertUnprocessable();
        }
        $this->postJson(route($module.'.reviu.images.store', $closedReview), ['image' => UploadedFile::fake()->image('ditutup.png')])->assertUnprocessable();
    }
    $this->actingAs($compiler)->postJson(route($module.'.kompilasi.store', $second), [
        'putaran_id' => $review->putaran_id, 'tahap_kompilasi' => 'tanggapan', 'hasil_kompilasi' => 'Timpa',
    ])->assertUnprocessable();
    foreach ([$material, $review->kompilasiPutaran, $review->fresh(), $pending] as $frozen) {
        $field = match ($frozen->getTable()) {
            'tb_tanggapan' => 'tanggapan', 'tb_kompilasi' => 'hasil_kompilasi', default => 'hasil_review'
        };
        expect(fn () => $frozen->update([$field => 'Timpa langsung']))->toThrow(LogicException::class);
        expect(fn () => $frozen->fresh()->delete())->toThrow(LogicException::class);
    }
    foreach (['status' => 'proses', 'closed_by' => 999, 'closed_at' => null, 'closed_review_id' => $pending->id, 'putaran_aktif_id' => null] as $field => $value) {
        if ((string) $record->getAttribute($field) !== (string) $value) {
            expect(fn () => $record->fresh()->forceFill([$field => $value])->save())->toThrow(LogicException::class);
        }
    }
    expect(fn () => $record->fresh()->delete())->toThrow(LogicException::class)
        ->and(fn () => app(SuratPutaranService::class)->buka($record->fresh(), $compiler->id))->toThrow(LogicException::class);
    expect(fn () => $review->putaran->fresh()->update(['archived_at' => now()]))->toThrow(LogicException::class);
    foreach (['patch' => $module.'.perekaman.update', 'post' => $module.'.perekaman.butir.store', 'delete' => $module.'.perekaman.destroy.request'] as $verb => $route) {
        $method = $verb.'Json';
        $this->actingAs($compiler)->{$method}(route($route, $record), [])->assertUnprocessable();
    }
    $this->actingAs($compiler)->patchJson(route('administrasi.pengajuan.approve', $deleteRequest))->assertUnprocessable();
    expect($deleteRequest->fresh()->status)->toBe('pending_super_admin_approval');
    Storage::disk('public')->assertExists('dokumen/surat-uji.pdf');

    $this->actingAs($b)->get(route($module.'.tanggapan.index'))->assertOk()->assertViewHas('responseStates', function ($states) use ($butir): bool {
        return $states[$butir->id]['is_closed'] && $states[$butir->id]['available_pics']->count() === 1;
    });
    $this->travel(2)->days();
    closureResponse($module, $butir, $b, $review->putaran_id, 'Kelengkapan B setelah Close');
    closureResponse($module, $second, $a, $review->putaran_id, 'Kelengkapan A pada butir lain');
    $late = $butir->tanggapanAktif()->where('unit_kerja_id', 2)->firstOrFail();
    expect($late->submitted_after_close_at->equalTo(now()))->toBeTrue()
        ->and($late->created_by)->toBe($b->id)->and($late->created_at->equalTo(now()))->toBeTrue();
    $this->patchJson(route($module.'.tanggapan.update', $late), ['tanggapan' => 'Mengubah susulan', 'deliverables' => 'X'])->assertForbidden();
    $picB = $butir->butirPics()->where('unit_kerja_id', 2)->firstOrFail();
    $this->actingAs($b)->postJson(route($module.'.tanggapan.store', $butir), ['putaran_id' => $review->putaran_id, 'butir_pic_id' => $picB->id, 'tanggapan' => 'Duplikat', 'deliverables' => 'X'])->assertUnprocessable();
    if ($afterLoop) {
        $this->postJson(route($module.'.tanggapan.store', $second), ['putaran_id' => $oldRound, 'butir_pic_id' => $second->butirPics()->where('unit_kerja_id', 2)->firstOrFail()->id, 'tanggapan' => 'Mengisi lama', 'deliverables' => 'X'])->assertUnprocessable();
    }
    (new SuratAwalStatusService)->synchronizeRecord($record->fresh());
    expect($record->fresh()->getRawOriginal())->toBe($recordSnapshot)
        ->and($review->fresh()->getRawOriginal())->toBe($reviewSnapshot)
        ->and($pending->fresh()->getRawOriginal())->toBe($pendingSnapshot)
        ->and($material->fresh()->getRawOriginal())->toBe($materialSnapshot)
        ->and($review->kompilasiPutaran->fresh()->getRawOriginal())->toBe($compilationSnapshot)
        ->and($record->putaran()->count())->toBe($afterLoop ? 2 : 1);
    expect(app(ReviuPutaranService::class)->current($butir->tanggapanUnit()->get(), $butir->fresh()->load('record.putaran'))->pluck('id')->all())->toBe([$material->id]);
    $this->actingAs($compiler)->get(route($module.'.kompilasi.index'))->assertOk()->assertDontSee('Kelengkapan B setelah Close');
    $this->actingAs($b)->get(route($module.'.tanggapan.index'))->assertOk()->assertSee('Tanggapan setelah penutupan')->assertViewHas('responseStates', function ($states) use ($butir, $b, $late): bool {
        $state = $states[$butir->id];
        $card = collect($state['detail']['pic_tanggapans'])->firstWhere('tanggapan_id', $late->id);

        return $state['available_pics']->isEmpty() && $card['submitted_after_close'] && ! $card['can_edit'] && $card['creator'] === $b->name;
    });
})->with(['snp', 'djsn'])->with(['tindak_lanjut_sesuai', 'tidak_dapat_ditindaklanjuti'])->with([false, true]);

test('failed closing audits roll back the decision closure metadata and uploaded files', function (string $module, string $action) {
    [$compiler, $a, $b, $reviewer, $butir] = closureFixture($module);
    $review = closureCompile($module, $butir, $compiler);
    $beforeRecord = $butir->record->fresh()->getRawOriginal();
    $beforeReview = $review->getRawOriginal();
    Event::listen('eloquent.creating: '.LogActivity::class, function (LogActivity $log) use ($action): void {
        if ($log->action === $action) {
            throw new RuntimeException('Audit penutupan gagal');
        }
    });
    $this->withoutExceptionHandling();
    expect(fn () => $this->actingAs($reviewer)->patch(route($module.'.reviu.update', $review), [
        'hasil_review' => 'Close', 'status' => 'tindak_lanjut_sesuai', 'dokumen' => UploadedFile::fake()->create('final.pdf', 1, 'application/pdf'),
    ]))->toThrow(RuntimeException::class, 'Audit penutupan gagal');
    expect($butir->record->fresh()->getRawOriginal())->toBe($beforeRecord)
        ->and($review->fresh()->getRawOriginal())->toBe($beforeReview)
        ->and(LogActivity::where('action', 'close_surat_putaran')->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
})->with(['snp', 'djsn'])->with(['close_surat_putaran', 'update_review_putaran']);

test('closure migration is repeatable preserves legacy data and refuses removing used metadata from either module', function () {
    $migration = require database_path('migrations/2026_10_22_100320_add_snp_djsn_letter_closure_metadata.php');
    $butir = workflowButir('snp');
    $snapshot = $butir->record->getRawOriginal();
    $migration->up();
    $migration->up();
    expect($butir->record->fresh()->getRawOriginal())->toBe($snapshot);
    $migration->down();
    $migration->up();
    [$compiler, $a, $b, $reviewer, $djsn] = closureFixture('djsn');
    $review = closureCompile('djsn', $djsn, $compiler);
    $this->actingAs($reviewer)->patch(route('djsn.reviu.update', $review), ['hasil_review' => 'Tidak bisa', 'status' => 'tidak_dapat_ditindaklanjuti'])->assertSessionHasNoErrors();
    expect(fn () => $migration->down())->toThrow(RuntimeException::class)
        ->and($djsn->record->fresh()->closed_review_id)->toBe($review->id);
});

test('closed response pages show the late completion notice and marked response without offering edits', function (string $module) {
    [$compiler, $a, $b, $reviewer, $butir] = closureFixture($module);
    closureResponse($module, $butir, $a);
    $review = closureCompile($module, $butir, $compiler);
    $this->actingAs($reviewer)->patch(route($module.'.reviu.update', $review), [
        'hasil_review' => 'Sesuai', 'status' => 'tindak_lanjut_sesuai',
    ])->assertSessionHasNoErrors();
    $picId = $butir->butirPics()->where('unit_kerja_id', 2)->firstOrFail()->id;
    $pages = [[
        'mode' => 'closed-pending', 'picId' => $picId, 'roundId' => $review->putaran_id,
        'html' => $this->actingAs($b)->get(route($module.'.tanggapan.index'))->assertOk()->getContent(),
    ]];
    closureResponse($module, $butir, $b, $review->putaran_id, 'Kelengkapan B setelah Close');
    $pages[] = ['mode' => 'closed-late', 'html' => $this->get(route($module.'.tanggapan.index'))->assertOk()->getContent()];
    $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
    $process = new Process(['node', base_path('tests/Unit/TanggapanUnit.browser.mjs')], base_path());
    $process->setInput(json_encode([
        'module' => $module, 'pages' => $pages,
        'css' => $manifest['resources/css/app.css']['file'], 'js' => $manifest['resources/js/app.js']['file'],
        'screenshot' => base_path('tmp/workflow-close-'.$module.'.png'),
    ]));
    $process->setTimeout(60)->run();
    expect($process->isSuccessful())->toBeTrue($process->getOutput().$process->getErrorOutput());
})->with(['snp', 'djsn']);
