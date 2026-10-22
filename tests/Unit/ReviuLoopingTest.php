<?php

use App\Enums\SuratStatus;
use App\Models\DjsnButir;
use App\Models\DjsnReview;
use App\Models\LogActivity;
use App\Models\SnpButir;
use App\Models\SnpReview;
use App\Models\SuratPutaran;
use App\Models\User;
use App\Services\SnpButirContent;
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

function loopingFixture(string $module): array
{
    $compiler = workflowUser('super_admin');
    test()->actingAs($compiler);
    $first = workflowButir($module);
    $second = $first->newQuery()->create(['id_'.$module => $first->{'id_'.$module}, 'butir_'.$module => 'Butir kedua surat yang sama']);
    DB::table('tb_unit_kerja')->insert(['id' => 2, 'direktorat_id' => 1, 'nama_unit' => 'Unit B', 'kode_unit' => 'B', 'status' => 'active']);
    foreach ([$first, $second] as $butir) {
        if ($butir->id === $second->id) {
            $butir->butirPics()->create(['jenis_pic' => 'komite', 'komite_id' => 1]);
            $butir->butirPics()->create(['jenis_pic' => 'utama', 'unit_kerja_id' => 1]);
        }
        $butir->butirPics()->create(['jenis_pic' => 'pendukung', 'unit_kerja_id' => 2]);
    }
    $userA = workflowUser('pic_'.$module);
    $userB = workflowUser('pic_'.$module);
    $userB->unitKerja()->sync([2 => ['status' => 'active']]);
    $reviewer = workflowUser('moderator_'.$module);
    $typeId = DB::table('tb_type')->insertGetId(['code' => $module, 'name' => strtoupper($module)]);
    DB::table('tb_role_type')->whereIn('name', ['pic_'.$module, 'moderator_'.$module])->update(['type_id' => $typeId]);

    return [$compiler, $userA, $userB, $reviewer, $first, $second];
}

function loopingRespond(string $module, SnpButir|DjsnButir $butir, User $user, ?int $roundId, string $text): void
{
    $pic = $butir->butirPics()->where('unit_kerja_id', $user->unitKerjaIds()[0])->firstOrFail();
    test()->actingAs($user)->post(route($module.'.tanggapan.store', $butir), [
        'butir_pic_id' => $pic->id, 'putaran_id' => $roundId, 'tanggapan' => $text, 'deliverables' => 'Hasil '.$text,
    ])->assertRedirect()->assertSessionHasNoErrors();
}

function loopingCompile(string $module, SnpButir|DjsnButir $butir, User $compiler, ?int $roundId, string $text): SnpReview|DjsnReview
{
    test()->actingAs($compiler)->post(route($module.'.kompilasi.store', $butir), [
        'tahap_kompilasi' => 'tanggapan', 'putaran_id' => $roundId, 'hasil_kompilasi' => $text,
    ])->assertRedirect()->assertSessionHasNoErrors();
    $class = $module === 'snp' ? SnpReview::class : DjsnReview::class;

    return $class::where('id_butir_'.$module, $butir->{'id_butir_'.$module})->latest('id')->firstOrFail();
}

function loopingReject(string $module, SnpReview|DjsnReview $review, User $reviewer, string $text): SuratPutaran
{
    test()->actingAs($reviewer)->patch(route($module.'.reviu.update', $review), [
        'hasil_review' => $text, 'status' => 'tindak_lanjut_belum_sesuai', 'deliverables' => 'Perbaikan diperlukan',
    ])->assertRedirect(route($module.'.reviu.index'))->assertSessionHasNoErrors()->assertSessionHas('success');
    $round = $review->butir->record->fresh()->putaranAktif;
    expect($round)->not->toBeNull();

    return $round;
}

test('one rejection reopens every unit and butir of the same letter without reopening other letters', function (string $module) {
    [$compiler, $a, $b, $reviewer, $first, $second] = loopingFixture($module);
    loopingRespond($module, $first, $a, null, 'Jawaban awal A1');
    loopingRespond($module, $second, $a, null, 'Jawaban awal A2');
    $oldA = $first->tanggapanUnit()->firstOrFail();
    $oldOther = $second->tanggapanUnit()->firstOrFail();
    $review = loopingCompile($module, $first, $compiler, null, 'Kompilasi awal 1');
    $pendingReview = loopingCompile($module, $second, $compiler, $review->putaran_id, 'Kompilasi awal 2');
    $control = workflowButir($module);
    $controlBefore = $control->record->fresh()->getRawOriginal();
    $oldRoundId = $review->putaran_id;
    $round = loopingReject($module, $review, $reviewer, 'Perbaiki seluruh surat');

    expect($round->nomor_putaran)->toBe(1)
        ->and($round->created_by)->toBe($reviewer->id)
        ->and($round->tanggapan()->count())->toBe(0)
        ->and($round->kompilasi()->count())->toBe(0)
        ->and($round->reviu()->count())->toBe(0)
        ->and($review->fresh()->status)->toBe('tindak_lanjut_belum_sesuai')
        ->and($review->putaran->fresh()->archived_by)->toBe($reviewer->id)
        ->and($review->putaran->fresh()->archived_at)->not->toBeNull()
        ->and($control->record->fresh()->getRawOriginal())->toBe($controlBefore);

    foreach ([$first, $second] as $butir) {
        expect($butir->record->fresh()->status)->toBe(SuratStatus::TindakLanjutBelumSesuai->value)
            ->and($butir->tanggapanAktif()->count())->toBe(0);
    }
    foreach ([$a, $b] as $user) {
        $page = $this->actingAs($user)->get(route($module.'.tanggapan.index', ['keyword' => $first->{'id_'.$module}]))->assertOk();
        $page->assertViewHas('responseStates', function ($states) use ($first, $second, $round): bool {
            foreach ([$first, $second] as $butir) {
                if ($states[$butir->id]['round_id'] !== $round->id || $states[$butir->id]['available_pics']->count() !== 1 || $states[$butir->id]['responses']->isNotEmpty()) {
                    return false;
                }
            }

            return true;
        });
    }
    $this->actingAs($compiler)->patchJson(route($module.'.tanggapan.update', $oldOther), ['tanggapan' => 'Menimpa butir lain', 'deliverables' => 'Dilarang'])->assertForbidden();
    $this->actingAs($b)->postJson(route($module.'.tanggapan.store', $second), [
        'butir_pic_id' => $second->butirPics()->where('unit_kerja_id', 2)->firstOrFail()->id,
        'putaran_id' => $oldRoundId, 'tanggapan' => 'Menyusul putaran lama', 'deliverables' => 'Dilarang',
    ])->assertUnprocessable()->assertJsonValidationErrors('putaran_id');
    foreach ([$review, $pendingReview] as $stale) {
        $this->actingAs($reviewer)->patchJson(route($module.'.reviu.update', $stale), ['hasil_review' => 'Duplikat', 'status' => 'tindak_lanjut_belum_sesuai'])->assertUnprocessable();
        $this->postJson(route($module.'.reviu.images.store', $stale), ['image' => UploadedFile::fake()->image('lama.png')])->assertUnprocessable();
    }
    expect($first->record->fresh()->putaran()->count())->toBe(2)
        ->and($pendingReview->fresh()->status)->toBe('belum_ditanggapi')
        ->and($oldA->fresh()->tanggapan)->toBe('Jawaban awal A1');
})->with(['snp', 'djsn']);

test('two complete rejections keep every round separate and retain the rejected letter status through responses compilation and undecided review', function (string $module) {
    [$compiler, $a, $b, $reviewer, $first, $second] = loopingFixture($module);
    $roundId = null;
    $snapshots = [];
    foreach ([0, 1] as $number) {
        loopingRespond($module, $first, $a, $roundId, 'Jawaban A putaran '.$number);
        if ($number) {
            loopingRespond($module, $first, $b, $roundId, 'Jawaban B putaran '.$number);
            expect($first->record->fresh()->status)->toBe('proses_tindak_lanjut_belum_sesuai');
        }
        $review = loopingCompile($module, $first, $compiler, $roundId, 'Kompilasi putaran '.$number);
        if ($number) {
            expect($first->record->fresh()->status)->toBe('proses_tindak_lanjut_belum_sesuai');
            $this->actingAs($reviewer)->patch(route($module.'.reviu.update', $review), ['hasil_review' => 'Menunggu keputusan', 'status' => 'belum_ditanggapi'])->assertRedirect()->assertSessionHasNoErrors();
            expect($first->record->fresh()->status)->toBe('proses_tindak_lanjut_belum_sesuai');
        }
        $round = loopingReject($module, $review, $reviewer, 'Hasil reviu putaran '.$number);
        expect($round->nomor_putaran)->toBe($number + 1);
        foreach ($first->record->fresh()->putaran()->whereNotNull('archived_at')->get() as $archived) {
            foreach (['tanggapan', 'kompilasi', 'reviu'] as $relation) {
                foreach ($archived->{$relation} as $row) {
                    $key = $row->getTable().'-'.$row->id;
                    if (isset($snapshots[$key])) {
                        expect($row->getRawOriginal())->toBe($snapshots[$key][1]);
                    }
                    $snapshots[$key] = [$row, $row->getRawOriginal()];
                    expect(fn () => $row->update([$row->getTable() === 'tb_tanggapan' ? 'tanggapan' : ($row->getTable() === 'tb_kompilasi' ? 'hasil_kompilasi' : 'hasil_review') => 'Timpa']))->toThrow(LogicException::class);
                    expect(fn () => $row->delete())->toThrow(LogicException::class);
                }
            }
        }
        $roundId = $round->id;
    }
    loopingRespond($module, $first, $a, $roundId, 'Jawaban A putaran terakhir');
    $last = $first->tanggapanAktif()->firstOrFail();
    $this->actingAs($a)->patch(route($module.'.tanggapan.update', $last), ['tanggapan' => 'Perbaikan terakhir', 'deliverables' => 'Hasil terbaru'])->assertRedirect()->assertSessionHasNoErrors();
    $this->travel(35)->days();
    app(SuratAwalStatusService::class)->synchronizeModule($module);
    expect($first->record->fresh()->status)->toBe('proses_tindak_lanjut_belum_sesuai')
        ->and($first->record->fresh()->putaran()->pluck('nomor_putaran')->all())->toBe([0, 1, 2])
        ->and($first->tanggapanUnit()->count())->toBe(4)
        ->and($first->tanggapanAktif()->count())->toBe(1);
    foreach ($snapshots as [$row, $snapshot]) {
        expect($row->fresh()->getRawOriginal())->toBe($snapshot);
    }
    $audits = LogActivity::where('type_code', $module)->where('action', 'buka_putaran_perbaikan')->orderBy('id')->get();
    expect($audits)->toHaveCount(2)->and($audits->pluck('user_id')->unique()->all())->toBe([$reviewer->id])
        ->and($audits[1]->new_values['putaran_baru']['nomor_putaran'])->toBe(2);
})->with(['snp', 'djsn']);

test('a unit that skipped an old round answers only the active round and another user from the same unit cannot duplicate it', function (string $module) {
    [$compiler, $a, $b, $reviewer, $first] = loopingFixture($module);
    loopingRespond($module, $first, $a, null, 'Jawaban lama A');
    $review = loopingCompile($module, $first, $compiler, null, 'Kompilasi sebagian');
    $round = loopingReject($module, $review, $reviewer, 'Perbaiki tanggapan');
    loopingRespond($module, $first, $b, $round->id, 'Jawaban baru B');
    loopingRespond($module, $first, $a, $round->id, 'Jawaban baru A');
    $colleague = workflowUser('pic_'.$module);
    $pic = $first->butirPics()->where('unit_kerja_id', 1)->firstOrFail();
    $this->actingAs($colleague)->postJson(route($module.'.tanggapan.store', $first), [
        'butir_pic_id' => $pic->id, 'putaran_id' => $round->id, 'tanggapan' => 'Duplikat A', 'deliverables' => 'Dilarang',
    ])->assertUnprocessable()->assertJsonValidationErrors('butir_pic_id');
    expect($first->tanggapanAktif()->count())->toBe(2)->and($first->tanggapanUnit()->count())->toBe(3)
        ->and($first->tanggapanUnit()->where('putaran_id', $review->putaran_id)->where('unit_kerja_id', 2)->count())->toBe(0);
})->with(['snp', 'djsn']);

test('a failed round opening rolls back the review archive status due date and document replacement together', function (string $module) {
    [$compiler, $a, $b, $reviewer, $first] = loopingFixture($module);
    $round = app(SuratPutaranService::class)->buka($first->record->fresh(), $compiler->id);
    loopingRespond($module, $first, $a, $round->id, 'Jawaban putaran aktif');
    $review = loopingCompile($module, $first, $compiler, $round->id, 'Kompilasi sebelum gagal');
    $review->kompilasiPutaran->update(['ubah_tgl' => '2027-05-10', 'status_pengajuan_tgl' => 'pending']);
    Storage::disk('public')->put('dokumen/reviu-lama.pdf', 'Dokumen draf lama');
    $review->update(['dokumen' => 'dokumen/reviu-lama.pdf', 'hasil_review' => 'Draf sebelum kegagalan']);
    $before = [$first->record->fresh()->getRawOriginal(), $round->fresh()->getRawOriginal(), $review->fresh()->getRawOriginal(), $review->kompilasiPutaran->fresh()->getRawOriginal()];
    $logs = LogActivity::count();
    $this->partialMock(SuratPutaranService::class, function ($mock) {
        $mock->shouldReceive('buka')->once()->andThrow(new RuntimeException('Simulasi pembukaan putaran gagal'));
    });
    $this->actingAs($reviewer)->patchJson(route($module.'.reviu.update', $review), [
        'hasil_review' => 'Reviu gagal', 'status' => 'tindak_lanjut_belum_sesuai', 'status_pengajuan_tgl' => 'disetujui',
        'dokumen' => UploadedFile::fake()->create('baru.pdf', 1, 'application/pdf'),
    ])->assertStatus(500);
    expect([$first->record->fresh()->getRawOriginal(), $round->fresh()->getRawOriginal(), $review->fresh()->getRawOriginal(), $review->kompilasiPutaran->fresh()->getRawOriginal()])->toBe($before)
        ->and($first->record->fresh()->putaran()->count())->toBe(2)
        ->and(Storage::disk('public')->allFiles())->toBe(['dokumen/reviu-lama.pdf'])
        ->and(LogActivity::count())->toBe($logs);
})->with(['snp', 'djsn']);

test('Detail exposes only permitted review history and never grants edit access to old responses', function (string $module) {
    [$compiler, $a, $b, $reviewer, $first] = loopingFixture($module);
    loopingRespond($module, $first, $a, null, 'Jawaban lama');
    $review = loopingCompile($module, $first, $compiler, null, 'Kompilasi lama');
    loopingReject($module, $review, $reviewer, 'Hasil reviu komite 1');
    DB::table('tb_komite')->insert(['id' => 2, 'nama_komite' => 'Komite lain']);
    $b->komite()->sync([2 => ['status' => 'active']]);
    $page = $this->actingAs($b)->get(route($module.'.tanggapan.index', ['keyword' => $first->{'id_butir_'.$module}]))->assertOk();
    $rounds = collect($page->viewData('responseStates')[$first->id]['detail']['rounds']);
    $old = $rounds->firstWhere('read_only', true);
    expect($old['reviews'])->toBeEmpty()->and($old['pic_tanggapans'][0]['can_edit'])->toBeFalse()
        ->and($old['compilations'][0]['content'])->toBe('Kompilasi lama');
    $a->roleTypes()->firstOrFail()->update(['type_id' => null]);
    $page = $this->actingAs($a)->get(route($module.'.tanggapan.index', ['keyword' => $first->{'id_butir_'.$module}]))->assertOk();
    $old = collect($page->viewData('responseStates')[$first->id]['detail']['rounds'])->firstWhere('read_only', true);
    expect($old['compilations'])->toBeEmpty()->and($old['reviews'][0]['content'])->toBe('Hasil reviu komite 1');
})->with(['snp', 'djsn']);

test('failure of the second audit entry rolls back both audit entries and the entire correction round', function (string $module) {
    [$compiler, $a, $b, $reviewer, $first] = loopingFixture($module);
    loopingRespond($module, $first, $a, null, 'Jawaban awal');
    $review = loopingCompile($module, $first, $compiler, null, 'Kompilasi awal');
    $round = $review->putaran;
    $before = [$first->record->fresh()->getRawOriginal(), $round->fresh()->getRawOriginal(), $review->fresh()->getRawOriginal()];
    $logCount = LogActivity::count();
    $event = 'eloquent.creating: '.LogActivity::class;
    Event::listen($event, function (LogActivity $entry): void {
        if ($entry->action === 'update_review_putaran') {
            throw new RuntimeException('Simulasi audit reviu gagal');
        }
    });
    try {
        $this->actingAs($reviewer)->patchJson(route($module.'.reviu.update', $review), ['hasil_review' => 'Keputusan gagal disimpan', 'status' => 'tindak_lanjut_belum_sesuai'])->assertStatus(500);
    } finally {
        Event::forget($event);
    }
    expect([$first->record->fresh()->getRawOriginal(), $round->fresh()->getRawOriginal(), $review->fresh()->getRawOriginal()])->toBe($before)
        ->and($first->record->fresh()->putaran()->count())->toBe(1)
        ->and(LogActivity::count())->toBe($logCount);
})->with(['snp', 'djsn']);

test('the browser reads both old rounds with formatting and images while new responses use the active round', function (string $module) {
    [$compiler, $a, $b, $reviewer, $first] = loopingFixture($module);
    $roundId = null;
    $history = [];
    $images = [];
    foreach ([0, 1] as $number) {
        loopingRespond($module, $first, $a, $roundId, 'Jawaban lama '.$number);
        $url = $this->actingAs($compiler)->postJson(route($module.'.kompilasi.images.store', $first), ['putaran_id' => $roundId, 'image' => UploadedFile::fake()->image('kompilasi.png')])->assertCreated()->json('url');
        $content = SnpButirContent::PREFIX.'<p><strong>Kompilasi lama '.$number.'</strong><img src="'.$url.'" data-width="50"></p>';
        $image = app(SnpButirContent::class)->images($content)[0];
        $images[$url] = base64_encode(Storage::disk('local')->get($image['path']));
        $review = loopingCompile($module, $first, $compiler, $roundId, $content);
        $round = loopingReject($module, $review, $reviewer, SnpButirContent::PREFIX.'<p><em>Perbaikan lama '.$number.'</em></p>');
        $history[] = ['id' => $review->putaran_id, 'response' => 'Jawaban lama '.$number];
        $roundId = $round->id;
    }
    $this->actingAs($a);
    $route = route($module.'.tanggapan.index', ['keyword' => $first->{'id_butir_'.$module}]);
    $pic = $first->butirPics()->where('unit_kerja_id', 1)->firstOrFail()->id;
    $pages = [['mode' => 'new-round', 'picId' => $pic, 'roundId' => $roundId, 'currentRoundId' => $roundId, 'historyRounds' => $history, 'html' => $this->get($route)->assertOk()->getContent()]];
    loopingRespond($module, $first, $a, $roundId, 'Jawaban unit A');
    $pages[] = ['mode' => 'answered', 'responseId' => $first->tanggapanAktif()->firstOrFail()->id, 'currentRoundId' => $roundId, 'historyRounds' => $history, 'html' => $this->get($route)->assertOk()->getContent()];
    $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
    $process = new Process(['node', base_path('tests/Unit/TanggapanUnit.browser.mjs')], base_path());
    $process->setInput(json_encode([
        'module' => $module, 'pages' => $pages, 'images' => $images,
        'css' => $manifest['resources/css/app.css']['file'], 'js' => $manifest['resources/js/app.js']['file'],
        'screenshot' => base_path('tmp/looping-history-'.$module.'.png'),
    ]));
    $process->setTimeout(60)->run();
    expect($process->isSuccessful())->toBeTrue($process->getOutput().$process->getErrorOutput());
})->with(['snp', 'djsn']);
