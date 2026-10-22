<?php

use App\Models\DjsnButir;
use App\Models\DjsnReview;
use App\Models\DjsnTanggapan;
use App\Models\SnpButir;
use App\Models\SnpReview;
use App\Models\SnpTanggapan;
use App\Models\User;
use App\Services\ReviuBahanPreviewService;
use App\Services\SnpButirContent;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
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
    $this->travelTo(now()->setDate(2026, 10, 22)->setTime(8, 0));
});

/** @return array{User, SnpButir|DjsnButir, array<int, User>} */
function materialPreviewFixture(string $module): array
{
    $compiler = workflowUser('super_admin');
    test()->actingAs($compiler);
    $butir = workflowButir($module);
    DB::table('tb_unit_kerja')->where('id', 1)->update(['kode_unit' => 'A', 'nama_unit' => 'Unit A']);
    $writers = [];
    foreach ([1 => 'A', 2 => 'B', 3 => 'C'] as $unitId => $code) {
        if ($unitId !== 1) {
            DB::table('tb_unit_kerja')->insert(['id' => $unitId, 'direktorat_id' => 1, 'kode_unit' => $code, 'nama_unit' => 'Unit '.$code, 'status' => 'active']);
            $butir->butirPics()->create(['jenis_pic' => 'pendukung', 'unit_kerja_id' => $unitId]);
        }
        $writer = workflowUser('pic_'.$module);
        $writer->update(['name' => 'Pengisi '.$code]);
        $writer->unitKerja()->sync([$unitId => ['status' => 'active']]);
        $writers[$unitId] = $writer;
    }
    $typeId = DB::table('tb_type')->insertGetId(['code' => $module, 'name' => strtoupper($module)]);
    DB::table('tb_role_type')->where('name', 'pic_'.$module)->update(['type_id' => $typeId]);

    return [$compiler, $butir, $writers];
}

function materialPreviewRespond(string $module, SnpButir|DjsnButir $butir, User $writer, ?int $roundId, string $content): SnpTanggapan|DjsnTanggapan
{
    $pic = $butir->butirPics()->where('unit_kerja_id', $writer->unitKerjaIds()[0])->firstOrFail();
    $class = $module === 'snp' ? SnpTanggapan::class : DjsnTanggapan::class;

    return $class::create([
        'id_butir_'.$module => $butir->{'id_butir_'.$module}, 'putaran_id' => $roundId, 'butir_pic_id' => $pic->id,
        'tanggapan' => $content, 'deliverables' => 'Deliverables <literal>', 'created_by' => $writer->id,
    ]);
}

function materialPreviewCompile(string $module, SnpButir|DjsnButir $butir, User $compiler, ?int $roundId, string $content): SnpReview|DjsnReview
{
    test()->actingAs($compiler)->post(route($module.'.kompilasi.store', $butir), [
        'putaran_id' => $roundId, 'tahap_kompilasi' => 'tanggapan', 'hasil_kompilasi' => $content, 'deliverables' => 'Hasil <literal>',
    ])->assertRedirect()->assertSessionHasNoErrors();

    return $butir->reviews()->latest('id')->firstOrFail();
}

function materialPreviewSnapshot(string $module): array
{
    return collect(['tb_record', 'tb_surat_putaran', 'tb_tanggapan', 'tb_kompilasi', 'tb_review', 'tb_tindak_lanjut'])
        ->mapWithKeys(fn (string $table): array => [$table => DB::connection('mysql_'.$module)->table($table)->orderBy('id')->get()->map(fn ($row): array => (array) $row)->all()])->all();
}

test('modal materials contain only the relevant unit responses metadata and linked compilation without writing data', function (string $module, int $count) {
    [$compiler, $butir, $writers] = materialPreviewFixture($module);
    $ids = [];
    for ($number = 1; $number <= $count; $number++) {
        $this->travel(1)->minutes();
        $ids[] = materialPreviewRespond($module, $butir, $writers[$number], null, 'Jawaban '.$number.' <b>literal</b>')->id;
    }
    $other = workflowButir($module);
    materialPreviewRespond($module, $other, $writers[1], null, 'RAHASIA SURAT LAIN');
    $snapshot = materialPreviewSnapshot($module);
    $page = $this->get(route($module.'.kompilasi.index', ['keyword' => $butir->{'id_butir_'.$module}]))->assertOk();
    $preview = $page->viewData('previewBahan')[$butir->id];
    expect(array_column($preview['responses'], 'id'))->toBe($ids)
        ->and($preview['late_responses'])->toBe([])->and($preview['round_label'])->toBe('Putaran awal')
        ->and($preview['butir_id'])->toBe($butir->{'id_butir_'.$module})
        ->and($preview['letter_number'])->toBe($butir->record->nomor_surat)
        ->and($preview['compilation'])->toBeNull()->and(materialPreviewSnapshot($module))->toBe($snapshot);
    foreach ($preview['responses'] as $index => $response) {
        expect($response['unit'])->toBe(chr(65 + $index).' - Unit '.chr(65 + $index))
            ->and($response['author'])->toBe('Pengisi '.chr(65 + $index))
            ->and($response['date'])->toBe('22/10/2026 08:0'.($index + 1))
            ->and($response['content'])->toBe('Jawaban '.($index + 1).' <b>literal</b>')
            ->and($response['round_label'])->toBe('Putaran awal');
    }
    $review = materialPreviewCompile($module, $butir, $compiler, $preview['round_id'], SnpButirContent::PREFIX.'<p><strong>Kompilasi sumber</strong></p>');
    $snapshot = materialPreviewSnapshot($module);
    $page = $this->get(route($module.'.reviu.index', ['keyword' => $butir->{'id_butir_'.$module}]))->assertOk()->assertSee('Preview Hasil Kompilasi');
    $reviewPreview = $page->viewData('previewBahan')[$review->id];
    expect($reviewPreview['responses'])->toBe($preview['responses'])
        ->and($reviewPreview['compilation']['id'])->toBe($review->kompilasi_id)
        ->and($reviewPreview['compilation']['content'])->toContain('<strong>Kompilasi sumber</strong>')
        ->and($reviewPreview['compilation']['author'])->toBe($compiler->name)
        ->and($reviewPreview['compilation']['date'])->toBe(now()->format('d/m/Y H:i'))
        ->and(materialPreviewSnapshot($module))->toBe($snapshot);
    $page->assertDontSee('RAHASIA SURAT LAIN');
})->with(['snp', 'djsn'])->with([0, 1, 3]);

test('materials follow the selected review round through two loops and keep late completion separate', function (string $module) {
    [$compiler, $butir, $writers] = materialPreviewFixture($module);
    $service = app(ReviuBahanPreviewService::class);
    $old = [];
    $roundId = null;
    foreach ([0, 1] as $number) {
        $response = materialPreviewRespond($module, $butir, $writers[1], $roundId, 'Jawaban lama '.$number);
        $review = materialPreviewCompile($module, $butir, $compiler, $response->putaran_id, 'Kompilasi lama '.$number);
        $old[] = [$review, $response];
        $this->patch(route($module.'.reviu.update', $review), ['hasil_review' => 'Perbaiki', 'status' => 'tindak_lanjut_belum_sesuai'])->assertSessionHasNoErrors();
        $roundId = $butir->record->fresh()->putaran_aktif_id;
        $preview = $service->forCompilation($butir->fresh(), $compiler);
        expect($preview['responses'])->toBe([])->and($preview['round_label'])->toBe('Putaran '.($number + 1));
    }
    $current = materialPreviewRespond($module, $butir, $writers[1], $roundId, 'Jawaban terbaru');
    $review = materialPreviewCompile($module, $butir, $compiler, $roundId, 'Kompilasi terbaru');
    $this->patch(route($module.'.reviu.update', $review), ['hasil_review' => 'Sesuai', 'status' => 'tindak_lanjut_sesuai'])->assertSessionHasNoErrors();
    $late = materialPreviewRespond($module, $butir, $writers[2], $roundId, 'Kelengkapan terlambat');
    $snapshot = materialPreviewSnapshot($module);
    $preview = $service->forReview($review->fresh(), $compiler);
    expect(array_column($preview['responses'], 'id'))->toBe([$current->id])
        ->and(array_column($preview['late_responses'], 'id'))->toBe([$late->id])
        ->and($preview['late_responses'][0]['after_close'])->toBeTrue()
        ->and($preview['late_responses'][0]['after_close_at'])->toBe(now()->format('d/m/Y H:i'))
        ->and($preview['round_label'])->toBe('Putaran 2')
        ->and($preview['compilation']['content'])->toBe('Kompilasi terbaru');
    foreach ($old as $number => [$oldReview, $response]) {
        $data = $service->forReview($oldReview->fresh(), $compiler);
        expect(array_column($data['responses'], 'id'))->toBe([$response->id])
            ->and($data['late_responses'])->toBe([])->and($data['compilation']['content'])->toBe('Kompilasi lama '.$number)
            ->and($data['round_label'])->toBe($number ? 'Putaran 1' : 'Putaran awal');
        $page = $this->get(route($module.'.reviu.index', ['keyword' => 'Kompilasi lama '.$number]))->assertOk();
        expect($page->viewData('previewBahan')[$oldReview->id])->toBe($data);
    }
    expect(materialPreviewSnapshot($module))->toBe($snapshot);
})->with(['snp', 'djsn']);

test('preview follows existing module and committee read permissions without granting editing rights', function (string $module) {
    [$compiler, $butir, $writers] = materialPreviewFixture($module);
    materialPreviewRespond($module, $butir, $writers[2], null, 'BAHAN KOMITE UJI');
    $review = materialPreviewCompile($module, $butir, $compiler, null, 'Kompilasi berwenang');
    $service = app(ReviuBahanPreviewService::class);
    foreach (['pic', 'moderator', 'admin', 'viewer'] as $role) {
        $reader = workflowUser($role.'_'.$module);
        $page = $this->actingAs($reader)->get(route($module.'.reviu.index'))->assertOk();
        expect($page->viewData('previewBahan')[$review->id]['responses'][0]['author'])->toBe('Pengisi B');
        if (in_array($role, ['admin', 'viewer'], true)) {
            $page->assertDontSee('data-reviu-putaran', false);
            $this->patchJson(route($module.'.reviu.update', $review), ['hasil_review' => 'Tidak berwenang', 'status' => 'tindak_lanjut_sesuai'])->assertForbidden();
        }
        DB::table('tb_komite')->insertOrIgnore(['id' => 2, 'nama_komite' => 'Komite Lain']);
        $reader->komite()->sync([2 => ['status' => 'active']]);
        expect(fn () => $service->forReview($review->fresh(), $reader->fresh()))->toThrow(HttpException::class);
        $page = $this->get(route($module.'.reviu.index'))->assertOk();
        expect($page->viewData('previewBahan'))->toBe([]);
        $page->assertDontSee('BAHAN KOMITE UJI');
    }
    $outsider = workflowUser('pic_'.($module === 'snp' ? 'djsn' : 'snp'));
    $this->actingAs($outsider)->get(route($module.'.kompilasi.index'))->assertForbidden();
    $this->get(route($module.'.reviu.index'))->assertForbidden();
    expect(fn () => $service->forCompilation($butir->fresh(), $outsider))->toThrow(HttpException::class);
})->with(['snp', 'djsn']);

test('preview does not use a compilation linked to another butir or round and preserves unassigned legacy response metadata', function (string $module) {
    [$compiler, $butir, $writers] = materialPreviewFixture($module);
    $response = materialPreviewRespond($module, $butir, $writers[1], null, 'Riwayat unit tidak diketahui');
    $db = DB::connection('mysql_'.$module);
    $db->table('tb_tanggapan')->where('id', $response->id)->update(['unit_kerja_id' => null, 'butir_pic_id' => null, 'created_by' => null, 'created_at' => null, 'putaran_id' => null]);
    $preview = app(ReviuBahanPreviewService::class)->forCompilation($butir->fresh(), $compiler);
    expect($preview['responses'][0]['unit'])->toBe('Unit tidak tercatat (data lama)')
        ->and($preview['responses'][0]['author'])->toBe('Pengisi tidak tercatat (data lama)')
        ->and($preview['responses'][0]['date'])->toBe('-');
    $review = materialPreviewCompile($module, $butir, $compiler, null, 'Kompilasi asli');
    $other = workflowButir($module);
    $otherReview = materialPreviewCompile($module, $other, $compiler, null, 'KOMPILASI SURAT LAIN');
    $db->table('tb_review')->where('id', $review->id)->update(['kompilasi_id' => $otherReview->kompilasi_id]);
    $result = app(ReviuBahanPreviewService::class)->forReview($review->fresh(), $compiler);
    expect($result['compilation'])->toBeNull()->and($result['responses'])->toHaveCount(1);
    $this->get(route($module.'.reviu.index', ['keyword' => $butir->{'id_butir_'.$module}]))->assertOk()->assertDontSee('KOMPILASI SURAT LAIN');
    $db->table('tb_review')->where('id', $review->id)->update(['kompilasi_id' => $review->kompilasi_id]);
    $this->patch(route($module.'.reviu.update', $review), ['hasil_review' => 'Perbaiki', 'status' => 'tindak_lanjut_belum_sesuai'])->assertSessionHasNoErrors();
    $roundId = $butir->record->fresh()->putaran_aktif_id;
    $current = materialPreviewRespond($module, $butir, $writers[3], $roundId, 'Jawaban putaran baru');
    $currentReview = materialPreviewCompile($module, $butir, $compiler, $roundId, 'Kompilasi baru');
    $db->table('tb_review')->where('id', $currentReview->id)->update(['kompilasi_id' => $review->kompilasi_id]);
    $result = app(ReviuBahanPreviewService::class)->forReview($currentReview->fresh(), $compiler);
    expect($result['compilation'])->toBeNull()->and(array_column($result['responses'], 'id'))->toBe([$current->id]);
})->with(['snp', 'djsn']);

test('preview remains behind the new workflow activation flag', function () {
    [$compiler, $butir] = materialPreviewFixture('djsn');
    $review = materialPreviewCompile('djsn', $butir, $compiler, null, 'Kompilasi');
    config(['sidewas.reviu_putaran_enabled' => false]);
    $this->get(route('djsn.kompilasi.index'))->assertNotFound();
    $this->get(route('djsn.reviu.index'))->assertOk()->assertDontSee('data-bahan-preview', false);
    expect(fn () => app(ReviuBahanPreviewService::class)->forCompilation($butir->fresh(), $compiler))->toThrow(HttpException::class);
    expect(fn () => app(ReviuBahanPreviewService::class)->forReview($review->fresh(), $compiler))->toThrow(HttpException::class);
});

test('date approval by the reviewer does not replace the compilation author or original creation date in preview', function (string $module) {
    [$compiler, $butir] = materialPreviewFixture($module);
    $review = materialPreviewCompile($module, $butir, $compiler, null, 'Kompilasi sumber');
    $review->kompilasiPutaran->update(['ubah_tgl' => '2026-12-31']);
    $createdAt = $review->kompilasiPutaran->created_at->format('d/m/Y H:i');
    $reviewer = workflowUser('moderator_'.$module);
    $this->travel(1)->days();
    $this->actingAs($reviewer)->patch(route($module.'.reviu.update', $review), [
        'hasil_review' => 'Masih dipelajari', 'status' => 'belum_ditanggapi', 'status_pengajuan_tgl' => 'disetujui',
    ])->assertRedirect()->assertSessionHasNoErrors();
    expect($review->kompilasiPutaran->fresh()->updated_by)->toBe($reviewer->id);
    $preview = app(ReviuBahanPreviewService::class)->forReview($review->fresh(), $reviewer);
    expect($preview['compilation']['author'])->toBe($compiler->name)
        ->and($preview['compilation']['date'])->toBe($createdAt);
})->with(['snp', 'djsn']);

test('the browser previews zero one or three responses and retains every unsaved field while navigating', function (string $module) {
    [$compiler, $first, $writers] = materialPreviewFixture($module);
    $pages = [];
    $images = [];
    foreach ([0, 1, 3] as $count) {
        $butir = $count === 0 ? $first : workflowButir($module);
        if ($count !== 0) {
            foreach ([2, 3] as $unitId) {
                $butir->butirPics()->create(['jenis_pic' => 'pendukung', 'unit_kerja_id' => $unitId]);
            }
        }
        $imageHtml = '';
        if ($count === 3) {
            $filename = Str::uuid().'.png';
            $path = $module.'-images/'.$butir->record->id.'/'.$filename;
            $fixtureImage = UploadedFile::fake()->image('referensi.png', 80, 40);
            Storage::disk('local')->put($path, file_get_contents($fixtureImage->getPathname()));
            $url = route($module.'.butir-images.show', ['record' => $butir->record->id, 'filename' => $filename], false);
            $this->get($url)->assertOk();
            $images[$url] = base64_encode(Storage::disk('local')->get($path));
            $imageHtml = '<img src="'.$url.'" data-width="50">';
        }
        $dates = [];
        for ($number = 1; $number <= $count; $number++) {
            $this->travel(1)->minutes();
            $content = $count === 3 && $number === 1
                ? SnpButirContent::PREFIX.'<p><strong>Tanggapan panjang 1</strong></p>'.str_repeat('<p>Isi lengkap tanggapan yang dapat digulir.</p>', 40).$imageHtml.'<script>window.previewInjected = 1</script>'
                : 'Jawaban '.$number.' <b>literal</b>';
            $response = materialPreviewRespond($module, $butir, $writers[$number], null, $content);
            $dates[] = $response->created_at->format('d/m/Y H:i');
        }
        $routeParams = ['keyword' => $butir->{'id_butir_'.$module}];
        $pageData = ['count' => $count, 'dates' => $dates, 'butirId' => $butir->{'id_butir_'.$module}];
        $pages[] = $pageData + ['kind' => 'kompilasi', 'action' => '/'.$module.'/kompilasi/'.$butir->id, 'html' => $this->get(route($module.'.kompilasi.index', $routeParams))->assertOk()->getContent()];
        $review = materialPreviewCompile($module, $butir, $compiler, $response->putaran_id ?? null, SnpButirContent::PREFIX.'<p><em>Kompilasi referensi</em></p>'.$imageHtml);
        $pages[] = $pageData + ['kind' => 'reviu', 'action' => '/'.$module.'/reviu/'.$review->id, 'compilationDate' => $review->kompilasiPutaran->updated_at->format('d/m/Y H:i'), 'html' => $this->get(route($module.'.reviu.index', $routeParams))->assertOk()->getContent()];
    }
    $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
    $upload = UploadedFile::fake()->create('bahan.pdf', 1, 'application/pdf');
    $process = new Process(['node', base_path('tests/Unit/ReviuBahanPreview.browser.mjs')], base_path());
    $process->setInput(json_encode([
        'pages' => $pages, 'images' => $images, 'compiler' => $compiler->name, 'upload' => $upload->getPathname(),
        'css' => $manifest['resources/css/app.css']['file'], 'js' => $manifest['resources/js/app.js']['file'],
        'screenshot' => base_path('tmp/reviu-bahan-preview-'.$module),
    ]));
    $process->setTimeout(90)->run();
    expect($process->isSuccessful())->toBeTrue($process->getOutput().$process->getErrorOutput());
})->with(['snp', 'djsn']);
