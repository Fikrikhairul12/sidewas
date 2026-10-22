<?php

use App\Enums\ReviuStatus;
use App\Models\DjsnReview;
use App\Models\SnpReview;
use App\Services\ReviuPutaranService;
use App\Services\SnpButirContent;
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
    (require database_path('migrations/2026_10_22_085515_expand_snp_djsn_review_status_choices.php'))->up();
    config(['sidewas.reviu_putaran_enabled' => true]);
    Storage::fake('public');
});

function prepareCompilation(string $module): array
{
    $actor = workflowUser('super_admin');
    test()->actingAs($actor);
    $butir = workflowButir($module);
    test()->post(route($module.'.kompilasi.store', $butir), [
        'tahap_kompilasi' => 'tanggapan', 'hasil_kompilasi' => 'Kompilasi sumber',
        'dokumen' => UploadedFile::fake()->create('sumber.pdf', 1, 'application/pdf'),
    ])->assertRedirect()->assertSessionHasNoErrors();
    $class = $module === 'snp' ? SnpReview::class : DjsnReview::class;

    return [$actor, $butir->fresh(), $class::whereNotNull('kompilasi_id')->latest('id')->firstOrFail()];
}

test('stage 6A stays inactive by default and the legacy DJSN response flow still works', function () {
    config(['sidewas.reviu_putaran_enabled' => false]);
    expect(ReviuPutaranService::enabled())->toBeFalse();
    $this->actingAs(workflowUser('super_admin'));
    $butir = workflowButir('djsn');
    $this->get(route('djsn.kompilasi.index'))->assertNotFound();
    $this->post(route('djsn.kompilasi.store', $butir), [])->assertNotFound();
    $this->post(route('djsn.tanggapan.store', $butir), [
        'butir_pic_id' => $butir->butirPics()->where('jenis_pic', 'utama')->firstOrFail()->id,
        'tanggapan' => 'Tanggapan lama', 'deliverables' => 'Hasil lama',
    ])->assertRedirect()->assertSessionHasNoErrors();
    expect(DjsnReview::count())->toBe(1)->and(DjsnReview::firstOrFail()->id_tanggapan)->not->toBeNull();
    $this->get(route('djsn.reviu.index'))->assertOk()->assertSee('Dalam Proses Reviu Dewan Pengawas');
});

test('both modules compile with zero or partial unit responses and create one linked review only after compilation', function (string $module, int $responseCount) {
    $actor = workflowUser('super_admin');
    $this->actingAs($actor);
    $butir = workflowButir($module);
    DB::table('tb_unit_kerja')->insert(['id' => 2, 'direktorat_id' => 1, 'nama_unit' => 'Unit Kedua', 'status' => 'active']);
    $butir->butirPics()->create(['jenis_pic' => 'pendukung', 'unit_kerja_id' => 2]);
    if ($responseCount) {
        $this->post(route($module.'.tanggapan.store', $butir), [
            'butir_pic_id' => $butir->butirPics()->where('jenis_pic', 'utama')->firstOrFail()->id,
            'tanggapan' => 'Jawaban satu unit', 'deliverables' => 'Hasil unit',
        ])->assertRedirect()->assertSessionHasNoErrors();
    }
    $reviewClass = $module === 'snp' ? SnpReview::class : DjsnReview::class;
    expect($reviewClass::count())->toBe(0);
    $page = $this->get(route($module.'.kompilasi.index'))->assertOk()->assertSee('data-kompilasi-putaran', false);
    $this->post(route($module.'.kompilasi.store', $butir), [
        'tahap_kompilasi' => 'tanggapan', 'hasil_kompilasi' => SnpButirContent::PREFIX.'<p><strong>Kompilasi aman</strong></p>',
        'deliverables' => 'Teks <literal>', 'ubah_tgl' => '2027-01-31',
    ])->assertRedirect(route($module.'.kompilasi.index'))->assertSessionHasNoErrors();
    $compilation = $butir->kompilasis()->firstOrFail();
    $review = $reviewClass::firstOrFail();
    expect($reviewClass::count())->toBe(1)
        ->and($review->kompilasi_id)->toBe($compilation->id)
        ->and($review->putaran_id)->toBe($compilation->putaran_id)
        ->and($review->id_tanggapan)->toBeNull()
        ->and($review->komite_id)->toBe(1)
        ->and($review->created_by)->toBe($actor->id)
        ->and($compilation->status_pengajuan_tgl)->toBe('pending')
        ->and($compilation->deliverables)->toBe('Teks <literal>');
    $this->postJson(route($module.'.kompilasi.store', $butir), ['tahap_kompilasi' => 'tanggapan', 'hasil_kompilasi' => 'Timpa'])->assertUnprocessable();
    expect($butir->kompilasis()->count())->toBe(1)->and($reviewClass::count())->toBe(1);
    $page = $this->get(route($module.'.reviu.index'))->assertOk()->assertSee('<strong>Kompilasi aman</strong>', false)->assertSee('data-reviu-putaran', false);
    $dom = new DOMDocument;
    @$dom->loadHTML($page->getContent());
    $options = (new DOMXPath($dom))->query('//select[@id="status-reviu"]/option');
    expect(iterator_to_array($options))->toHaveCount(4);
    expect(array_map(fn ($node) => $node->getAttribute('value'), iterator_to_array($options)))->toBe(array_keys(ReviuStatus::options()));
})->with(['snp', 'djsn'])->with([0, 1]);

test('review authority stays with the existing role and assigned committee including image and document endpoints', function (string $module, string $role, bool $sameCommittee) {
    [$author, $butir, $review] = prepareCompilation($module);
    $user = workflowUser($role === 'super_admin' ? $role : $role.'_'.$module);
    if (! $sameCommittee) {
        DB::table('tb_komite')->insert(['id' => 2, 'nama_komite' => 'Komite Lain']);
        $user->komite()->sync([2 => ['status' => 'active']]);
    }
    $this->actingAs($user);
    $allowed = $role === 'super_admin' || ($sameCommittee && in_array($role, ['pic', 'moderator'], true));
    $patch = $this->patchJson(route($module.'.reviu.update', $review), ['hasil_review' => 'Hasil baru', 'status' => 'belum_ditanggapi', 'komite_id' => 999]);
    $upload = $this->postJson(route($module.'.reviu.images.store', $review), ['image' => UploadedFile::fake()->image('reviu.png')]);
    $download = $this->get(route($module.'.reviu.dokumen', $review));
    if ($allowed) {
        $patch->assertRedirect()->assertSessionHasNoErrors();
        $upload->assertCreated();
        $download->assertOk();
        expect($review->fresh()->komite_id)->toBe(1)->and($review->fresh()->created_by)->toBe($author->id)->and($review->fresh()->updated_by)->toBe($user->id);
    } else {
        $patch->assertForbidden();
        $upload->assertForbidden();
        $download->assertForbidden();
        expect($review->fresh()->hasil_review)->toBeNull();
    }
    $page = $this->get(route($module.'.reviu.index'))->assertOk();
    $allowed ? $page->assertSee('data-reviu-putaran', false) : $page->assertDontSee('data-reviu-putaran', false);
})->with(['snp', 'djsn'])->with(['pic', 'moderator', 'admin', 'viewer', 'super_admin'])->with([true, false]);

test('compilation permissions mirror SNP SBD requirements without granting review powers to admins', function (string $module) {
    $butir = workflowButir($module);
    foreach (['pic', 'moderator', 'admin', 'viewer'] as $role) {
        $user = workflowUser($role.'_'.$module);
        $this->actingAs($user);
        $data = ['tahap_kompilasi' => 'tanggapan', 'hasil_kompilasi' => 'Kompilasi uji'];
        $this->postJson(route($module.'.kompilasi.store', $butir), $data)->assertForbidden();
        DB::table('tb_unit_kerja')->where('id', 1)->update(['kode_unit' => 'SBD']);
        if (in_array($role, ['admin', 'moderator'], true)) {
            $this->post(route($module.'.kompilasi.store', $butir), $data)->assertRedirect()->assertSessionHasNoErrors();
            $butir = workflowButir($module);
        } else {
            $this->postJson(route($module.'.kompilasi.store', $butir), $data)->assertForbidden();
        }
        DB::table('tb_unit_kerja')->where('id', 1)->update(['kode_unit' => null]);
    }
})->with(['snp', 'djsn']);

test('only four new review values are accepted and decisions reopen or close the letter', function (string $module) {
    [$user, $butir, $review] = prepareCompilation($module);
    foreach (['dalam_proses_tindak_lanjut_direksi', 'selesai_tuntas', 'dalam_proses_reviu_dewas', 'invalid'] as $status) {
        $this->patchJson(route($module.'.reviu.update', $review), ['hasil_review' => 'Tidak valid', 'status' => $status])->assertUnprocessable()->assertJsonValidationErrors('status');
    }
    foreach (array_keys(ReviuStatus::options()) as $status) {
        if ($status !== 'belum_ditanggapi') {
            [$user, $butir, $review] = prepareCompilation($module);
        }
        $before = $butir->record->getRawOriginal();
        $result = $this->patchJson(route($module.'.reviu.update', $review), [
            'hasil_review' => SnpButirContent::PREFIX.'<p><em>Hasil aman</em><script>bad()</script></p>',
            'status' => $status, 'kompilasi_id' => 999, 'putaran_id' => 999, 'komite_id' => 999,
        ]);
        expect($result->getStatusCode())->toBe(302, $status.': '.$result->getContent());
        expect($review->fresh()->status)->toBe($status)->and($review->fresh()->hasil_review)->toContain('<em>Hasil aman</em>')->not->toContain('script');
        if ($status === 'tindak_lanjut_belum_sesuai') {
            expect($butir->record->fresh()->status)->toBe('proses_tindak_lanjut_belum_sesuai')
                ->and($butir->record->fresh()->putaranAktif->nomor_putaran)->toBe(1);
        } elseif (in_array($status, ['tindak_lanjut_sesuai', 'tidak_dapat_ditindaklanjuti'], true)) {
            expect($butir->record->fresh()->status)->toBe($status)
                ->and($butir->record->fresh()->closed_review_id)->toBe($review->id)
                ->and($butir->record->fresh()->closed_at)->not->toBeNull();
        } else {
            expect($butir->record->fresh()->getRawOriginal())->toBe($before);
        }
        if ($status !== 'belum_ditanggapi') {
            $this->patchJson(route($module.'.reviu.update', $review), ['hasil_review' => 'Timpa', 'status' => 'belum_ditanggapi'])->assertUnprocessable();
            $this->postJson(route($module.'.reviu.images.store', $review), ['image' => UploadedFile::fake()->image('terkunci.png')])->assertUnprocessable();
        }
    }
})->with(['snp', 'djsn']);

test('compilation and review are isolated by round and legacy review materials are preserved', function (string $module) {
    $user = workflowUser('super_admin');
    $this->actingAs($user);
    $butir = workflowButir($module);
    $legacy = workflowReview($module, $butir);
    $snapshot = $legacy->fresh()->getRawOriginal();
    $this->patchJson(route($module.'.reviu.update', $legacy), ['hasil_review' => 'Timpa riwayat', 'status' => 'tindak_lanjut_sesuai'])->assertUnprocessable();
    $this->postJson(route($module.'.kompilasi.store', $butir), ['tahap_kompilasi' => 'tanggapan', 'hasil_kompilasi' => 'Timpa lama'])->assertUnprocessable();
    $rounds = app(SuratPutaranService::class);
    $roundOne = $rounds->buka($butir->record->fresh(), $user->id);
    $data = ['tahap_kompilasi' => 'tanggapan', 'hasil_kompilasi' => 'Hasil putaran 1', 'putaran_id' => $roundOne->id];
    $this->post(route($module.'.kompilasi.store', $butir), $data)->assertSessionHasNoErrors()->assertRedirect();
    $compilation = $butir->kompilasis()->firstOrFail();
    $reviewClass = $module === 'snp' ? SnpReview::class : DjsnReview::class;
    $review = $reviewClass::where('kompilasi_id', $compilation->id)->firstOrFail();
    $rounds->arsipkan($roundOne, $user->id);
    $roundTwo = $rounds->buka($butir->record->fresh(), $user->id);
    $this->postJson(route($module.'.kompilasi.store', $butir), $data)->assertUnprocessable();
    $this->patchJson(route($module.'.reviu.update', $review), ['hasil_review' => 'Timpa putaran', 'status' => 'tindak_lanjut_sesuai'])->assertUnprocessable();
    $this->postJson(route($module.'.kompilasi.images.store', $butir), ['putaran_id' => $roundOne->id, 'image' => UploadedFile::fake()->image('stale.png')])->assertUnprocessable();
    $data['putaran_id'] = $roundTwo->id;
    $data['hasil_kompilasi'] = 'Hasil putaran 2';
    $this->post(route($module.'.kompilasi.store', $butir), $data)->assertSessionHasNoErrors()->assertRedirect();
    expect($butir->kompilasis()->count())->toBe(2)->and($compilation->fresh()->hasil_kompilasi)->toBe('Hasil putaran 1')
        ->and($legacy->fresh()->getRawOriginal())->toBe($snapshot);
    $this->get(route($module.'.kompilasi.index'))->assertOk()->assertSee('Hasil putaran 2')->assertDontSee('Hasil putaran 1');
})->with(['snp', 'djsn']);

test('review choices migration is repeatable and refuses rollback when either module uses new decisions', function () {
    $migration = require database_path('migrations/2026_10_22_085515_expand_snp_djsn_review_status_choices.php');
    $migration->up();
    [$author, $butir, $review] = prepareCompilation('djsn');
    $this->patch(route('djsn.reviu.update', $review), ['hasil_review' => 'Keputusan', 'status' => 'tidak_dapat_ditindaklanjuti'])->assertSessionHasNoErrors();
    expect(fn () => $migration->down())->toThrow(RuntimeException::class);
    expect($review->fresh()->status)->toBe('tidak_dapat_ditindaklanjuti');
});

test('DJSN and SNP compilation images use the same rich editor and round authorization', function (string $module) {
    $user = workflowUser('super_admin');
    $this->actingAs($user);
    $butir = workflowButir($module);
    $route = route($module.'.kompilasi.images.store', $butir);
    $url = $this->postJson($route, ['image' => UploadedFile::fake()->image('hasil.png')])->assertCreated()->json('url');
    $this->post(route($module.'.kompilasi.store', $butir), [
        'tahap_kompilasi' => 'tanggapan', 'hasil_kompilasi' => SnpButirContent::PREFIX.'<p><strong>Gambar kompilasi</strong><img src="'.$url.'" data-width="50" onerror="alert(1)"></p>',
    ])->assertRedirect()->assertSessionHasNoErrors();
    $compilation = $butir->kompilasis()->firstOrFail();
    expect($compilation->hasil_kompilasi)->toContain('data-width="50"', '<strong>')->not->toContain('onerror');
    $this->get($url)->assertOk();
    $this->postJson($route, ['image' => UploadedFile::fake()->image('locked.png')])->assertUnprocessable();
    $this->actingAs(workflowUser('viewer_'.$module));
    $this->postJson($route, ['image' => UploadedFile::fake()->image('forbidden.png')])->assertForbidden();
})->with(['snp', 'djsn']);

test('compiled due date proposals are reviewed without changing the original unit response', function (string $module) {
    [$author, $butir, $review] = prepareCompilation($module);
    $review->kompilasiPutaran->update(['ubah_tgl' => '2027-02-15', 'status_pengajuan_tgl' => 'pending']);
    $this->actingAs(workflowUser('pic_'.$module));
    $this->patch(route($module.'.reviu.update', $review), [
        'hasil_review' => 'Pengajuan disetujui', 'status' => 'belum_ditanggapi', 'status_pengajuan_tgl' => 'disetujui',
    ])->assertRedirect()->assertSessionHasNoErrors();
    expect($review->kompilasiPutaran->fresh()->status_pengajuan_tgl)->toBe('disetujui')
        ->and($butir->record->fresh()->jth_tempo->format('Y-m-d'))->toBe('2027-02-15');
})->with(['snp', 'djsn']);

test('inactive roles or committees and search filters cannot bypass review authority', function (string $module) {
    [$author, $butir, $review] = prepareCompilation($module);
    $user = workflowUser('pic_'.$module);
    $this->actingAs($user);
    DB::table('tb_komite')->insert(['id' => 2, 'nama_komite' => 'Komite lain']);
    $review->update(['komite_id' => 2, 'hasil_review' => 'RAHASIA-KOMITE-LAIN']);
    $this->get(route($module.'.reviu.index', ['komite_id' => 2, 'keyword' => 'RAHASIA-KOMITE-LAIN']))->assertOk()->assertViewHas('reviews', fn ($rows) => $rows->total() === 0);
    $review->update(['komite_id' => 1, 'hasil_review' => null]);
    $user->komite()->updateExistingPivot(1, ['status' => 'inactive']);
    $this->patchJson(route($module.'.reviu.update', $review), ['hasil_review' => 'Tidak sah', 'status' => 'belum_ditanggapi'])->assertForbidden();
    $user->komite()->updateExistingPivot(1, ['status' => 'active']);
    $user->roleTypes()->updateExistingPivot($user->roleTypes()->firstOrFail()->id, ['status' => 'inactive']);
    $this->patchJson(route($module.'.reviu.update', $review), ['hasil_review' => 'Tidak sah', 'status' => 'belum_ditanggapi'])->assertForbidden();
    expect($review->fresh()->hasil_review)->toBeNull();
})->with(['snp', 'djsn']);

test('the actual compilation and review modals keep toolbar content and post the correct form', function (string $module) {
    [$user, $butir, $review] = prepareCompilation($module);
    $other = workflowButir($module);
    $pages = [
        ['kind' => 'kompilasi', 'action' => '/'.$module.'/kompilasi/'.$other->id, 'html' => $this->get(route($module.'.kompilasi.index'))->assertOk()->getContent()],
        ['kind' => 'reviu', 'action' => '/'.$module.'/reviu/'.$review->id, 'html' => $this->get(route($module.'.reviu.index'))->assertOk()->getContent()],
    ];
    $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
    $process = new Process(['node', base_path('tests/Unit/ReviuPutaran.browser.mjs')], base_path());
    $process->setInput(json_encode([
        'pages' => $pages, 'statuses' => array_keys(ReviuStatus::options()),
        'css' => $manifest['resources/css/app.css']['file'], 'js' => $manifest['resources/js/app.js']['file'],
        'screenshot' => base_path('tmp/reviu-stage6a-'.$module.'.png'),
    ]));
    $process->setTimeout(60)->run();
    expect($process->isSuccessful())->toBeTrue($process->getOutput().$process->getErrorOutput());
})->with(['snp', 'djsn']);
