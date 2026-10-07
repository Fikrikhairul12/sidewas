<?php

use App\Services\SnpButirContent;
use App\Services\SnpReportPdfLabels;
use Dompdf\Canvas;
use Dompdf\Dompdf;
use Dompdf\Frame;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Shared\Drawing as DrawingDimensions;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Html;
use Symfony\Component\Process\Process;
use Tests\TestCase;

uses(TestCase::class);

function multiReportImage(string $module, int $width, int $height, string $filename): string
{
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, imagecolorallocate($image, 219, 234, 254));
    imagestring($image, 4, 20, 20, 'Gambar '.$module, imagecolorallocate($image, 30, 64, 175));
    ob_start();
    imagepng($image);
    $bytes = ob_get_clean();
    imagedestroy($image);
    Storage::disk('local')->put($module.'-images/1/'.$filename, $bytes);

    return '/'.$module.'/perekaman/1/gambar/'.$filename;
}

function multiReportRecords(string $module, string $content): Collection
{
    $recordClass = 'App\\Models\\'.ucfirst($module).'Record';
    $butirClass = 'App\\Models\\'.ucfirst($module).'Butir';
    $records = collect();
    foreach ([1, 2] as $recordId) {
        $butirs = collect();
        foreach ($recordId === 1 ? [1, 2] : [3] as $id) {
            $butir = (new $butirClass)->forceFill(['id' => $id, 'id_butir_'.$module => strtoupper($module).'.0'.$id, SnpButirContent::contentField($module) => $id === 1 ? $content : 'TEKS-LAMA-'.$id.' <literal>', 'status' => 'dalam_proses', 'tanggal_'.$module => '2026-10-07', 'agenda_'.$module => 'AGENDA-'.$id]);
            foreach (['butirPics', 'butirDirektorats', 'reviews'] as $relation) {
                $butir->setRelation($relation, collect());
            }
            $butir->setRelation('reviewTindakLanjut', (object) ['hasil_review' => 'HASIL-REVIU-'.$id, 'status' => 'dalam_proses_reviu_dewan_pengawas']);
            $butir->setRelation('tanggapan', (object) ['tanggapan' => 'TANGGAPAN-'.$id, 'deliverables' => 'DEL-TANGGAPAN-'.$id, 'dokumen' => null, 'status_pengajuan_tgl' => 'disetujui', 'ubah_tgl' => '2026-12-01', 'review' => (object) ['status' => 'dalam_proses_tindak_lanjut_direksi', 'hasil_review' => 'REVIU-TANGGAPAN-'.$id]]);
            $butir->setRelation('tindakLanjuts', collect([1, 2])->map(fn (int $stage): object => (object) ['id' => $stage, 'tindak_lanjut' => 'TL-'.$id.'-'.$stage, 'deliverables' => 'DEL-'.$id.'-'.$stage, 'jth_tempo' => '2026-12-01', 'dokumen' => 'dokumen-'.$id.'-'.$stage.'.pdf', 'unitKerja' => null, 'butirPic' => null, 'reviews' => collect([(object) ['id' => 1, 'tahap_review' => 'tindak_lanjut', 'hasil_review' => 'REVIU-'.$id.'-'.$stage, 'status' => 'selesai_tuntas']])]));
            $butirs->push($butir);
        }
        $record = (new $recordClass)->forceFill(['id' => $recordId, 'nomor_surat' => 'SURAT-'.$recordId, 'tanggal_surat' => '2026-10-07', 'perihal_surat' => 'PERIHAL-'.$recordId, 'jth_tempo' => '2026-11-06']);
        $record->setRelation('butir'.ucfirst($module), $butirs);
        $records->push($record);
    }

    return $records;
}

test('PDF and Excel preserve complete rich content images and every related follow up in each module', function (string $module) {
    Storage::fake('local');
    $wide = multiReportImage($module, 700, 180, '12345678-1234-1234-1234-123456789abc.png');
    $tall = multiReportImage($module, 200, 1600, '12345678-1234-1234-1234-123456789abd.png');
    $content = SnpButirContent::PREFIX.'<p style="text-align:center"><strong>AWAL-BUTIR</strong></p><p><em>Miring</em> <u>Garis bawah</u></p><ol start="3"><li><p>Nomor tiga</p></li><li><p>Nomor empat</p></li></ol><p style="text-align:justify"><span style="font-size:20px">'.str_repeat('ISI-LENGKAP ', 400).'</span></p><p><img src="'.$wide.'" data-width="50"></p><p>ANTAR-GAMBAR</p><p style="text-align:right"><img src="'.$tall.'" data-width="100"></p><p>=AKHIR-BUTIR</p>';
    $records = multiReportRecords($module, $content);
    $contentField = $module === 'djsn' ? 'isi_butir' : 'keputusan';
    $fields = $module === 'djsn'
        ? ['surat', 'id_butir', $contentField, 'tanggapan', 'tindak_lanjut', 'deliverable', 'dokumen', 'jatuh_tempo', 'hasil_reviu', 'status']
        : ['surat', 'tgl_agenda', $contentField, 'direktorat', 'unit_pic', 'tindak_lanjut', 'deliverable', 'dokumen', 'jatuh_tempo', 'hasil_reviu', 'status'];
    $pdfFields = $module === 'djsn' ? ['surat', 'id_butir', 'isi_butir', 'pic_unit', 'tanggapan_tl', 'deliverable', 'dokumen', 'jatuh_tempo', 'komite', 'hasil_reviu', 'status'] : $fields;
    $fixtures = [];
    foreach (['pdf', 'pdf-custom'] as $template) {
        $html = view('layouts.'.$module.'.report.'.$template, ['records' => $records, 'selectedFields' => $pdfFields, 'fieldLabels' => array_combine($pdfFields, $pdfFields), 'printedBy' => 'Penguji', 'printedAt' => '07/10/2026'])->render();
        $document = new DOMDocument;
        @$document->loadHTML($html);
        $xpath = new DOMXPath($document);
        $cells = $xpath->query('//td[@data-report-content="'.strtoupper($module).'.01"]');
        $text = implode('', array_map(fn ($cell): string => $cell->textContent, iterator_to_array($cells)));
        expect(substr_count(preg_replace('/\s+/u', ' ', $text), 'ISI-LENGKAP'))->toBe(400)
            ->and($text)->toContain('AWAL-BUTIR', '3. Nomor tiga', '4. Nomor empat', '=AKHIR-BUTIR', 'ANTAR-GAMBAR')
            ->and($html)->toContain('font-size:20px', 'font-weight:bold', 'font-style:italic', 'text-decoration:underline', 'data:image/png;base64')
            ->not->toContain('snp-rich:v1', '&lt;p&gt;');
        expect($xpath->query('//img[not(ancestor::td[@data-report-content])]'))->toHaveCount(0);
        $headerCount = $xpath->query('//thead/tr/th')->length;
        foreach ($xpath->query('//tbody/tr') as $row) {
            expect($xpath->query('./td', $row))->toHaveCount($headerCount);
        }
        foreach ([1, 2, 3] as $id) {
            foreach ([1, 2] as $stage) {
                expect(substr_count($document->textContent, 'TL-'.$id.'-'.$stage))->toBe(1)
                    ->and(substr_count($document->textContent, 'DEL-'.$id.'-'.$stage))->toBe(1);
            }
        }
        expect($document->textContent)->toContain('TEKS-LAMA-2 <literal>', 'TEKS-LAMA-3 <literal>', 'SURAT-1', 'SURAT-2');
        $renderedText = [];
        $labels = [];
        $continuations = [];
        $contentPages = [];
        $images = [];
        $pdf = new Dompdf;
        $pdf->setPaper('legal', 'landscape');
        $pdf->getOptions()->setIsJavascriptEnabled(false);
        $pdf->setCallbacks([...app(SnpReportPdfLabels::class)->callbacks(), ['event' => 'end_frame', 'f' => function (Frame $frame, Canvas $canvas) use (&$renderedText, &$labels, &$continuations, &$contentPages, &$images, $module): void {
            $node = $frame->get_node();
            $page = $canvas->get_page_number();
            if ($node instanceof DOMText) {
                $text = trim($node->textContent);
                $renderedText[] = $text;
                if (in_array($text, array_map(fn (int $id): string => strtoupper($module).'.0'.$id, [1, 2, 3]), true)) {
                    $labels[$text][$page] = ($labels[$text][$page] ?? 0) + 1;
                }
                if ($text === 'Lanjutan') {
                    for ($parent = $node->parentNode; $parent instanceof DOMElement; $parent = $parent->parentNode) {
                        if ($parent->hasAttribute('data-snp-butir-label')) {
                            $id = $parent->getAttribute('data-snp-butir-label');
                            $continuations[$id][$page] = ($continuations[$id][$page] ?? 0) + 1;
                            break;
                        }
                    }
                }
            }
            if ($node instanceof DOMElement && $node->tagName === 'td' && $node->hasAttribute('data-report-content')) {
                $contentPages[$node->getAttribute('data-report-content')][$page] = true;
            }
            if ($node instanceof DOMElement && $node->tagName === 'img') {
                for ($parent = $frame->get_parent(); $parent; $parent = $parent->get_parent()) {
                    $element = $parent->get_node();
                    if ($element instanceof DOMElement && $element->hasAttribute('data-report-content')) {
                        $images[] = ['box' => $frame->get_border_box(), 'cell' => $parent->get_content_box()];
                        break;
                    }
                }
            }
        }]]);
        $pdf->loadHtml($html);
        $pdf->render();
        $printedText = preg_replace('/\s+/u', ' ', implode(' ', $renderedText));
        expect(substr_count($printedText, 'ISI-LENGKAP'))->toBe(400, 'Rendered pages: '.$pdf->getCanvas()->get_page_count().'; text: '.mb_substr($printedText, 0, 500))
            ->and($printedText)->toContain('AWAL-BUTIR', 'ANTAR-GAMBAR', '=AKHIR-BUTIR', 'TEKS-LAMA-2 <literal>', 'TEKS-LAMA-3 <literal>')
            ->and($pdf->getCanvas()->get_page_count())->toBeGreaterThan(2)
            ->and($pdf->getCanvas()->get_width())->toBe(1008.0)
            ->and($pdf->getCanvas()->get_height())->toBe(612.0)
            ->and($images)->toHaveCount(4);
        foreach ($images as $image) {
            expect($image['box']['x'])->toBeGreaterThanOrEqual($image['cell']['x'] - 1)
                ->and($image['box']['x'] + $image['box']['w'])->toBeLessThanOrEqual($image['cell']['x'] + $image['cell']['w'] + 1)
                ->and($image['box']['y'] + $image['box']['h'])->toBeLessThan(574);
        }
        foreach ($contentPages as $id => $pages) {
            $first = array_key_first($pages);
            foreach ($pages as $page => $present) {
                expect($labels[$id][$page] ?? 0)->toBe($page !== $first || $module === 'djsn' ? 1 : 0)
                    ->and($continuations[$id][$page] ?? 0)->toBe($page !== $first ? 1 : 0);
            }
        }
        foreach ([1, 2, 3] as $id) {
            foreach ([1, 2] as $stage) {
                expect(substr_count($printedText, 'TL-'.$id.'-'.$stage))->toBe(1)
                    ->and(substr_count($printedText, 'DEL-'.$id.'-'.$stage))->toBe(1);
            }
        }
        $bytes = $pdf->output();
        expect($bytes)->toStartWith('%PDF-');
        if (getenv('MULTI_REPORT_ARTIFACTS')) {
            file_put_contents(getenv('MULTI_REPORT_ARTIFACTS').'/'.$module.'-'.$template.'.pdf', $bytes);
            file_put_contents(getenv('MULTI_REPORT_ARTIFACTS').'/'.$module.'-'.$template.'.html', $html);
        }
        $fixtures[$template] = $html;
    }
    $process = new Process(['node', base_path('tests/Unit/MultiModuleRichReport.browser.mjs')], base_path());
    $process->setInput(json_encode(['module' => $module, 'fixtures' => $fixtures], JSON_THROW_ON_ERROR));
    $process->setTimeout(90);
    $process->run();
    expect($process->isSuccessful())->toBeTrue($process->getOutput().$process->getErrorOutput());

    $exportClass = 'App\\Exports\\'.ucfirst($module).'ReportExport';
    $bytes = Excel::raw(new $exportClass($records, $fields, array_combine($fields, $fields)), Maatwebsite\Excel\Excel::XLSX);
    $path = tempnam(sys_get_temp_dir(), 'multi-rich-report-');
    file_put_contents($path, $bytes);
    try {
        $sheet = IOFactory::load($path)->getActiveSheet();
        $column = Coordinate::stringFromColumnIndex(array_search($contentField, $fields, true) + 1);
        $rows = range(2, $sheet->getHighestRow());
        $text = implode('', array_map(fn (int $row): string => (string) $sheet->getCell($column.$row)->getValue(), $rows));
        expect(substr_count($text, 'ISI-LENGKAP'))->toBe(400)
            ->and($text)->toContain('AWAL-BUTIR', 'ANTAR-GAMBAR', '=AKHIR-BUTIR', 'TEKS-LAMA-2 <literal>', 'TEKS-LAMA-3 <literal>')
            ->not->toContain('snp-rich:v1', '<p>')
            ->and($sheet->getColumnDimension($column)->getWidth())->toBe(90.0)
            ->and($sheet->getDrawingCollection())->toHaveCount(2);
        $find = fn (string $text): string => $column.collect($rows)->first(fn (int $row): bool => str_contains((string) $sheet->getCell($column.$row)->getValue(), $text));
        expect($sheet->getStyle($find('AWAL-BUTIR'))->getAlignment()->getHorizontal())->toBe('center')
            ->and($sheet->getCell($find('Miring'))->getValue())->toBeInstanceOf(RichText::class)
            ->and($sheet->getCell($find('=AKHIR-BUTIR'))->getDataType())->toBe('s')
            ->and($text)->toContain('3. Nomor tiga', '4. Nomor empat');
        $fonts = collect($sheet->getCell($find('Miring'))->getValue()->getRichTextElements())->map(fn ($run) => $run->getFont());
        expect($fonts->contains(fn ($font): bool => $font->getItalic()))->toBeTrue()
            ->and($fonts->contains(fn ($font): bool => $font->getUnderline() === 'single'))->toBeTrue()
            ->and($sheet->getCell($find('AWAL-BUTIR'))->getValue()->getRichTextElements()[0]->getFont()->getBold())->toBeTrue()
            ->and($sheet->getStyle($column.'2')->getBorders()->getBottom()->getBorderStyle())->toBe(Border::BORDER_NONE)
            ->and($sheet->getStyle($column.'3')->getBorders()->getTop()->getBorderStyle())->toBe(Border::BORDER_NONE);
        foreach ($sheet->getDrawingCollection() as $drawing) {
            expect($drawing->getCoordinates())->toStartWith($column)
                ->and($drawing->getOffsetX() + $drawing->getWidth())->toBeLessThanOrEqual(DrawingDimensions::cellDimensionToPixels(90, $sheet->getParent()->getDefaultStyle()->getFont()));
        }
        foreach ($sheet->getRowDimensions() as $row) {
            expect($row->getRowHeight())->toBeLessThanOrEqual(409);
        }
        $allText = implode(' ', array_map(fn (array $row): string => implode(' ', $row), $sheet->toArray()));
        foreach ([1, 2, 3] as $id) {
            foreach ([1, 2] as $stage) {
                expect(substr_count($allText, 'TL-'.$id.'-'.$stage))->toBe(1)
                    ->and(substr_count($allText, 'DEL-'.$id.'-'.$stage))->toBe(1);
            }
        }
        expect($sheet->getStyle($column.'2')->getBorders()->getTop()->getBorderStyle())->toBe(Border::BORDER_THIN);
        if (getenv('MULTI_REPORT_ARTIFACTS')) {
            file_put_contents(getenv('MULTI_REPORT_ARTIFACTS').'/'.$module.'.xlsx', $bytes);
            (new Html($sheet->getParent()))->setEmbedImages(true)->save(getenv('MULTI_REPORT_ARTIFACTS').'/'.$module.'-excel.html');
        }
    } finally {
        unlink($path);
    }
    foreach ([[$contentField], ['status', 'tindak_lanjut']] as $selection) {
        $pdfSelection = $module === 'djsn' ? array_map(fn (string $field): string => $field === 'tindak_lanjut' ? 'tanggapan_tl' : $field, $selection) : $selection;
        $html = view('layouts.'.$module.'.report.pdf-custom', ['records' => $records, 'selectedFields' => $pdfSelection, 'fieldLabels' => array_combine($pdfSelection, $pdfSelection)])->render();
        expect($html)->not->toContain('snp-rich:v1');
        if (! in_array($contentField, $selection, true)) {
            expect($html)->not->toContain('data:image/', 'AWAL-BUTIR');
        }
        expect(fn () => Excel::raw(new $exportClass($records, $selection, array_combine($selection, $selection)), Maatwebsite\Excel\Excel::XLSX))->not->toThrow(Throwable::class);
    }
})->with(['ragab', 'rawas', 'djsn', 'eksternal']);

test('report download responses contain actual DomPDF bytes for regular and custom reports', function (string $module) {
    if (getenv('REPORT_TEST_PROC_OPEN_DISABLED')) {
        expect(function_exists('proc_open'))->toBeFalse();
    }
    Storage::fake('local');
    $image = multiReportImage($module, 300, 100, '12345678-1234-1234-1234-123456789abc.png');
    $records = multiReportRecords($module, SnpButirContent::PREFIX.'<p><strong>Isi butir lengkap</strong></p><img src="'.$image.'"><p>Sesudah gambar</p>');
    $class = 'App\\Http\\Controllers\\'.ucfirst($module).'\\Report'.ucfirst($module).'Controller';
    $controller = new $class;
    $download = new ReflectionMethod($controller, 'downloadPdf');
    $contentField = $module === 'djsn' ? 'isi_butir' : 'keputusan';
    foreach (['pdf', 'pdf-custom'] as $template) {
        $filename = $module.'-'.$template.'.pdf';
        $response = $download->invoke($controller, 'layouts.'.$module.'.report.'.$template, [
            'records' => $records,
            'selectedFields' => [$contentField],
            'fieldLabels' => [$contentField => 'Isi butir'],
        ], $filename);
        expect($response->getStatusCode())->toBe(200)
            ->and($response->headers->get('Content-Type'))->toBe('application/pdf')
            ->and($response->headers->get('Content-Disposition'))->toContain('attachment', $filename)
            ->and($response->getContent())->toStartWith('%PDF-');
    }
})->with(['ragab', 'rawas', 'djsn', 'eksternal']);

test('custom Excel keeps images with their owning butir after row insertion and when follow ups are absent', function (string $module) {
    Storage::fake('local');
    $image = multiReportImage($module, 200, 80, '12345678-1234-1234-1234-123456789abc.png');
    $value = SnpButirContent::PREFIX.'<p>AWAL</p><img src="'.$image.'"><p>'.str_repeat('PANJANG ', 700).'</p><p>AKHIR</p>';
    $records = multiReportRecords($module, $value);
    $field = SnpButirContent::contentField($module);
    $records[0]->{'butir'.ucfirst($module)}[0]->setRelation('tindakLanjuts', collect());
    $records[0]->{'butir'.ucfirst($module)}[1]->{$field} = SnpButirContent::PREFIX.'<p>BUTIR-KEDUA</p><img src="'.$image.'" data-width="50">';
    $contentField = $module === 'djsn' ? 'isi_butir' : 'keputusan';
    $ownerField = $module === 'djsn' ? 'id_butir' : 'tgl_agenda';
    $fields = ['status', $ownerField, $contentField];
    $exportClass = 'App\\Exports\\'.ucfirst($module).'ReportExport';
    $bytes = Excel::raw(new $exportClass($records, $fields, array_combine($fields, $fields)), Maatwebsite\Excel\Excel::XLSX);
    $path = tempnam(sys_get_temp_dir(), 'multi-image-owner-');
    file_put_contents($path, $bytes);
    try {
        $sheet = IOFactory::load($path)->getActiveSheet();
        expect($sheet->getDrawingCollection())->toHaveCount(2);
        foreach ($sheet->getDrawingCollection() as $drawing) {
            expect($drawing->getCoordinates())->toStartWith('C');
            $row = substr($drawing->getCoordinates(), 1);
            $cell = $sheet->getCell('B'.$row);
            $range = $cell->getMergeRange();
            $owner = (string) ($range ? $sheet->getCell(explode(':', $range)[0])->getValue() : $cell->getValue());
            $id = str_contains($drawing->getName(), '.02') ? 2 : 1;
            expect($owner)->toContain($module === 'djsn' ? strtoupper($module).'.0'.$id : 'AGENDA-'.$id);
        }
        expect(implode('', array_column($sheet->toArray(), 2)))->toContain('AKHIR', 'BUTIR-KEDUA', 'TEKS-LAMA-3 <literal>');
    } finally {
        unlink($path);
    }
})->with(['ragab', 'rawas', 'djsn', 'eksternal']);
