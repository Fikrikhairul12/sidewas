<?php

use App\Enums\TahapProses;
use App\Models\User;
use App\Services\ButirReportPdfAlignment;
use App\Services\ReviuBahanPreviewService;
use App\Services\SnpButirContent;
use App\Services\SnpReportPdfLabels;
use App\Services\SuratTanggalService;
use App\Services\WorkflowReportHistory;
use Dompdf\Canvas;
use Dompdf\Dompdf;
use Dompdf\Frame;
use Illuminate\Database\Eloquent\Model;
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
    config(['sidewas.alur_revisi_enabled' => true, 'sidewas.reviu_putaran_enabled' => true]);
    $this->travelTo(now()->setDate(2026, 11, 1)->setTime(10, 30));
    Storage::fake('public');
});

function compiledReportFixture(string $module): array
{
    $actor = workflowUser('super_admin');
    $butir = workflowButir($module);
    $users = [];
    foreach ([1 => 'A', 2 => 'B', 3 => 'C'] as $id => $name) {
        DB::table('tb_unit_kerja')->updateOrInsert(['id' => $id], ['nama_unit' => 'Unit '.$name, 'kode_unit' => $name, 'direktorat_id' => 1, 'status' => 'active']);
        $butir->butirPics()->firstOrCreate(['jenis_pic' => $id === 1 ? 'utama' : 'pendukung', 'unit_kerja_id' => $id]);
        $users[$id] = workflowUser('pic_'.$module);
        $users[$id]->update(['name' => 'Pengisi '.$name]);
        $users[$id]->unitKerja()->sync([$id => ['status' => 'active']]);
    }

    return compact('module', 'actor', 'butir', 'users');
}

function compiledReportInput(array $f, int $unit, string $content, bool $document = false): Model
{
    $stage = $f['butir']->record->fresh()->tahapAktif;
    $followUp = $stage?->tahap_proses === TahapProses::TindakLanjut;
    test()->actingAs($f['users'][$unit])->post($followUp ? route($f['module'].'.tindak-lanjut.store') : route($f['module'].'.tanggapan.store', $f['butir']), [
        'butir_id' => $f['butir']->id, 'tahap_id' => $stage?->id,
        'butir_pic_id' => $f['butir']->butirPics()->where('unit_kerja_id', $unit)->firstOrFail()->id,
        'isi' => $content, 'deliverables' => 'HASIL-UNIT-'.$unit,
        ...($document ? ['dokumen' => UploadedFile::fake()->create('sumber.pdf', 1, 'application/pdf')] : []),
    ])->assertRedirect()->assertSessionHasNoErrors();

    return ($followUp ? $f['butir']->tindakLanjuts() : $f['butir']->tanggapanUnit())->latest('id')->firstOrFail();
}

function compiledReportCompile(array $f, string $body = 'Kompilasi referensi', array $extra = []): Model
{
    $stage = $f['butir']->record->fresh()->tahapAktif;
    test()->actingAs($f['actor'])->post(route($f['module'].'.kompilasi.store', $f['butir']), $extra + [
        'tahap_id' => $stage?->id, 'putaran_id' => $stage?->putaran_asal_id,
        'tahap_kompilasi' => $stage?->tahap_proses === TahapProses::TindakLanjut ? 'tindak_lanjut' : 'tanggapan',
        'hasil_kompilasi' => $body, 'deliverables' => 'HASIL-KOMPILASI',
    ])->assertRedirect()->assertSessionHasNoErrors();

    return $f['butir']->reviews()->latest('id')->firstOrFail();
}

function compiledReportDecide(array $f, Model $review, string $status, string $body = 'Hasil reviu'): void
{
    test()->actingAs($f['actor'])->patch(route($f['module'].'.reviu.update', $review), [
        'hasil_review' => $body, 'status' => $status,
        ...($status === 'tindak_lanjut_belum_sesuai' ? ['jatuh_tempo_tl' => app(SuratTanggalService::class)->suggestedDate()] : []),
    ])->assertRedirect()->assertSessionHasNoErrors();
}

function compiledReportHtml(array $f, bool $custom = false, array $extra = []): string
{
    return test()->actingAs($f['actor'])->post(route($f['module'].'.report.'.($custom ? 'cetak-custom' : 'cetak')), $extra + [
        'record_ids' => [$f['butir']->record->id], 'butir_ids' => [$f['butir']->id],
        'fields' => $f['module'] === 'snp'
            ? ['surat', 'id_butir', 'isi_butir', 'tanggapan_unit', 'tindak_lanjut_unit', 'kompilasi_tanggapan', 'kompilasi_tindak_lanjut', 'hasil_reviu', 'status']
            : ['surat', 'id_butir', 'isi_butir', 'tanggapan_tl', 'hasil_reviu', 'status'],
    ])->assertOk()->viewData('reportHtml');
}

function compiledReportText(string $html): string
{
    return preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace('</div>', ' ', $html))));
}

function compiledReportImage(): UploadedFile
{
    $file = UploadedFile::fake()->image('diagram.png', 180, 90);
    $image = imagecreatetruecolor(180, 90);
    imagefill($image, 0, 0, imagecolorallocate($image, 239, 246, 255));
    $blue = imagecolorallocate($image, 23, 77, 117);
    imagerectangle($image, 2, 2, 177, 87, $blue);
    imagestring($image, 4, 24, 22, 'BAHAN RESMI', $blue);
    imagestring($image, 3, 24, 48, 'SALINAN KOMPILASI', $blue);
    imagepng($image, $file->getPathname());
    imagedestroy($image);

    return $file;
}

/** @return array{pdf: Dompdf, text: string, images: int} */
function compiledReportRender(string $html): array
{
    $text = [];
    $images = [];
    $pdf = new Dompdf;
    $pdf->setPaper('legal', 'landscape');
    $pdf->getOptions()->setIsJavascriptEnabled(false);
    $pdf->setCallbacks([...app(ButirReportPdfAlignment::class)->callbacks(), ...app(SnpReportPdfLabels::class)->callbacks(), [
        'event' => 'end_frame', 'f' => function (Frame $frame, Canvas $canvas) use (&$text, &$images): void {
            $node = $frame->get_node();
            if ($node instanceof DOMText) {
                $text[] = $node->textContent;
            }
            if ($node instanceof DOMElement && $node->tagName === 'tr' && $node->hasAttribute('data-report-butir')) {
                $box = $frame->get_border_box();
                expect($canvas->get_page_number())->toBe((int) $node->getAttribute('data-report-page') + 1)
                    ->and($box['y'])->toBeGreaterThanOrEqual(8 * 72 / 25.4 - 0.1)
                    ->and($box['y'] + $box['h'])->toBeLessThanOrEqual(612 - 14 * 72 / 25.4 + 0.1);
            }
            if ($node instanceof DOMElement && $node->tagName === 'img') {
                $images[] = $frame->get_border_box();
            }
        },
    ]]);
    $pdf->loadHtml($html);
    $pdf->render();
    foreach ($images as $box) {
        expect($box['x'])->toBeGreaterThanOrEqual(8 * 72 / 25.4)
            ->and($box['x'] + $box['w'])->toBeLessThanOrEqual(1008 - 8 * 72 / 25.4 + 1)
            ->and($box['y'] + $box['h'])->toBeLessThanOrEqual(612 - 14 * 72 / 25.4 + 0.1);
    }

    return ['pdf' => $pdf, 'text' => preg_replace('/\s+/u', ' ', implode(' ', $text)), 'images' => count($images)];
}

test('R6 preview aktif dan salinan resmi menangani nol satu beberapa bahan tanpa membaca susulan', function (string $module, int $count) {
    $f = compiledReportFixture($module);
    $sources = [];
    for ($unit = 1; $unit <= $count; $unit++) {
        $sources[] = compiledReportInput($f, $unit, 'BAHAN-RESMI-'.$unit, true);
    }
    $preview = app(ReviuBahanPreviewService::class)->forCompilation($f['butir']->fresh(), $f['actor']);
    expect($preview['responses'])->toHaveCount($count)->and($preview['kind_label'])->toBe('Tanggapan')
        ->and($preview['round_label'])->toBe('Tanggapan Awal');
    $review = compiledReportCompile($f);
    if ($count) {
        $this->actingAs($f['users'][1])->patch(route($module.'.tanggapan.update', $sources[0]), ['isi' => 'EDIT-SETELAH-KOMPILASI', 'deliverables' => 'HASIL-EDIT'])->assertSessionHasNoErrors();
    }
    if ($count < 3) {
        compiledReportInput($f, 3, 'SUSULAN-BELUM-DIKOMPILASI');
    }
    $db = DB::connection('mysql_'.$module);
    $snapshot = $db->table('tb_kompilasi')->first();
    DB::table('tb_unit_kerja')->where('id', 1)->update(['nama_unit' => 'NAMA-UNIT-DIUBAH']);
    $f['users'][1]->update(['name' => 'NAMA-PENGISI-DIUBAH']);
    $db->table('tb_butir_'.$module)->where('id', $f['butir']->id)->update(['butir_'.$module => 'BUTIR-DIUBAH']);
    $db->table('tb_record')->where('id', $f['butir']->record->id)->update(['nomor_surat' => 'SURAT-DIUBAH', 'perihal_surat' => 'PERIHAL-DIUBAH']);
    $official = app(ReviuBahanPreviewService::class)->forReview($review->fresh(), $f['actor']);
    $data = app(WorkflowReportHistory::class)->data(['records' => $f['butir']->record->newCollection([$f['butir']->record->fresh()])], $module);
    $round = $data['workflowReport']['butirs'][0]['rounds']->sole();
    expect($round['sources']->pluck('content')->all())->toBe(array_column($official['responses'], 'content'))
        ->and($official['responses'])->toHaveCount($count)->and($official['missing_units'])->toHaveCount(3 - $count)
        ->and($round['missing'])->toHaveCount(3 - $count)->and($official['late_responses'])->toBe([])
        ->and($db->table('tb_kompilasi')->first())->toEqual($snapshot);
    foreach ([false, true] as $custom) {
        $html = compiledReportHtml($f, $custom);
        expect($html)->not->toContain('EDIT-SETELAH-KOMPILASI', 'SUSULAN-BELUM-DIKOMPILASI', 'HASIL-EDIT', 'NAMA-UNIT-DIUBAH', 'NAMA-PENGISI-DIUBAH', 'BUTIR-DIUBAH', 'SURAT-DIUBAH', 'PERIHAL-DIUBAH');
        expect(compiledReportText($html))->toContain('Kompilasi referensi', 'Tanggapan Awal', 'Belum Ditanggapi');
        if ($count < 3) {
            expect(compiledReportText($html))->toContain('Belum mengisi saat kompilasi.', 'Unit C');
        }
        if ($count) {
            expect($official['responses'][0]['author'])->toBe('Pengisi A');
            $copy = $review->kompilasiPutaran->bahan()->first()->salinan['dokumen'];
            if (! $custom) {
                expect($html)->toContain(route($module.'.report.dokumen', ['kompilasi' => $review->kompilasi_id, 'filename' => basename($copy['path'])]));
            }
            expect(Storage::disk($copy['disk'])->exists($copy['path']))->toBeTrue();
        }
    }
})->with(['snp', 'djsn'])->with([0, 1, 3]);

test('R6 seluruh kompilasi dua putaran Tindak Lanjut dicetak tanpa isian setelah Close', function (string $module, string $decision) {
    $f = compiledReportFixture($module);
    $image = compiledReportImage();
    $filename = Str::uuid().'.png';
    $path = $module.'-images/'.$f['butir']->record->id.'/'.$filename;
    Storage::disk('local')->put($path, file_get_contents($image->getPathname()));
    $imageUrl = route($module.'.butir-images.show', ['record' => $f['butir']->record->id, 'filename' => $filename], false);
    $rich = SnpButirContent::PREFIX.'<p><strong>KOMPILASI-PUTARAN-0</strong></p>'.str_repeat('<p>Isi panjang hasil kompilasi dengan format tebal dan gambar tetap terbaca.</p>', 35).'<img src="'.$imageUrl.'" data-width="50"><p>PENUTUP-ISI-PANJANG</p>';
    $reviews = [];
    foreach ([0, 1, 2] as $number) {
        compiledReportInput($f, 1, 'BAHAN-PUTARAN-'.$number, true);
        compiledReportInput($f, 2, 'BAHAN-B-PUTARAN-'.$number);
        $reviews[$number] = compiledReportCompile($f, $number === 0 ? $rich : 'KOMPILASI-PUTARAN-'.$number);
        if ($number < 2) {
            compiledReportInput($f, 3, 'SUSULAN-PUTARAN-'.$number);
        }
        compiledReportDecide($f, $reviews[$number], $number < 2 ? 'tindak_lanjut_belum_sesuai' : $decision, 'REVIU-PUTARAN-'.$number);
        $this->travel(1)->days();
        if ($number < 2) {
            $preview = app(ReviuBahanPreviewService::class)->forCompilation($f['butir']->fresh(), $f['actor']);
            expect($preview['responses'])->toBe([])->and($preview['kind_label'])->toBe('Tindak Lanjut')
                ->and($preview['round_label'])->toBe('Tindak Lanjut Putaran '.($number + 1));
        }
    }
    compiledReportInput($f, 3, 'SUSULAN-SETELAH-CLOSE');
    Storage::disk('local')->delete($path);
    $record = $f['butir']->record->fresh();
    $stored = $record->getRawOriginal();
    $rounds = app(WorkflowReportHistory::class)->rounds($f['butir']->fresh());
    expect($rounds->pluck('label')->all())->toBe(['Tanggapan Awal', 'Tindak Lanjut Putaran 1', 'Tindak Lanjut Putaran 2']);
    foreach ($reviews as $number => $review) {
        $official = app(ReviuBahanPreviewService::class)->forReview($review->fresh(), $f['actor']);
        expect($rounds[$number]['sources']->pluck('content')->all())->toBe(array_column($official['responses'], 'content'))
            ->and($official['late_responses'])->toBe([]);
    }
    foreach ([false, true] as $custom) {
        $html = compiledReportHtml($f, $custom);
        $result = compiledReportRender($html);
        foreach ([0, 1, 2] as $number) {
            expect($result['text'])->toContain('BAHAN-PUTARAN-'.$number, 'KOMPILASI-PUTARAN-'.$number, 'REVIU-PUTARAN-'.$number);
        }
        expect(strpos($result['text'], 'BAHAN-PUTARAN-0'))->toBeLessThan(strpos($result['text'], 'BAHAN-PUTARAN-1'))
            ->and(strpos($result['text'], 'BAHAN-PUTARAN-1'))->toBeLessThan(strpos($result['text'], 'BAHAN-PUTARAN-2'))
            ->and($result['text'])->toContain('PENUTUP-ISI-PANJANG', 'Belum mengisi saat kompilasi.', 'Unit C', 'Close:')
            ->not->toContain('SUSULAN-PUTARAN', 'SUSULAN-SETELAH-CLOSE')
            ->and($result['images'])->toBeGreaterThan(0)->and($result['pdf']->getCanvas()->get_page_count())->toBeGreaterThan(1);
        $route = $module === 'snp' ? ($custom ? 'download-custom' : 'download') : ($custom ? 'cetak-custom' : 'cetak');
        $download = $this->actingAs($f['actor'])->post(route($module.'.report.'.$route), [
            'record_ids' => [$record->id], 'butir_ids' => [$f['butir']->id],
            'fields' => $module === 'snp' ? ['isi_butir', 'tanggapan_unit', 'tindak_lanjut_unit', 'kompilasi_tanggapan', 'kompilasi_tindak_lanjut', 'hasil_reviu', 'status'] : ['isi_butir', 'tanggapan_tl', 'hasil_reviu', 'status'], '_download' => 1,
        ])->assertOk();
        expect($download->headers->get('Content-Type'))->toBe('application/pdf')->and($download->getContent())->toStartWith('%PDF-');
        if (getenv('COMPILATION_REPORT_ARTIFACTS')) {
            file_put_contents(getenv('COMPILATION_REPORT_ARTIFACTS').'/'.$module.'-'.($custom ? 'custom' : 'biasa').'-'.$decision.'.pdf', $download->getContent());
        }
    }
    expect($record->fresh()->getRawOriginal())->toBe($stored);
})->with(['snp', 'djsn'])->with(['tindak_lanjut_sesuai', 'tidak_dapat_ditindaklanjuti']);

test('R6 tahap belum dikompilasi dan bahan lama tidak diperkirakan menjadi bahan resmi', function (string $module, bool $legacy) {
    $f = compiledReportFixture($module);
    $source = compiledReportInput($f, 1, 'ISIAN-MENTAH-RAHASIA');
    if ($legacy) {
        DB::connection('mysql_'.$module)->table('tb_kompilasi')->insert(['id_butir_'.$module => $f['butir']->{'id_butir_'.$module}, 'tahap_id' => $source->tahap_id,
            'putaran_id' => $source->putaran_id, 'hasil_kompilasi' => 'KOMPILASI-LAMA-TANPA-BUKTI', 'status' => 'dalam_proses_reviu_dewas']);
    }
    foreach ([false, true] as $custom) {
        $html = compiledReportHtml($f, $custom);
        expect($html)->not->toContain('ISIAN-MENTAH-RAHASIA', 'KOMPILASI-LAMA-TANPA-BUKTI')
            ->and(compiledReportText($html))->toContain('Belum tersedia bahan proses resmi untuk dicetak');
        if ($legacy) {
            expect(compiledReportText($html))->toContain('memerlukan rekonsiliasi');
        }
    }
})->with(['snp', 'djsn'])->with([false, true]);

test('R6 putaran aktif tanpa kompilasi tidak menambahkan proses dalam PDF', function (string $module) {
    $f = compiledReportFixture($module);
    compiledReportInput($f, 1, 'TANGGAPAN-RESMI-AWAL');
    $review = compiledReportCompile($f);
    compiledReportDecide($f, $review, 'tindak_lanjut_belum_sesuai');
    compiledReportInput($f, 1, 'PERBAIKAN-BELUM-DIKOMPILASI');
    foreach ([false, true] as $custom) {
        $text = compiledReportText(compiledReportHtml($f, $custom));
        expect($text)->toContain('TANGGAPAN-RESMI-AWAL')->not->toContain('PERBAIKAN-BELUM-DIKOMPILASI', 'Tindak Lanjut Putaran 1');
    }
})->with(['snp', 'djsn']);

test('R6 filter custom memilih butir kolom dan unit secara terpisah untuk Tanggapan dan Tindak Lanjut', function () {
    $f = compiledReportFixture('snp');
    compiledReportInput($f, 1, 'AWAL-UNIT-A');
    compiledReportInput($f, 2, 'AWAL-UNIT-B');
    $initial = compiledReportCompile($f);
    compiledReportDecide($f, $initial, 'tindak_lanjut_belum_sesuai');
    compiledReportInput($f, 1, 'PERBAIKAN-UNIT-A');
    compiledReportInput($f, 2, 'PERBAIKAN-UNIT-B');
    compiledReportCompile($f);
    $db = DB::connection('mysql_snp');
    $db->table('tb_butir_snp')->insert(['id_snp' => $f['butir']->id_snp, 'id_butir_snp' => 'BUTIR-DI-LUAR-PILIHAN', 'butir_snp' => 'BUTIR-LAIN-TERSEMBUNYI']);
    $html = compiledReportHtml($f, true, ['fields' => ['tanggapan_unit', 'tindak_lanjut_unit'], 'tanggapan_unit_kerja_ids' => [1], 'tindak_lanjut_unit_kerja_ids' => [2]]);
    $text = compiledReportText($html);
    expect($text)->toContain('AWAL-UNIT-A', 'PERBAIKAN-UNIT-B', 'Tindak Lanjut Putaran 1')
        ->not->toContain('AWAL-UNIT-B', 'PERBAIKAN-UNIT-A', 'BUTIR-LAIN-TERSEMBUNYI', 'Kompilasi referensi', 'STATUS SURAT TERKINI');
    expect(app(WorkflowReportHistory::class)->unitOptions($f['butir']->fresh())->pluck('id')->all())->toBe([1, 2, 3]);
});

test('R6 perbaikan lama yang dipetakan dari tabel Tanggapan dibaca sebagai Tindak Lanjut terkompilasi', function (string $module) {
    $f = compiledReportFixture($module);
    compiledReportInput($f, 1, 'BAHAN-AWAL');
    $review = compiledReportCompile($f);
    compiledReportDecide($f, $review, 'tindak_lanjut_belum_sesuai');
    $stage = $f['butir']->record->fresh()->tahapAktif;
    DB::connection('mysql_'.$module)->table('tb_tanggapan')->insert([
        'id_butir_'.$module => $f['butir']->{'id_butir_'.$module}, 'tahap_id' => $stage->id, 'putaran_id' => $stage->putaran_asal_id,
        'butir_pic_id' => $f['butir']->butirPics()->where('unit_kerja_id', 1)->firstOrFail()->id,
        'tanggapan' => 'PERBAIKAN-LAMA-TERPETAKAN', 'created_by' => $f['users'][1]->id, 'created_at' => now(),
    ]);
    $live = app(ReviuBahanPreviewService::class)->forCompilation($f['butir']->fresh(), $f['actor']);
    expect($live['responses'][0]['unit'])->toBe('A - Unit A');
    $review = compiledReportCompile($f);
    $preview = app(ReviuBahanPreviewService::class)->forReview($review->fresh(), $f['actor']);
    $round = app(WorkflowReportHistory::class)->rounds($f['butir']->fresh())->last();
    expect($preview['kind_label'])->toBe('Tindak Lanjut')->and($round['responses'])->toBeEmpty()
        ->and($round['followups']->sole()['content'])->toBe('PERBAIKAN-LAMA-TERPETAKAN');
})->with(['snp', 'djsn']);

test('R6 gambar bahan dan tanggal saat kompilasi tetap utuh setelah sumber dihapus serta tanggal disetujui', function (string $module) {
    $f = compiledReportFixture($module);
    $first = compiledReportCompile($f);
    compiledReportDecide($f, $first, 'tindak_lanjut_belum_sesuai');
    $this->travel(16)->days();
    $filename = Str::uuid().'.png';
    $path = $module.'-images/'.$f['butir']->record->id.'/'.$filename;
    $image = compiledReportImage();
    Storage::disk('local')->put($path, file_get_contents($image->getPathname()));
    $url = route($module.'.butir-images.show', ['record' => $f['butir']->record->id, 'filename' => $filename], false);
    compiledReportInput($f, 1, SnpButirContent::PREFIX.'<p><strong>GAMBAR-BAHAN-RESMI</strong></p><img src="'.$url.'" data-width="50">');
    $review = compiledReportCompile($f, extra: ['ubah_tgl' => '2026-12-20', 'alasan_pengajuan_tgl' => 'Memerlukan tambahan waktu']);
    $this->actingAs($f['actor'])->patch(route($module.'.reviu.update', $review), [
        'hasil_review' => 'Perubahan tanggal disetujui', 'status' => 'belum_ditanggapi',
        'status_pengajuan_tgl' => 'disetujui', 'alasan_keputusan_tgl' => 'Tambahan waktu diberikan reviewer',
    ])->assertRedirect()->assertSessionHasNoErrors();
    Storage::disk('local')->delete($path);
    foreach ([false, true] as $custom) {
        $html = compiledReportHtml($f, $custom, ['fields' => $module === 'snp' ? ['tindak_lanjut_unit', 'jatuh_tempo'] : ['tanggapan_tl', 'jatuh_tempo']]);
        $result = compiledReportRender($html);
        expect($result['text'])->toContain('GAMBAR-BAHAN-RESMI', 'Batas saat kompilasi: 15/11/2026 23.59 WIB',
            'Diisi melewati batas saat pengisian: 15/11/2026 23.59 WIB.', '20/12/2026', 'Tambahan waktu diberikan reviewer')
            ->and($result['images'])->toBe(1);
    }
    expect($f['butir']->record->fresh()->tahapAktif->jatuh_tempo->toDateString())->toBe('2026-12-20');
})->with(['snp', 'djsn']);

test('R6 salinan atau hubungan reviu yang rusak tidak membocorkan bahan dari putaran lain', function (string $module, string $damage) {
    $f = compiledReportFixture($module);
    compiledReportInput($f, 1, 'BAHAN-AWAL');
    $first = compiledReportCompile($f, 'KOMPILASI-AWAL');
    compiledReportDecide($f, $first, 'tindak_lanjut_belum_sesuai');
    compiledReportInput($f, 1, 'BAHAN-PERBAIKAN');
    $second = compiledReportCompile($f, 'KOMPILASI-PERBAIKAN');
    $db = DB::connection('mysql_'.$module);
    if ($damage === 'review') {
        $db->table('tb_review')->where('id', $second->id)->update(['kompilasi_id' => $first->kompilasi_id]);
    } elseif ($damage === 'material') {
        $db->table('tb_kompilasi_bahan')->where('kompilasi_id', $second->kompilasi_id)->update(['tahap_id' => $first->tahap_id]);
    } else {
        $db->table('tb_kompilasi')->where('id', $second->kompilasi_id)->update(['status_bahan' => 'perlu_rekonsiliasi']);
    }
    $preview = app(ReviuBahanPreviewService::class)->forReview($second->fresh(), $f['actor']);
    expect($preview['needs_reconciliation'])->toBeTrue()->and($preview['responses'])->toBe([])->and($preview['compilation'])->toBeNull();
    $html = compiledReportHtml($f);
    expect(compiledReportText($html))->toContain('KOMPILASI-AWAL');
    if ($damage !== 'review') {
        expect($html)->not->toContain('BAHAN-PERBAIKAN', 'KOMPILASI-PERBAIKAN');
    }
})->with(['snp', 'djsn'])->with(['review', 'material', 'header']);

test('R6 hak akses preview dan PDF tetap mengikuti role serta komite lama', function (string $module) {
    $f = compiledReportFixture($module);
    $review = compiledReportCompile($f);
    $outsider = User::factory()->create(['status' => 'active']);
    foreach (['cetak', 'cetak-custom'] as $route) {
        $this->actingAs($outsider)->post(route($module.'.report.'.$route), ['record_ids' => [$f['butir']->record->id]])->assertForbidden();
    }
    $this->get(route($module.'.kompilasi.index'))->assertForbidden();
    $this->get(route($module.'.reviu.index'))->assertForbidden();
    $reviewer = workflowUser('moderator_'.$module);
    $reviewer->komite()->detach();
    expect(fn () => app(ReviuBahanPreviewService::class)->forReview($review->fresh(), $reviewer))->toThrow(HttpException::class);
})->with(['snp', 'djsn']);

test('R6 browser mempertahankan formulir preview nol satu tiga bahan pada tahap awal dan Tindak Lanjut', function (string $module) {
    $f = compiledReportFixture($module);
    $pages = [];
    $images = [];
    foreach ([false, true] as $followUp) {
        foreach ([0, 1, 3] as $count) {
            if ($pages !== []) {
                $f['butir'] = workflowButir($module);
                foreach ([2, 3] as $id) {
                    $f['butir']->butirPics()->create(['jenis_pic' => 'pendukung', 'unit_kerja_id' => $id]);
                }
            }
            if ($followUp) {
                $review = compiledReportCompile($f);
                compiledReportDecide($f, $review, 'tindak_lanjut_belum_sesuai');
            }
            $imageHtml = '';
            if ($count === 3) {
                $filename = Str::uuid().'.png';
                $path = $module.'-images/'.$f['butir']->record->id.'/'.$filename;
                $image = compiledReportImage();
                Storage::disk('local')->put($path, file_get_contents($image->getPathname()));
                $url = route($module.'.butir-images.show', ['record' => $f['butir']->record->id, 'filename' => $filename], false);
                $images[$url] = base64_encode(Storage::disk('local')->get($path));
                $imageHtml = '<img src="'.$url.'" data-width="50">';
            }
            $dates = [];
            for ($unit = 1; $unit <= $count; $unit++) {
                $content = $unit === 1 && $count === 3 ? SnpButirContent::PREFIX.'<p><strong>Tanggapan panjang</strong></p>'.str_repeat('<p>Bahan panjang yang dapat digulir.</p>', 40).$imageHtml : 'Jawaban '.$unit.' <b>literal</b>';
                $source = compiledReportInput($f, $unit, $content);
                $dates[] = $source->created_at->format('d/m/Y H:i');
                $this->travel(1)->minutes();
            }
            $context = ['count' => $count, 'dates' => $dates, 'butirId' => $f['butir']->{'id_butir_'.$module},
                'roundLabel' => $followUp ? 'Tindak Lanjut Putaran 1' : 'Tanggapan Awal', 'kindLabel' => $followUp ? 'Tindak Lanjut' : 'Tanggapan'];
            $params = ['keyword' => $f['butir']->{'id_butir_'.$module}];
            $pages[] = $context + ['kind' => 'kompilasi', 'hasDateProposal' => $followUp, 'action' => '/'.$module.'/kompilasi/'.$f['butir']->id,
                'html' => $this->actingAs($f['actor'])->get(route($module.'.kompilasi.index', $params))->assertOk()->getContent()];
            $review = compiledReportCompile($f, SnpButirContent::PREFIX.'<p><em>Kompilasi referensi</em></p>'.$imageHtml);
            $copies = $review->kompilasiPutaran->bahan->flatMap(fn ($material) => $material->salinan['gambar'])->concat($review->kompilasiPutaran->salinan_konteks['kompilasi']['gambar']);
            foreach ($copies as $copy) {
                $images[$copy['url']] = base64_encode(Storage::disk('local')->get($copy['path']));
            }
            $pages[] = $context + ['kind' => 'reviu', 'action' => '/'.$module.'/reviu/'.$review->id,
                'compilationDate' => $review->kompilasiPutaran->bahan_dicatat_at->format('d/m/Y H:i'),
                'html' => $this->get(route($module.'.reviu.index', $params))->assertOk()->getContent()];
        }
    }
    $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
    $process = new Process(['node', base_path('tests/Unit/ReviuBahanPreview.browser.mjs')], base_path());
    $upload = UploadedFile::fake()->create('lampiran.pdf', 1, 'application/pdf');
    $process->setInput(json_encode(['pages' => $pages, 'images' => $images, 'compiler' => $f['actor']->name, 'upload' => $upload->getPathname(),
        'css' => $manifest['resources/css/app.css']['file'], 'js' => $manifest['resources/js/app.js']['file'], 'screenshot' => base_path('tmp/r6-preview-'.$module)]));
    $process->setTimeout(120)->run();
    expect($process->isSuccessful())->toBeTrue($process->getOutput().$process->getErrorOutput());
})->with(['snp', 'djsn']);
