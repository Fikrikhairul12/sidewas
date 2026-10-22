<?php

use App\Enums\SuratStatus;
use App\Exports\DjsnReportExport;
use App\Exports\SnpReportExport;
use App\Models\DjsnButir;
use App\Models\DjsnRecord;
use App\Models\DjsnTanggapan;
use App\Models\SnpButir;
use App\Models\SnpKompilasi;
use App\Models\SnpRecord;
use App\Models\SnpReview;
use App\Models\SnpTanggapan;
use App\Services\SnpButirContent;
use Dompdf\Dompdf;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\Process\Process;
use Tests\TestCase;

uses(TestCase::class);

require_once __DIR__.'/WorkflowTestSetup.php';

beforeEach(function () {
    $this->withoutVite();
    setUpWorkflowTestDatabase();
});

test('SNP compilation saves safe rich content and uploads only for authorized editable compilations', function () {
    $this->actingAs(workflowUser('super_admin'));
    $butir = workflowButir('snp');
    $uploadRoute = route('snp.kompilasi.images.store', ['butir' => $butir, 'tahap_kompilasi' => 'tanggapan', 'putaran_tl' => 1]);
    $url = $this->postJson($uploadRoute, ['image' => UploadedFile::fake()->image('kompilasi.png')])->assertCreated()->json('url');
    $value = SnpButirContent::PREFIX.'<p style="text-align:center"><strong>KOMPILASI</strong><img src="'.$url.'" data-width="50" onerror="alert(1)"></p><script>bad()</script>';
    $this->post(route('snp.kompilasi.store', $butir), ['tahap_kompilasi' => 'tanggapan', 'putaran_tl' => 1, 'hasil_kompilasi' => $value])->assertRedirect(route('snp.kompilasi.index'))->assertSessionHasNoErrors();
    $kompilasi = SnpKompilasi::firstOrFail();
    expect($kompilasi->hasil_kompilasi)->toContain('<strong>KOMPILASI</strong>', 'data-width="50"')->not->toContain('onerror', 'script')
        ->and($butir->record->fresh()->status)->toBe('terbit')
        ->and($kompilasi->status)->toBe('dalam_proses_reviu_dewas')
        ->and(SnpReview::firstOrFail()->status)->toBe('belum_ditanggapi')
        ->and($butir->fresh()->butir_snp)->toBe('Isi butir lama');
    $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/png');
    $this->postJson($uploadRoute, ['image' => UploadedFile::fake()->image('lagi.png')])->assertUnprocessable();
    $this->actingAs(workflowUser('pic_snp'));
    $this->postJson($uploadRoute, ['image' => UploadedFile::fake()->image('pic.png')])->assertForbidden();
});

test('reviewers can upload and save rich results without perekaman write access and legacy text stays literal', function (string $module) {
    $user = workflowUser('pic_'.$module);
    $this->actingAs($user);
    $butir = workflowButir($module);
    $review = workflowReview($module, $butir);
    $uploadRoute = route($module.'.reviu.images.store', $review);
    $url = $this->postJson($uploadRoute, ['image' => UploadedFile::fake()->image('review.png')])->assertCreated()->json('url');
    $this->postJson(route($module.'.butir-images.store', $butir->record), ['image' => UploadedFile::fake()->image('perekaman.png')])->assertForbidden();
    $value = SnpButirContent::PREFIX.'<p style="text-align:right"><em>REVIU</em><span style="font-size:24px">Besar</span></p><ol><li><p>Poin</p></li></ol><p><img src="'.$url.'" data-width="25"></p><script>bad()</script>';
    $this->patch(route($module.'.reviu.update', $review), ['hasil_review' => $value, 'status' => 'belum_ditanggapi'])->assertSessionHasNoErrors()->assertRedirect();
    expect($review->fresh()->hasil_review)->toContain('<em>REVIU</em>', 'font-size:24px', '<ol>', 'data-width="25"')->not->toContain('script');
    $this->get($url)->assertOk();
    $page = $this->get(route($module.'.reviu.index'))->assertOk()->assertSee('name="hasil_review"', false)->assertSee('<em>REVIU</em>', false)->assertDontSee('&lt;p', false);
    expect(str_contains(str_replace('\\/', '/', $page->getContent()), $module.'/reviu/'.$review->id.'/gambar'))->toBeTrue();
    $legacy = "Teks lama <literal> & asli\nBaris dua";
    $this->patch(route($module.'.reviu.update', $review), ['hasil_review' => $legacy, 'status' => 'belum_ditanggapi'])->assertSessionHasNoErrors();
    expect($review->fresh()->hasil_review)->toBe($legacy);
    $this->get(route($module.'.reviu.index'))->assertSee('Teks lama &lt;literal&gt; &amp; asli', false);
})->with(['snp', 'djsn']);

test('response forms retain plain textareas and save literal text without a toolbar', function (string $module) {
    $this->actingAs(workflowUser('pic_'.$module));
    $butir = workflowButir($module);
    $picId = $butir->butirPics()->where('jenis_pic', 'utama')->firstOrFail()->id;
    $value = "Tanggapan <literal> & asli\nBaris kedua.";
    $data = ['butir_pic_id' => $picId, 'tanggapan' => $value, 'deliverables' => 'Hasil unit'];
    $this->post(route($module.'.tanggapan.store', $butir), $data)->assertSessionHasNoErrors()->assertRedirect();
    $class = $module === 'snp' ? SnpTanggapan::class : DjsnTanggapan::class;
    $tanggapan = $class::firstOrFail();
    expect($tanggapan->tanggapan)->toBe($value);
    $page = $this->get(route($module.'.tanggapan.index'))->assertOk()->assertSee('Tanggapan &lt;literal&gt; &amp; asli', false)->assertDontSee('data-snp-editor', false);
    $document = new DOMDocument;
    @$document->loadHTML($page->getContent());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//textarea[@name="tanggapan"]'))->toHaveCount(2);
    if ($module === 'snp') {
        $this->actingAs(workflowUser('admin_snp'));
        $updated = "Revisi <literal> & asli\nBaris kedua.";
        $this->patch(route('snp.tanggapan.update', $tanggapan), ['tanggapan' => $updated, 'deliverables' => 'Revisi'])->assertSessionHasNoErrors();
        expect($tanggapan->fresh()->tanggapan)->toBe($updated);
    }
})->with(['snp', 'djsn']);

test('review rich results reject empty content foreign images unsafe files unauthorized committees and locked stages', function (string $module) {
    $this->actingAs(workflowUser('pic_'.$module));
    $review = workflowReview($module, workflowButir($module));
    $uploadRoute = route($module.'.reviu.images.store', $review);
    foreach ([UploadedFile::fake()->create('bad.svg', 1, 'image/svg+xml'), UploadedFile::fake()->image('large.png')->size(2049)] as $file) {
        $this->postJson($uploadRoute, ['image' => $file])->assertUnprocessable()->assertJsonValidationErrors('image');
    }
    foreach ([SnpButirContent::PREFIX.'<p><br></p>', SnpButirContent::PREFIX.'<img src="/'.$module.'/perekaman/999/gambar/12345678-1234-1234-1234-123456789abc.png">', str_repeat('x', 500001)] as $invalid) {
        $this->patch(route($module.'.reviu.update', $review), ['hasil_review' => $invalid, 'status' => 'belum_ditanggapi'])->assertSessionHasErrors('hasil_review');
    }
    expect($review->fresh()->hasil_review)->toBe('Reviu lama');
    $review->update(['komite_id' => 2]);
    $this->postJson($uploadRoute, ['image' => UploadedFile::fake()->image('wrong.png')])->assertForbidden();
    $review->update(['komite_id' => 1, 'status' => 'dalam_proses_tindak_lanjut_direksi']);
    $this->postJson($uploadRoute, ['image' => UploadedFile::fake()->image('locked.png')])->assertUnprocessable();
    $this->patch(route($module.'.reviu.update', $review), ['hasil_review' => 'Tidak boleh berubah', 'status' => 'belum_ditanggapi'])->assertSessionHas('error');
    expect($review->fresh()->hasil_review)->toBe('Reviu lama');
})->with(['snp', 'djsn']);

test('response image endpoints are unavailable while rich editing is limited to compilation and review', function (string $path) {
    $this->actingAs(workflowUser('super_admin'));
    $this->postJson($path, ['image' => UploadedFile::fake()->image('tanggapan.png')])->assertNotFound();
    expect(Storage::disk('local')->allFiles())->toBeEmpty();
})->with(['/snp/tanggapan/1/gambar', '/snp/tanggapan/1/gambar-edit', '/djsn/tanggapan/1/gambar']);

test('workflow content migration preserves legacy values and prevents truncating rollback', function () {
    $review = workflowReview('snp', workflowButir('snp'));
    $migration = require database_path('migrations/2026_10_21_105635_expand_snp_djsn_workflow_content.php');
    $migration->up();
    expect($review->fresh()->hasil_review)->toBe('Reviu lama');
    $long = SnpButirContent::PREFIX.'<p>'.str_repeat('Isi panjang ', 7000).'</p>';
    $review->update(['hasil_review' => $long]);
    expect(fn () => $migration->down())->toThrow(RuntimeException::class)
        ->and($review->fresh()->hasil_review)->toBe($long);
    $review->update(['hasil_review' => 'Pendek']);
    $migration->down();
    expect($review->fresh()->hasil_review)->toBe('Pendek');
});

test('workflow editors use their own image endpoint and preserve all butir editor interactions in the browser', function (string $module, string $field, string $endpoint) {
    $imageName = '12345678-1234-1234-1234-123456789abc.png';
    $image = UploadedFile::fake()->image('fixture.png', 600, 400);
    Storage::disk('local')->put($module.'-images/1/'.$imageName, file_get_contents($image->getPathname()));
    $imageUrl = '/'.$module.'/perekaman/1/gambar/'.$imageName;
    $initial = SnpButirContent::PREFIX.'<p style="text-align:center"><strong>Isi awal</strong></p><p><img src="'.$imageUrl.'" data-width="50"></p>';
    $html = Blade::render(<<<'BLADE'
        <form id="editorForm" x-data="{ selected: 0, drafts: [@js($initial), 'Teks lama <literal>'], record: 1, uploadUrl: @js($endpoint) }" @submit.prevent>
            <button type="button" id="switch" @click="selected = selected === 0 ? 1 : 0">Ganti hasil</button>
            <div @snp-editor-change.stop="drafts[selected] = $event.detail">
                <x-snp-butir-editor :module="$module" :name="$field" label="Hasil" content-expression="drafts[selected]" record-expression="record" key-expression="selected" upload-url-expression="uploadUrl" />
            </div>
            <button type="submit" id="save">Simpan</button>
            <x-snp-butir-preview id="richPreview" content-expression="drafts[selected]" :expanded="true" />
        </form>
        <x-snp-butir-reader />
        BLADE, compact('initial', 'module', 'field', 'endpoint'));
    $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
    $process = new Process(['node', base_path('tests/Unit/SnpRichEditor.browser.mjs')], base_path());
    $process->setInput(json_encode(['html' => $html, 'module' => $module, 'field' => $field, 'uploadUrl' => $endpoint, 'uploadPath' => Storage::disk('local')->path($module.'-images/1/'.$imageName), 'css' => $manifest['resources/css/app.css']['file'], 'js' => $manifest['resources/js/app.js']['file']]));
    $process->setTimeout(90)->run();
    expect($process->isSuccessful())->toBeTrue($process->getOutput().$process->getErrorOutput());
})->with([
    ['snp', 'hasil_kompilasi', '/snp/kompilasi/1/gambar?tahap_kompilasi=tanggapan&putaran_tl=1'],
    ['snp', 'hasil_review', '/snp/reviu/1/gambar'],
    ['djsn', 'hasil_review', '/djsn/reviu/1/gambar'],
]);

test('standard and custom workflow PDF reports retain formatted results and embedded images', function (string $module) {
    $butir = workflowButir($module);
    $review = workflowReview($module, $butir);
    $name = '12345678-1234-1234-1234-123456789abc.png';
    $file = UploadedFile::fake()->image('pdf.png', 400, 200);
    Storage::disk('local')->put($module.'-images/1/'.$name, file_get_contents($file->getPathname()));
    $rich = SnpButirContent::PREFIX.'<p style="text-align:center"><strong>AWAL-REVIU</strong></p><ol><li><p>'.str_repeat('Isi reviu lengkap. ', 160).'</p></li></ol><p><img src="/'.$module.'/perekaman/1/gambar/'.$name.'" data-width="50"></p><p>AKHIR-REVIU</p>';
    $review->update(['hasil_review' => $rich]);
    $responseClass = $module === 'snp' ? SnpTanggapan::class : DjsnTanggapan::class;
    $responseClass::findOrFail($review->id_tanggapan)->update(['tanggapan' => 'AWAL-TANGGAPAN isi tanggapan lama AKHIR-TANGGAPAN']);
    if ($module === 'snp') {
        SnpKompilasi::create(['id_butir_snp' => $butir->id_butir_snp, 'tahap_kompilasi' => 'tanggapan', 'hasil_kompilasi' => str_replace('REVIU', 'KOMPILASI', $rich)]);
    }
    foreach (['pdf', 'pdf-custom'] as $template) {
        $fields = $module === 'snp' ? ['id_butir', 'tanggapan_unit', 'kompilasi_tanggapan', 'hasil_reviu'] : ['id_butir', 'tanggapan_tl', 'hasil_reviu'];
        $html = view('layouts.'.$module.'.report.'.$template, ['records' => collect([$butir->record]), 'selectedFields' => $fields, 'fieldLabels' => []])->render();
        expect($html)->toContain('data:image/png;base64', 'AWAL-REVIU', 'AKHIR-REVIU', 'font-weight:bold')->not->toContain('&lt;p', 'snp-rich:v1');
        if ($module === 'snp') {
            expect($html)->toContain('AWAL-KOMPILASI', 'AKHIR-KOMPILASI');
        }
        if ($module === 'djsn' || $template === 'pdf-custom') {
            expect($html)->toContain('AWAL-TANGGAPAN', 'AKHIR-TANGGAPAN');
        }
        $dompdf = new Dompdf;
        $dompdf->setPaper('legal', 'landscape');
        $dompdf->loadHtml($html);
        $dompdf->render();
        expect(strlen($dompdf->output()))->toBeGreaterThan(1000);
    }
})->with(['snp', 'djsn']);

test('Excel keeps workflow text formatting and multiple images in their related result columns', function (string $module, bool $includeButir) {
    $butir = workflowButir($module);
    $review = workflowReview($module, $butir);
    $name = '12345678-1234-1234-1234-123456789abc.png';
    $file = UploadedFile::fake()->image('excel.png', 400, 200);
    Storage::disk('local')->put($module.'-images/1/'.$name, file_get_contents($file->getPathname()));
    $rich = SnpButirContent::PREFIX.'<p><strong>AWAL-HASIL</strong></p><p><img src="/'.$module.'/perekaman/1/gambar/'.$name.'"></p><p>ANTAR-GAMBAR</p><p><img src="/'.$module.'/perekaman/1/gambar/'.$name.'" data-width="50"></p><p>AKHIR-HASIL</p>';
    $butir->update(['butir_'.$module => SnpButirContent::PREFIX.'<p>BUTIR PERTAMA</p><p><img src="/'.$module.'/perekaman/1/gambar/'.$name.'"></p>']);
    $second = $butir->replicate();
    $second->{'id_butir_'.$module} .= '-KEDUA';
    $second->{'butir_'.$module} = 'BUTIR KEDUA';
    $second->save();
    $review->update(['hasil_review' => $rich]);
    $responseClass = $module === 'snp' ? SnpTanggapan::class : DjsnTanggapan::class;
    $responseClass::findOrFail($review->id_tanggapan)->update(['tanggapan' => "AWAL-TANGGAPAN\nAKHIR-TANGGAPAN"]);
    if ($module === 'snp') {
        SnpKompilasi::create(['id_butir_snp' => $butir->id_butir_snp, 'tahap_kompilasi' => 'tanggapan', 'hasil_kompilasi' => str_replace('HASIL', 'KOMPILASI', $rich)]);
    }
    $fields = $includeButir ? ['id_butir', 'isi_butir'] : ['id_butir'];
    $fields = [...$fields, $module === 'snp' ? 'tanggapan_unit' : 'tanggapan', ...($module === 'snp' ? ['kompilasi_tanggapan'] : []), 'hasil_reviu'];
    $class = $module === 'snp' ? SnpReportExport::class : DjsnReportExport::class;
    $bytes = Excel::raw(new $class(collect([$butir->record]), $fields, []), Maatwebsite\Excel\Excel::XLSX);
    $path = tempnam(sys_get_temp_dir(), 'workflow-excel-');
    file_put_contents($path, $bytes);
    try {
        $sheet = IOFactory::load($path)->getActiveSheet();
        $text = implode("\n", array_map(fn (array $row): string => implode(' ', $row), $sheet->toArray()));
        expect($text)->toContain('AWAL-HASIL', 'AKHIR-HASIL', 'AWAL-TANGGAPAN', 'AKHIR-TANGGAPAN')->not->toContain('snp-rich', '<p>', '<img');
        expect($sheet->getDrawingCollection())->toHaveCount(($module === 'snp' ? 4 : 2) + ($includeButir ? 1 : 0));
        expect($sheet->getRowDimension(2)->getRowHeight())->toBeLessThan(100);
        $coordinates = [];
        foreach ($sheet->getDrawingCollection() as $drawing) {
            $coordinates[] = $drawing->getCoordinates();
            $row = preg_replace('/^[A-Z]+/', '', $drawing->getCoordinates());
            $id = $sheet->getCell('A'.$row);
            if ($range = $id->getMergeRange()) {
                $id = $sheet->getCell(explode(':', $range)[0]);
            }
            expect($id->getValue())->toBe($butir->{'id_butir_'.$module});
        }
        expect(count(array_unique($coordinates)))->toBe(count($coordinates));
    } finally {
        unlink($path);
    }
})->with([['snp', true], ['snp', false], ['djsn', true], ['djsn', false]]);

test('initial letter statuses agree across perekaman detail filters reports and dashboard', function (string $module) {
    $this->travelTo(now('Asia/Jakarta')->setDate(2026, 10, 21)->startOfDay());
    $this->actingAs(workflowUser('super_admin'));
    $recordClass = $module === 'snp' ? SnpRecord::class : DjsnRecord::class;
    $butirClass = $module === 'snp' ? SnpButir::class : DjsnButir::class;
    $draft = $recordClass::create(['nomor_surat' => 'DRAFT-UJI', 'tanggal_surat' => '2026-10-21']);
    $expired = $recordClass::create(['nomor_surat' => 'BATAS-UJI', 'tanggal_surat' => '2026-10-08']);
    $published = workflowButir($module)->record;
    $proses = $recordClass::create(['nomor_surat' => 'PROSES-UJI', 'tanggal_surat' => '2026-01-01']);
    $butir = $butirClass::create(['id_'.$module => $proses->{'id_'.$module}, 'butir_'.$module => 'Butir proses']);
    $db = DB::connection('mysql_'.$module);
    $pic = $db->table('tb_butir_pic')->insertGetId(['id_butir_'.$module => $butir->{'id_butir_'.$module}, 'jenis_pic' => 'utama', 'unit_kerja_id' => 1]);
    $db->table('tb_butir_pic')->insert(['id_butir_'.$module => $butir->{'id_butir_'.$module}, 'jenis_pic' => 'pendukung', 'unit_kerja_id' => 2]);
    $db->table('tb_butir_pic')->insert(['id_butir_'.$module => $butir->{'id_butir_'.$module}, 'jenis_pic' => 'komite', 'komite_id' => 1]);
    $this->post(route($module.'.tanggapan.store', $butir), [
        'butir_pic_id' => $pic, 'tanggapan' => 'Jawaban satu unit', 'deliverables' => 'Hasil satu unit',
    ])->assertRedirect()->assertSessionHasNoErrors();
    expect($proses->fresh()->status)->toBe('proses')->and($db->table('tb_tanggapan')->count())->toBe(1);
    expect($expired->fresh()->status)->toBe('draft');
    $this->travelTo(now('Asia/Jakarta')->setDate(2026, 10, 22)->startOfDay());
    $cases = ['draft' => $draft, 'terbit' => $published, 'belum_ditindaklanjuti' => $expired, 'proses' => $proses];
    foreach ($cases as $status => $record) {
        $page = $this->get(route($module.'.perekaman', ['status' => $status]))->assertOk()
            ->assertViewHas('records', fn ($records) => $records->pluck('id')->all() === [$record->id])
            ->assertViewHas('statistik', fn ($stats) => $stats['total'] === 4 && $stats[$status] === 1)
            ->assertSee(SuratStatus::labelFor($status))
            ->assertSee('detailRecord?.status_label', false)
            ->assertSee('status_label', false);
        expect($record->fresh()->status)->toBe($status);
        $this->get(route($module.'.report.index', ['status' => $status]))->assertOk()
            ->assertViewHas('records', fn ($records) => $records->pluck('id')->all() === [$record->id])
            ->assertSee(SuratStatus::labelFor($status))
            ->assertSee('background-color: '.SuratStatus::colorFor($status).'; color: '.SuratStatus::textColorFor($status).';', false);
        $this->get(route('dashboard', ['jenis_rapat' => $module, 'status' => $status]))->assertOk()
            ->assertViewHas('moduleStats', fn ($stats) => $stats[$module]['total_surat'] === 1 && $stats[$module]['status_surat'][$status] === 1)
            ->assertViewHas('chartData', function ($data) use ($status): bool {
                $label = SuratStatus::labelFor($status);
                $dataset = collect($data['suratPerJenis']['datasets'])->firstWhere('label', $label);

                return $dataset !== null && collect($dataset['data'])->all() === [1];
            });
    }
    $this->travelBack();
})->with(['snp', 'djsn']);

test('perekaman edits cannot replace the calculated letter status', function (string $module) {
    $this->actingAs(workflowUser('super_admin'));
    $butir = workflowButir($module);
    $record = $butir->record;
    foreach (['tb_cluster' => 'nama_cluster', 'tb_sub_cluster' => 'nama_sub_cluster'] as $table => $column) {
        DB::connection('mysql_'.$module)->table($table)->insert(['id' => 1, $column => 'Aktif', ...($table === 'tb_sub_cluster' ? ['cluster_id' => 1] : [])]);
    }
    if ($module === 'snp') {
        $record->update(['cluster_id' => 1, 'sub_cluster_id' => 1]);
    } else {
        $butir->update(['cluster_id' => 1, 'sub_cluster_id' => 1]);
    }
    $this->patch(route($module.'.perekaman.update', $record), [
        'nomor_surat' => $record->nomor_surat, 'tanggal_surat' => '2026-10-21', 'perihal_surat' => 'Perihal diperbaiki',
        'cluster_id' => 1, 'sub_cluster_id' => 1, 'status' => 'tindak_lanjut_sesuai',
        'butir_id' => $butir->id, 'butir_'.$module => 'Butir diperbaiki', 'butir_status' => 'terbit',
        'unit_kerja_utama_id' => 1, 'unit_kerja_pendukung_id' => [], 'komite_id' => 1,
    ])->assertRedirect()->assertSessionHasNoErrors();
    expect($record->fresh()->perihal_surat)->toBe('Perihal diperbaiki')->and($record->fresh()->status)->toBe('terbit');
})->with(['snp', 'djsn']);
