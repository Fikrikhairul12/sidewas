<?php

use App\Enums\SuratStatus;
use App\Models\DjsnButir;
use App\Models\SnpButir;
use App\Models\User;
use App\Services\ButirReportPdfAlignment;
use App\Services\SnpButirContent;
use App\Services\SnpReportPdfLabels;
use App\Services\WorkflowReportHistory;
use Dompdf\Canvas;
use Dompdf\Dompdf;
use Dompdf\Frame;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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

function reportHistoryFixture(string $module, string $decision): SnpButir|DjsnButir
{
    $actor = workflowUser('super_admin');
    test()->actingAs($actor);
    $butir = workflowButir($module);
    DB::table('tb_unit_kerja')->where('id', 1)->update(['kode_unit' => 'A', 'nama_unit' => 'Unit A']);
    foreach ([2 => 'B', 3 => 'C'] as $id => $code) {
        DB::table('tb_unit_kerja')->insert(['id' => $id, 'direktorat_id' => 1, 'kode_unit' => $code, 'nama_unit' => 'Unit '.$code, 'status' => 'active']);
        $butir->butirPics()->create(['jenis_pic' => 'pendukung', 'unit_kerja_id' => $id]);
    }
    $butir->update(['butir_'.$module => 'BUTIR-TERPILIH']);
    $butir->newQuery()->create(['id_'.$module => $butir->{'id_'.$module}, 'butir_'.$module => 'BUTIR-TIDAK-DIPILIH']);
    for ($number = 0; $number <= 2; $number++) {
        $roundId = $butir->record->fresh()->putaran_aktif_id;
        $pic = $butir->butirPics()->where('unit_kerja_id', 1)->firstOrFail();
        test()->post(route($module.'.tanggapan.store', $butir), [
            'butir_pic_id' => $pic->id, 'putaran_id' => $roundId,
            'tanggapan' => 'JAWABAN-PUTARAN-'.$number, 'deliverables' => 'HASIL-UNIT-'.$number,
        ])->assertRedirect()->assertSessionHasNoErrors();
        test()->post(route($module.'.kompilasi.store', $butir), [
            'putaran_id' => $roundId, 'tahap_kompilasi' => 'tanggapan',
            'hasil_kompilasi' => SnpButirContent::PREFIX.'<p><strong>KOMPILASI-PUTARAN-'.$number.'</strong></p>',
            'deliverables' => 'HASIL-KOMPILASI-'.$number,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $review = $butir->reviews()->latest('id')->firstOrFail();
        test()->patch(route($module.'.reviu.update', $review), [
            'hasil_review' => SnpButirContent::PREFIX.'<p><em>REVIU-PUTARAN-'.$number.'</em></p>',
            'status' => $number < 2 ? 'tindak_lanjut_belum_sesuai' : $decision,
        ])->assertRedirect()->assertSessionHasNoErrors();
        test()->travel(1)->days();
    }
    $pic = $butir->butirPics()->where('unit_kerja_id', 2)->firstOrFail();
    test()->post(route($module.'.tanggapan.store', $butir), [
        'butir_pic_id' => $pic->id, 'putaran_id' => $butir->record->fresh()->closed_putaran_id,
        'tanggapan' => 'KELENGKAPAN-TERLAMBAT-B', 'deliverables' => 'HASIL-TERLAMBAT-B',
    ])->assertRedirect()->assertSessionHasNoErrors();

    return $butir->fresh();
}

/** @return array{pdf: Dompdf, text: string} */
function inspectWorkflowReport(string $html): array
{
    $text = [];
    $boxes = [];
    $images = [];
    $pdf = new Dompdf;
    $pdf->setPaper('legal', 'landscape');
    $pdf->getOptions()->setIsJavascriptEnabled(false);
    $pdf->setCallbacks([...app(ButirReportPdfAlignment::class)->callbacks(), ...app(SnpReportPdfLabels::class)->callbacks(), ['event' => 'end_frame', 'f' => function (Frame $frame, Canvas $canvas) use (&$text, &$boxes, &$images): void {
        $node = $frame->get_node();
        if ($node instanceof DOMText) {
            $text[] = $node->textContent;
        }
        if ($node instanceof DOMElement && $node->tagName === 'tr' && $node->hasAttribute('data-report-butir')) {
            $boxes[] = $frame->get_border_box();
            expect($canvas->get_page_number())->toBe((int) $node->getAttribute('data-report-page') + 1);
        }
        if ($node instanceof DOMElement && $node->hasAttribute('data-snp-butir-label')) {
            expect($node->textContent)->toContain('Putaran');
        }
        if ($node instanceof DOMElement && $node->tagName === 'img') {
            $images[] = $frame->get_border_box();
        }
    }]]);
    $pdf->loadHtml($html);
    $pdf->render();
    foreach ($boxes as $box) {
        expect($box['y'])->toBeGreaterThanOrEqual(8 * 72 / 25.4)
            ->and($box['y'] + $box['h'])->toBeLessThanOrEqual(612 - 14 * 72 / 25.4);
    }
    foreach ($images as $box) {
        expect($box['x'])->toBeGreaterThanOrEqual(8 * 72 / 25.4)
            ->and($box['x'] + $box['w'])->toBeLessThanOrEqual(1008 - 8 * 72 / 25.4 + 1)
            ->and($box['y'] + $box['h'])->toBeLessThanOrEqual(612 - 14 * 72 / 25.4);
    }

    return ['pdf' => $pdf, 'text' => preg_replace('/\s+/u', ' ', implode(' ', $text)), 'images' => count($images)];
}

test('regular and custom PDF endpoints retain two correction cycles and separate late completion for both closing decisions', function (string $module, string $decision) {
    $butir = reportHistoryFixture($module, $decision);
    $record = $butir->record;
    $snapshot = $record->getRawOriginal();
    $outside = workflowButir($module);
    $outside->update(['butir_'.$module => 'SURAT-LUAR-CAKUPAN']);
    $rounds = app(WorkflowReportHistory::class)->data(['records' => $record->newCollection([$record])], $module)['workflowReport']['butirs'][0]['rounds'];
    expect($rounds)->toHaveCount(3)->and($rounds->pluck('label')->all())->toBe(['Putaran awal (0)', 'Putaran 1', 'Putaran 2'])
        ->and($rounds[0]['missing']->pluck('name')->all())->toBe(['Unit B', 'Unit C'])
        ->and($rounds[1]['missing']->pluck('name')->all())->toBe(['Unit B', 'Unit C'])
        ->and($rounds[2]['missing']->pluck('name')->all())->toBe(['Unit C'])
        ->and($rounds[2]['missing_at_review']->pluck('name')->all())->toBe(['Unit B', 'Unit C'])
        ->and($rounds[2]['responses'])->toHaveCount(1)->and($rounds[2]['late'])->toHaveCount(1)
        ->and($record->putaran()->whereNotNull('penugasan_unit')->count())->toBe(3);
    foreach (['cetak', 'cetak-custom'] as $kind) {
        $payload = ['record_ids' => [$record->id], 'butir_ids' => [$butir->id], 'fields' => ['id_butir', 'isi_butir', 'tanggapan_tl', 'hasil_reviu', 'status']];
        if ($module === 'snp') {
            $payload['fields'] = ['id_butir', 'isi_butir', 'tanggapan_unit', 'kompilasi_tanggapan', 'hasil_reviu', 'status'];
        }
        $response = $this->post(route($module.'.report.'.$kind), $payload)->assertOk();
        $html = $response->viewData('reportHtml');
        $result = inspectWorkflowReport($html);
        $text = $result['text'];
        foreach (range(0, 2) as $number) {
            foreach (['JAWABAN', 'KOMPILASI', 'REVIU'] as $prefix) {
                expect($text)->toContain($prefix.'-PUTARAN-'.$number);
            }
        }
        expect(strpos($text, 'JAWABAN-PUTARAN-0'))->toBeLessThan(strpos($text, 'JAWABAN-PUTARAN-1'))
            ->and(strpos($text, 'JAWABAN-PUTARAN-1'))->toBeLessThan(strpos($text, 'JAWABAN-PUTARAN-2'))
            ->and($text)->toContain('KELENGKAPAN-TERLAMBAT-B', 'Tanggapan setelah penutupan', 'bukan bahan keputusan reviu', 'Belum memberikan tanggapan pada putaran ini', 'Unit C', 'Close:', SuratStatus::labelFor($decision))
            ->not->toContain('SURAT-LUAR-CAKUPAN')
            ->and($html)->not->toContain('snp-rich:v1');
        if ($kind === 'cetak-custom') {
            expect($text)->not->toContain('BUTIR-TIDAK-DIPILIH');
        }
        $downloadRoute = $module === 'snp' ? ($kind === 'cetak' ? 'download' : 'download-custom') : $kind;
        $download = $this->post(route($module.'.report.'.$downloadRoute), $payload + ['_download' => 1])->assertOk();
        expect($download->headers->get('Content-Type'))->toBe('application/pdf')
            ->and($download->getContent())->toStartWith('%PDF-');
        if (getenv('WORKFLOW_REPORT_ARTIFACTS')) {
            file_put_contents(getenv('WORKFLOW_REPORT_ARTIFACTS').'/'.$module.'-'.$kind.'-'.$decision.'.html', $html);
            file_put_contents(getenv('WORKFLOW_REPORT_ARTIFACTS').'/'.$module.'-'.$kind.'-'.$decision.'.pdf', $download->getContent());
        }
    }
    expect($record->fresh()->getRawOriginal())->toBe($snapshot);
})->with(['snp', 'djsn'])->with(['tindak_lanjut_sesuai', 'tidak_dapat_ditindaklanjuti']);

test('custom reports keep every selected round while respecting unit butir and column selection', function () {
    $butir = reportHistoryFixture('snp', 'tindak_lanjut_sesuai');
    $payload = ['record_ids' => [$butir->record->id], 'butir_ids' => [$butir->id], 'fields' => ['tanggapan_unit'], 'tanggapan_unit_kerja_ids' => [1]];
    $html = $this->post(route('snp.report.cetak-custom'), $payload)->assertOk()->viewData('reportHtml');
    $text = preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html)));
    expect($text)->toContain('JAWABAN-PUTARAN-0', 'JAWABAN-PUTARAN-1', 'JAWABAN-PUTARAN-2', 'Putaran 1', 'Putaran 2')
        ->not->toContain('KELENGKAPAN-TERLAMBAT-B', 'KOMPILASI-PUTARAN', 'REVIU-PUTARAN', 'BUTIR-TERPILIH', 'Unit B', 'Unit C');
    $payload['fields'] = ['isi_butir'];
    $html = $this->post(route('snp.report.cetak-custom'), $payload)->assertOk()->viewData('reportHtml');
    expect($html)->toContain('BUTIR-TERPILIH')->not->toContain('JAWABAN-PUTARAN', 'KELENGKAPAN-TERLAMBAT', 'REVIU-PUTARAN');
});

test('legacy reports preserve follow ups and unknown identities without inventing historic unit assignments', function (string $module) {
    $this->actingAs(workflowUser('super_admin'));
    $butir = workflowButir($module);
    $db = DB::connection('mysql_'.$module);
    $db->table('tb_tanggapan')->insert(['id_butir_'.$module => $butir->{'id_butir_'.$module}, 'tanggapan' => 'JAWABAN-LAMA-TANPA-UNIT']);
    $db->table('tb_tindak_lanjut')->insert(['id_butir_'.$module => $butir->{'id_butir_'.$module}, 'tindak_lanjut' => 'TINDAK-LANJUT-LAMA', 'deliverables' => 'HASIL-LAMA', 'dokumen' => 'dokumen/lama.pdf']);
    $db->table('tb_review')->insert(['id_butir_'.$module => $butir->{'id_butir_'.$module}, 'tahap_review' => 'tindak_lanjut', 'hasil_review' => 'REVIU-LAMA', 'status' => 'selesai_tuntas']);
    $butir->record->forceFill(['status' => 'tuntas'])->save();
    $html = $this->post(route($module.'.report.cetak'), ['record_ids' => [$butir->record->id]])->assertOk()->viewData('reportHtml');
    $text = preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html)));
    expect($text)->toContain('JAWABAN-LAMA-TANPA-UNIT', 'TINDAK-LANJUT-LAMA', 'REVIU-LAMA', 'HASIL-LAMA', 'Unit tidak tercatat pada data lama', 'Pengisi tidak tercatat', 'Tanggal tidak tercatat', 'Selesai Tuntas (Alur Lama)', 'Penugasan unit pada putaran lama tidak tercatat');
    expect($butir->record->fresh()->putaran()->count())->toBe(0);
})->with(['snp', 'djsn']);

test('assignment snapshots remain immutable and an idempotent migration never fabricates legacy assignments', function () {
    $migration = require database_path('migrations/2026_10_22_132318_add_unit_assignments_to_surat_putaran.php');
    $migration->up();
    $butir = reportHistoryFixture('snp', 'tindak_lanjut_sesuai');
    $round = $butir->record->putaran()->where('nomor_putaran', 0)->firstOrFail();
    expect(fn () => $round->forceFill(['penugasan_unit' => []])->save())->toThrow(LogicException::class);
    expect(fn () => $migration->down())->toThrow(RuntimeException::class);
    expect($butir->record->putaran()->whereNotNull('penugasan_unit')->count())->toBe(3);
});

test('old active follow up routes and menus are replaced only when the new workflow flag is enabled', function (string $module) {
    $this->actingAs(workflowUser('super_admin'));
    $butir = workflowButir($module);
    $this->get(route($module.'.tindak-lanjut.index'))->assertRedirect(route($module.'.tanggapan.index'));
    $this->postJson(route($module.'.tindak-lanjut.store'), [])->assertGone();
    $sidebar = view('layouts.sidebar')->render();
    expect($sidebar)->not->toContain(route('snp.tindak-lanjut.index'), route('djsn.tindak-lanjut.index'))
        ->toContain(route('ragab.tindak-lanjut.index'), route('rawas.tindak-lanjut.index'), route('eksternal.tindak-lanjut.index'));
    config(['sidewas.reviu_putaran_enabled' => false]);
    $sidebar = view('layouts.sidebar')->render();
    expect($sidebar)->toContain(route('snp.tindak-lanjut.index'), route('djsn.tindak-lanjut.index'));
    $this->get(route($module.'.tindak-lanjut.index'))->assertOk();
})->with(['snp', 'djsn']);

test('report endpoints reject users without module permissions for previews and downloads', function (string $module) {
    $this->actingAs(workflowUser('super_admin'));
    $butir = workflowButir($module);
    $this->actingAs(User::factory()->create(['status' => 'active']));
    foreach (['cetak', 'cetak-custom'] as $kind) {
        $this->post(route($module.'.report.'.$kind), ['record_ids' => [$butir->record->id], 'butir_ids' => [$butir->id], 'fields' => ['status']])->assertForbidden();
    }
    if ($module === 'snp') {
        foreach (['download', 'download-custom'] as $kind) {
            $this->post(route($module.'.report.'.$kind), [])->assertForbidden();
        }
    } else {
        $this->post(route($module.'.report.cetak'), ['_download' => 1])->assertForbidden();
    }
})->with(['snp', 'djsn']);

test('long compilation and review content images and late plain text survive regular and custom PDF pagination', function (string $module) {
    $this->actingAs(workflowUser('super_admin'));
    $butir = workflowButir($module);
    $compImage = $this->postJson(route($module.'.kompilasi.images.store', ['butir' => $butir, 'tahap_kompilasi' => 'tanggapan']), [
        'image' => UploadedFile::fake()->image('kompilasi-tinggi.png', 240, 1600),
    ])->assertCreated()->json('url');
    $compilation = SnpButirContent::PREFIX.'<p style="text-align:center"><strong>KOMPILASI-AWAL</strong></p><ol start="3"><li><p>Daftar tiga</p></li><li><p>Daftar empat</p></li></ol><p>'.implode(' ', array_map(fn ($number) => 'KOMP-'.$number, range(1, 350))).'</p><p><img src="'.$compImage.'" data-width="100"></p><p>KOMPILASI-AKHIR</p>';
    $this->post(route($module.'.kompilasi.store', $butir), ['tahap_kompilasi' => 'tanggapan', 'hasil_kompilasi' => $compilation])->assertRedirect()->assertSessionHasNoErrors();
    $review = $butir->reviews()->firstOrFail();
    $reviewImage = $this->postJson(route($module.'.reviu.images.store', $review), ['image' => UploadedFile::fake()->image('reviu-lebar.png', 1600, 500)])->assertCreated()->json('url');
    $reviewContent = SnpButirContent::PREFIX.'<p style="text-align:right"><em>REVIU-AWAL</em></p><p><span style="font-size:24px">'.implode(' ', array_map(fn ($number) => 'REV-'.$number, range(1, 170))).'</span></p><p><img src="'.$reviewImage.'" data-width="100"></p><p><u>REVIU-AKHIR</u></p>';
    $this->patch(route($module.'.reviu.update', $review), ['hasil_review' => $reviewContent, 'status' => 'tindak_lanjut_sesuai'])->assertRedirect()->assertSessionHasNoErrors();
    $this->travel(1)->days();
    $pic = $butir->butirPics()->where('unit_kerja_id', 1)->firstOrFail();
    $plain = 'LITERAL <strong>BUKAN TOOLBAR</strong> '.implode(' ', array_map(fn ($number) => 'LATE-'.$number, range(1, 300)));
    $this->post(route($module.'.tanggapan.store', $butir), ['butir_pic_id' => $pic->id, 'putaran_id' => $review->putaran_id, 'tanggapan' => $plain, 'deliverables' => 'Hasil <literal>'])->assertRedirect()->assertSessionHasNoErrors();
    foreach (['cetak', 'cetak-custom'] as $kind) {
        $fields = $module === 'snp' ? ['id_butir', 'kompilasi_tanggapan', 'tanggapan_unit', 'hasil_reviu', 'status'] : ['id_butir', 'tanggapan_tl', 'hasil_reviu', 'status'];
        $html = $this->post(route($module.'.report.'.$kind), ['record_ids' => [$butir->record->id], 'butir_ids' => [$butir->id], 'fields' => $fields])->assertOk()->viewData('reportHtml');
        $result = inspectWorkflowReport($html);
        expect($result['images'])->toBeGreaterThanOrEqual(2)
            ->and($result['pdf']->getCanvas()->get_page_count())->toBeGreaterThan(1)
            ->and($result['text'])->toContain('KOMPILASI-AWAL', 'KOMPILASI-AKHIR', 'REVIU-AWAL', 'REVIU-AKHIR', '3. Daftar tiga', '4. Daftar empat', 'LITERAL <strong>BUKAN TOOLBAR</strong>', 'Tanggapan setelah penutupan')
            ->and($html)->toContain('font-weight:bold', 'font-style:italic', 'text-decoration:underline', 'font-size:24px');
        foreach (['KOMP' => 350, 'REV' => 170, 'LATE' => 300] as $prefix => $count) {
            foreach (range(1, $count) as $number) {
                expect($result['text'])->toContain($prefix.'-'.$number);
            }
        }
        if (getenv('WORKFLOW_REPORT_ARTIFACTS')) {
            file_put_contents(getenv('WORKFLOW_REPORT_ARTIFACTS').'/'.$module.'-'.$kind.'-rich.pdf', $result['pdf']->output());
            file_put_contents(getenv('WORKFLOW_REPORT_ARTIFACTS').'/'.$module.'-'.$kind.'-rich.html', $html);
        }
    }
})->with(['snp', 'djsn']);

test('historical Detail retains legacy follow ups and missing units using the frozen round assignments', function (string $module) {
    $butir = reportHistoryFixture($module, 'tindak_lanjut_sesuai');
    $round = $butir->record->putaran()->where('nomor_putaran', 0)->firstOrFail();
    DB::connection('mysql_'.$module)->table('tb_tindak_lanjut')->insert([
        'putaran_id' => $round->id, 'id_butir_'.$module => $butir->{'id_butir_'.$module}, 'tindak_lanjut' => 'RIWAYAT-TL-DETAIL', 'dokumen' => 'dokumen/tl-lama.pdf',
    ]);
    $state = $this->get(route($module.'.tanggapan.index'))->assertOk()->viewData('responseStates')[$butir->id];
    $history = collect($state['detail']['rounds'])->firstWhere('id', $round->id);
    expect(collect($history['pic_tanggapans'])->where('sudah_menanggapi', false))->toHaveCount(2)
        ->and($history['legacy_followups'][0]['content'])->toBe('RIWAYAT-TL-DETAIL')
        ->and($history['legacy_followups'][0]['document_url'])->toContain('/storage/dokumen/tl-lama.pdf');
    $this->get(route($module.'.tanggapan.index'))->assertSee('Riwayat Tindak Lanjut Lama')->assertSee('Belum memberikan tanggapan pada putaran ini.');
})->with(['snp', 'djsn']);

test('dashboard progress attention filters and late completion use the same official letter decisions', function (string $module, string $decision) {
    $butir = reportHistoryFixture($module, $decision);
    DB::connection('mysql_'.$module)->table('tb_record')->where('id', $butir->record->id)->update(['jth_tempo' => '2026-10-01']);
    $response = $this->get(route('dashboard', ['jenis_rapat' => $module]))->assertOk()->assertSee('100,0% Close')->assertSee('1 surat');
    $stats = $response->viewData('moduleStats')[$module];
    expect($stats['uses_letter_status'])->toBeTrue()->and($stats['progress'])->toBe(100.0)
        ->and($stats['total_surat'])->toBe(1)->and($stats['total_butir'])->toBe(2)
        ->and($stats['status_surat'][$decision])->toBe(1)->and($stats['jatuh_tempo'])->toBe(0)
        ->and($response->viewData('attentionRows'))->toBeEmpty();
    $filtered = $this->get(route($module.'.report.index', ['status' => $decision]))->assertOk()->viewData('records');
    expect($filtered->total())->toBe(1);
    $open = workflowButir($module);
    $open->record->update(['jth_tempo' => '2026-10-01']);
    $response = $this->get(route('dashboard', ['jenis_rapat' => $module]))->assertOk()->assertSee('50,0% Close');
    expect($response->viewData('moduleStats')[$module]['progress'])->toBe(50.0)
        ->and($response->viewData('moduleStats')[$module]['jatuh_tempo'])->toBe(1)
        ->and($response->viewData('attentionRows')->pluck('id')->all())->toBe([$open->{'id_'.$module}]);
})->with(['snp', 'djsn'])->with(['tindak_lanjut_sesuai', 'tidak_dapat_ditindaklanjuti']);

test('changed PIC assignments never rewrite missing units in an earlier round or hide them from custom unit choices', function (string $module) {
    $this->actingAs(workflowUser('super_admin'));
    $butir = workflowButir($module);
    DB::table('tb_unit_kerja')->where('id', 1)->update(['nama_unit' => 'Unit A']);
    foreach ([2 => 'B', 3 => 'C'] as $id => $code) {
        DB::table('tb_unit_kerja')->insert(['id' => $id, 'direktorat_id' => 1, 'nama_unit' => 'Unit '.$code]);
    }
    $oldPic = $butir->butirPics()->create(['jenis_pic' => 'pendukung', 'unit_kerja_id' => 2]);
    foreach ([0, 1] as $number) {
        if ($number === 1) {
            $oldPic->delete();
            $butir->butirPics()->create(['jenis_pic' => 'pendukung', 'unit_kerja_id' => 3]);
        }
        $this->post(route($module.'.kompilasi.store', $butir), [
            'putaran_id' => $butir->record->fresh()->putaran_aktif_id, 'tahap_kompilasi' => 'tanggapan', 'hasil_kompilasi' => 'Bahan '.$number,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $review = $butir->reviews()->latest('id')->firstOrFail();
        $this->patch(route($module.'.reviu.update', $review), ['hasil_review' => 'Hasil '.$number, 'status' => $number === 0 ? 'tindak_lanjut_belum_sesuai' : 'tindak_lanjut_sesuai'])->assertRedirect()->assertSessionHasNoErrors();
    }
    $data = app(WorkflowReportHistory::class)->data(['records' => $butir->record->newCollection([$butir->record->fresh()])], $module);
    $rounds = $data['workflowReport']['butirs'][0]['rounds'];
    expect($rounds[0]['missing']->pluck('name')->all())->toBe(['Unit A', 'Unit B'])
        ->and($rounds[1]['missing']->pluck('name')->all())->toBe(['Unit A', 'Unit C']);
    if ($module === 'snp') {
        $options = app(WorkflowReportHistory::class)->unitOptions($data['workflowReport']['butirs'][0]['butir']);
        expect($options->pluck('id')->sort()->values()->all())->toBe([1, 2, 3]);
        $this->get(route('snp.report.index'))->assertOk()->assertSee('Unit B')->assertSee('Unit C');
    }
})->with(['snp', 'djsn']);

test('unidentified legacy units remain selectable without assigning their responses to a known unit', function () {
    $this->actingAs(workflowUser('super_admin'));
    $butir = workflowButir('snp');
    DB::connection('mysql_snp')->table('tb_tanggapan')->insert(['id_butir_snp' => $butir->id_butir_snp, 'tanggapan' => 'RESPONS-TANPA-UNIT', 'created_by' => 8899]);
    $data = app(WorkflowReportHistory::class)->data(['records' => $butir->record->newCollection([$butir->record])], 'snp');
    $options = app(WorkflowReportHistory::class)->unitOptions($data['workflowReport']['butirs'][0]['butir']);
    expect($options->pluck('id')->sort()->values()->all())->toBe([0, 1])
        ->and($options->firstWhere('id', 0)['label'])->toBe('Unit tidak tercatat (data lama)');
    $payload = ['record_ids' => [$butir->record->id], 'butir_ids' => [$butir->id], 'fields' => ['tanggapan_unit'], 'tanggapan_unit_kerja_ids' => [0]];
    $html = $this->post(route('snp.report.cetak-custom'), $payload)->assertOk()->viewData('reportHtml');
    expect($html)->toContain('RESPONS-TANPA-UNIT', 'Pengisi #8899 (nama tidak tersedia)');
    $payload['tanggapan_unit_kerja_ids'] = [1];
    expect($this->post(route('snp.report.cetak-custom'), $payload)->assertOk()->viewData('reportHtml'))->not->toContain('RESPONS-TANPA-UNIT');
});

test('PDF includes letters without butirs and never restarts their fourteen day deadline after publication', function (string $module) {
    $this->actingAs(workflowUser('super_admin'));
    $butir = workflowButir($module);
    $record = $butir->record;
    $butir->delete();
    $html = $this->post(route($module.'.report.cetak'), ['record_ids' => [$record->id]])->assertOk()->viewData('reportHtml');
    expect($html)->toContain('Belum ada butir.', 'Terbit')->not->toContain('Batas awal 14 hari kalender');
    $recordClass = get_class($record);
    $draft = $recordClass::create(['nomor_surat' => 'SURAT-TANPA-BUTIR', 'tanggal_surat' => '2026-10-08']);
    $html = $this->post(route($module.'.report.cetak'), ['record_ids' => [$draft->id]])->assertOk()->viewData('reportHtml');
    $text = preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace('</div>', ' ', $html))));
    expect($text)->toContain('SURAT-TANPA-BUTIR', 'Belum Ditindaklanjuti', 'Batas awal 14 hari kalender: 22/10/2026');
})->with(['snp', 'djsn']);

test('new workflow perekaman edits preserve legacy butir status and cannot manually decide a letter', function (string $module) {
    $this->actingAs(workflowUser('super_admin'));
    $butir = workflowButir($module);
    $db = DB::connection('mysql_'.$module);
    $db->table('tb_cluster')->insert(['id' => 1, 'nama_cluster' => 'Cluster Uji', 'status' => 'active']);
    $db->table('tb_sub_cluster')->insert(['id' => 1, 'cluster_id' => 1, 'nama_sub_cluster' => 'Sub Cluster Uji', 'status' => 'active']);
    $payload = [
        'nomor_surat' => $butir->record->nomor_surat, 'tanggal_surat' => '2026-10-21', 'perihal_surat' => 'Perekaman diperbarui',
        'cluster_id' => 1, 'sub_cluster_id' => 1, 'butir_id' => $butir->id, 'butir_'.$module => 'Isi butir diperbarui',
        'unit_kerja_utama_id' => 1, 'komite_id' => 1,
        'status' => 'tindak_lanjut_sesuai', 'butir_status' => 'selesai_tuntas',
    ];
    $this->patch(route($module.'.perekaman.update', $butir->record), $payload)->assertRedirect()->assertSessionHasNoErrors();
    expect($butir->fresh()->{'butir_'.$module})->toBe('Isi butir diperbarui')
        ->and($butir->fresh()->status)->toBe('terbit')->and($butir->record->fresh()->status)->toBe('terbit');
    unset($payload['butir_status']);
    $this->patch(route($module.'.perekaman.update', $butir->record), $payload)->assertRedirect()->assertSessionHasNoErrors();
    $this->get(route($module.'.perekaman'))->assertOk()->assertDontSee('name="butir_status"', false);
    config(['sidewas.reviu_putaran_enabled' => false]);
    $this->get(route($module.'.perekaman'))->assertOk()->assertSee('name="butir_status"', false);
})->with(['snp', 'djsn']);
