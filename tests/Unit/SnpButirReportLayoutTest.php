<?php

use App\Exports\SnpReportExport;
use App\Models\SnpButir;
use App\Models\SnpRecord;
use App\Services\SnpButirContent;
use App\Services\SnpButirReportContent;
use Dompdf\Dompdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Drawing as DrawingDimensions;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Html;
use Tests\TestCase;

uses(TestCase::class);

function snpLayoutImage(int $width = 1600, int $height = 1000): string
{
    Storage::fake('local');
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, imagecolorallocate($image, 219, 234, 254));
    imagestring($image, 5, 20, 20, 'Gambar butir SNP', imagecolorallocate($image, 30, 64, 175));
    imagerectangle($image, 5, 5, $width - 6, $height - 6, imagecolorallocate($image, 30, 64, 175));
    ob_start();
    imagepng($image);
    $bytes = ob_get_clean();
    imagedestroy($image);
    $name = '12345678-1234-1234-1234-123456789abc.png';
    Storage::disk('local')->put('snp-images/1/'.$name, $bytes);

    return '/snp/perekaman/1/gambar/'.$name;
}

function snpLayoutRecords(array $values): Collection
{
    $butirs = collect($values)->map(function ($value, $index) {
        $butir = (new SnpButir)->forceFill(['id' => $index + 1, 'id_butir_snp' => 'SNP.0'.($index + 1), 'butir_snp' => $value]);
        foreach (['butirPics', 'kompilasis', 'kompilasiTindakLanjuts', 'tanggapan', 'tindakLanjuts', 'reviews'] as $relation) {
            $butir->setRelation($relation, collect());
        }
        $butir->setRelation('kompilasiTanggapan', null)->setRelation('kompilasiTindakLanjut', null);

        return $butir;
    });
    $record = (new SnpRecord)->forceFill(['id' => 1, 'nomor_surat' => 'SURAT-UJI', 'perihal_surat' => 'Periksa urutan gambar dan teks']);
    $record->setRelation('butirSnp', $butirs);

    return collect([$record]);
}

test('PDF keeps long formatted text lists and wide or tall images in their original order inside table cells', function (string $template, int $width, int $height) {
    $image = snpLayoutImage($width, $height);
    $value = SnpButirContent::PREFIX.'<p style="text-align:center"><strong>AWAL-BUTIR</strong></p><ol start="3"><li><p><em>Daftar pertama</em></p></li><li><p><u>Daftar kedua</u></p></li></ol><p><span style="font-size:24px">'.str_repeat('Teks ukuran besar. ', 160).'</span></p><p><img src="'.$image.'" data-width="100"></p><p>AKHIR-BUTIR</p>';
    $records = snpLayoutRecords([$value, 'BUTIR-KEDUA']);
    $html = view('layouts.snp.report.'.$template, ['records' => $records, 'selectedFields' => ['isi_butir'], 'fieldLabels' => ['isi_butir' => 'ISI BUTIR SNP']])->render();
    $document = new DOMDocument;
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);
    expect($xpath->query('//img')->length)->toBeGreaterThanOrEqual(1);
    expect($xpath->query('//img[not(ancestor::td[@data-snp-butir-content="SNP.01"])]'))->toHaveCount(0);
    $cells = $xpath->query('//td[@data-snp-butir-content="SNP.01"]');
    foreach (['SNP.01', 'SNP.02'] as $id) {
        $rows = $xpath->query('//td[@data-snp-butir-content="'.$id.'"]/parent::tr');
        foreach ($rows as $index => $row) {
            expect(str_contains($row->getAttribute('class'), 'butir-start'))->toBe($index === 0);
            expect(str_contains($row->getAttribute('class'), 'butir-end'))->toBe($index === $rows->length - 1);
        }
    }
    $text = implode('', array_map(fn ($cell) => $cell->textContent, iterator_to_array($cells)));
    expect($text)->toContain('AWAL-BUTIR', '3. Daftar pertama', '4. Daftar kedua', 'AKHIR-BUTIR')
        ->and(substr_count(preg_replace('/\s+/u', ' ', $text), 'Teks ukuran besar.'))->toBe(160)
        ->and($html)->toContain('font-size:24px', 'font-weight:bold', 'font-style:italic', 'text-decoration:underline', 'text-align:center')
        ->not->toContain('lihat lampiran', 'snp-content-appendix');
    $imageCell = $xpath->query('//img/ancestor::td')->item(0);
    $lastCell = $cells->item($cells->length - 1);
    expect($imageCell->textContent)->not->toContain('AKHIR-BUTIR')
        ->and($lastCell->textContent)->toContain('AKHIR-BUTIR')
        ->and($records[0]->butirSnp[0]->butir_snp)->toBe($value);
    $pdf = new Dompdf;
    $pdf->setPaper('legal', 'landscape');
    $pdf->loadHtml($html);
    $pdf->render();
    expect($pdf->getCanvas()->get_page_count())->toBeGreaterThan(1);
    if (getenv('SNP_EXPORT_ARTIFACTS')) {
        file_put_contents(storage_path('app/private/snp-layout-'.$template.'-'.$width.'.pdf'), $pdf->output());
        file_put_contents(storage_path('app/private/snp-layout-'.$template.'-'.$width.'.html'), $html);
    }
})->with([
    ['pdf', 1600, 1000], ['pdf', 200, 1600], ['pdf-custom', 1600, 1000], ['pdf-custom', 200, 1600],
]);

test('Excel preserves interleaved text and images within the selected content column and keeps butirs separate', function (int $width, int $height) {
    $image = snpLayoutImage($width, $height);
    $value = SnpButirContent::PREFIX.'<p>=AWAL-BUTIR</p><p><img src="'.$image.'" data-width="100"></p><p>'.str_repeat('ISI-LENGKAP ', 1200).'AKHIR-BUTIR</p><p><img src="'.$image.'" data-width="25"></p><p>SETELAH-GAMBAR-KEDUA</p>';
    $records = snpLayoutRecords([$value, SnpButirContent::PREFIX.'<p>BUTIR-KEDUA</p><img src="'.$image.'" data-width="50"><p>AKHIR-KEDUA</p>']);
    $fields = ['surat', 'isi_butir', 'id_butir', 'status'];
    $bytes = Excel::raw(new SnpReportExport($records, $fields, array_combine($fields, $fields)), Maatwebsite\Excel\Excel::XLSX);
    $path = tempnam(sys_get_temp_dir(), 'snp-layout-');
    file_put_contents($path, $bytes);
    try {
        $archive = new ZipArchive;
        $archive->open($path);
        $worksheetXml = $archive->getFromName('xl/worksheets/sheet1.xml');
        $archive->close();
        expect($worksheetXml)->toContain('showGridLines="false"');
        $sheet = IOFactory::load($path)->getActiveSheet();
        $text = implode("\n", array_map(fn ($row) => $row[1] ?? '', $sheet->toArray()));
        expect(substr_count($text, 'ISI-LENGKAP'))->toBe(1200)
            ->and($sheet->getCell('B2')->getValue())->toContain('=AWAL-BUTIR')
            ->and($sheet->getCell('B2')->getDataType())->toBe('s')
            ->and($sheet->getColumnDimension('B')->getWidth())->toBe(90.0)
            ->and($sheet->getParent()->getDefaultStyle()->getFont()->getSize())->toBe(11.0)
            ->and($sheet->getDrawingCollection())->toHaveCount(3);
        $drawings = iterator_to_array($sheet->getDrawingCollection());
        usort($drawings, fn ($a, $b) => ((int) substr($a->getCoordinates(), 1)) <=> ((int) substr($b->getCoordinates(), 1)));
        $firstImageRow = (int) substr($drawings[0]->getCoordinates(), 1);
        $secondImageRow = (int) substr($drawings[1]->getCoordinates(), 1);
        $imageEnd = function (int $row) use ($sheet): int {
            foreach ($sheet->getMergeCells() as $range) {
                [$start, $end] = Coordinate::rangeBoundaries($range);
                if ($start[0] === 2 && (int) $start[1] === $row && $end[0] === 2) {
                    return (int) $end[1];
                }
            }

            return $row;
        };
        expect($sheet->getCell('B'.($firstImageRow - 1))->getValue())->toContain('AWAL-BUTIR');
        expect($sheet->getCell('B'.($secondImageRow - 1))->getValue())->toContain('AKHIR-BUTIR');
        expect($sheet->getCell('B'.($imageEnd($secondImageRow) + 1))->getValue())->toContain('SETELAH-GAMBAR-KEDUA');
        foreach ($drawings as $drawing) {
            $row = (int) substr($drawing->getCoordinates(), 1);
            expect($drawing->getCoordinates()[0])->toBe('B');
            expect($drawing->getOffsetX() + $drawing->getWidth())->toBeLessThanOrEqual(DrawingDimensions::cellDimensionToPixels(90, $sheet->getParent()->getDefaultStyle()->getFont()));
            $areaHeight = array_sum(array_map(fn ($rowIndex) => $sheet->getRowDimension($rowIndex)->getRowHeight(), range($row, $imageEnd($row)))) / 0.75;
            expect($drawing->getOffsetY() + $drawing->getHeight())->toBeLessThanOrEqual($areaHeight);
            expect($sheet->getCell('C'.$row)->getValue())->toBe(str_contains($drawing->getName(), 'SNP.02') ? 'SNP.02' : 'SNP.01');
        }
        foreach ($sheet->getRowDimensions() as $row) {
            expect($row->getRowHeight())->toBeLessThanOrEqual(409);
        }
        expect($sheet->getMergeCells())->not->toBeEmpty();
        $lastRow = $sheet->getHighestRow();
        for ($row = 2; $row < $lastRow; $row++) {
            $sameButir = $sheet->getCell('C'.$row)->getValue() === $sheet->getCell('C'.($row + 1))->getValue();
            expect($sheet->getStyle('B'.$row)->getBorders()->getBottom()->getBorderStyle())->toBe($sameButir ? Border::BORDER_NONE : Border::BORDER_THIN);
            expect($sheet->getStyle('B'.($row + 1))->getBorders()->getTop()->getBorderStyle())->toBe($sameButir ? Border::BORDER_NONE : Border::BORDER_THIN);
            expect($sheet->getStyle('C'.$row)->getBorders()->getBottom()->getBorderStyle())->toBe($sameButir ? Border::BORDER_NONE : Border::BORDER_THIN);
        }
        expect($sheet->getStyle('B'.$lastRow)->getBorders()->getBottom()->getBorderStyle())->toBe(Border::BORDER_THIN);
        expect(count(array_filter($sheet->toArray(), fn ($row) => str_contains($row[0] ?? '', 'SURAT-UJI'))))->toBe(2);
        if (getenv('SNP_EXPORT_ARTIFACTS')) {
            file_put_contents(storage_path('app/private/snp-layout.xlsx'), $bytes);
            $sheet->setShowGridlines(false);
            (new Html($sheet->getParent()))->setEmbedImages(true)->save(storage_path('app/private/snp-layout-excel.html'));
        }
    } finally {
        unlink($path);
    }
})->with([[1600, 1000], [200, 1600]]);

test('missing images remain marked at their original position and long unbroken text is preserved', function () {
    Storage::fake('local');
    $value = SnpButirContent::PREFIX.'<p>SEBELUM</p><img src="/snp/perekaman/1/gambar/12345678-1234-1234-1234-123456789abc.png"><p>'.str_repeat('W', 2500).' SESUDAH</p>';
    $service = app(SnpButirReportContent::class);
    $chunks = $service->excelChunks($value, 600);
    $text = implode("\n", array_column($chunks, 'text'));
    expect(substr_count($text, 'W'))->toBe(2500)
        ->and(strpos($text, 'SEBELUM'))->toBeLessThan(strpos($text, '[Gambar tidak tersedia]'))
        ->and(strpos($text, '[Gambar tidak tersedia]'))->toBeLessThan(strpos($text, 'SESUDAH'));
    $html = implode('', $service->pdfChunks($value, 346));
    expect(substr_count(strip_tags($html), 'W'))->toBe(2500)
        ->and($html)->toContain('[Gambar tidak tersedia]', 'SESUDAH');
});

test('PDF continues tall images at a readable width without discarding any source pixels', function () {
    $image = snpLayoutImage(200, 1600);
    $path = SnpButirContent::imageReference($image)['path'];
    $original = Storage::disk('local')->get($path);
    $chunks = app(SnpButirReportContent::class)->pdfChunks(SnpButirContent::PREFIX.'<p><img src="'.$image.'" data-width="100"></p>', 346);
    $heights = [];
    $source = imagecreatefromstring($original);
    $top = 0;
    foreach ($chunks as $chunk) {
        $document = new DOMDocument;
        @$document->loadHTML($chunk);
        $element = $document->getElementsByTagName('img')->item(0);
        $bytes = base64_decode(explode(',', $element->getAttribute('src'), 2)[1]);
        $size = getimagesizefromstring($bytes);
        expect($size[0])->toBe(200)->and($size[1])->toBeLessThanOrEqual(560);
        $part = imagecreatefromstring($bytes);
        expect(imagecolorat($part, 10, 0))->toBe(imagecolorat($source, 10, $top));
        expect(imagecolorat($part, 10, $size[1] - 1))->toBe(imagecolorat($source, 10, $top + $size[1] - 1));
        imagedestroy($part);
        $heights[] = $size[1];
        $top += $size[1];
    }
    imagedestroy($source);
    expect(count($chunks))->toBeGreaterThan(1)
        ->and(array_sum($heights))->toBe(1600)
        ->and(Storage::disk('local')->get($path))->toBe($original);
});

test('regular PDF retains each compilation round its review dates and the latest butir status only once', function () {
    $records = snpLayoutRecords(['Isi butir singkat']);
    $butir = $records[0]->butirSnp[0];
    $records[0]->tanggal_surat = '2026-10-01';
    $butir->setRelation('kompilasiTanggapan', (object) ['hasil_kompilasi' => 'TANGGAPAN-UJI', 'deliverables' => 'DOKUMEN-UJI', 'dokumen' => null, 'ubah_tgl' => '2026-11-20', 'status_pengajuan_tgl' => 'disetujui']);
    $butir->setRelation('kompilasiTindakLanjuts', collect([
        (object) ['hasil_kompilasi' => 'TL-PERTAMA', 'deliverables' => 'DELIVERABLE-PERTAMA', 'dokumen' => null, 'putaran_tl' => 1],
        (object) ['hasil_kompilasi' => 'TL-KEDUA', 'deliverables' => 'DELIVERABLE-KEDUA', 'dokumen' => null, 'putaran_tl' => 2],
    ]));
    $butir->setRelation('reviews', collect([
        (object) ['id' => 1, 'tahap_review' => 'tanggapan', 'putaran_tl' => null, 'hasil_review' => 'REVIU-TANGGAPAN', 'status' => 'dalam_proses_tindak_lanjut_direksi'],
        (object) ['id' => 2, 'tahap_review' => 'tindak_lanjut', 'putaran_tl' => 1, 'hasil_review' => 'REVIU-PERTAMA', 'status' => 'dalam_proses_reviu_dewas'],
        (object) ['id' => 3, 'tahap_review' => 'tindak_lanjut', 'putaran_tl' => 2, 'hasil_review' => 'REVIU-KEDUA', 'status' => 'selesai_tuntas'],
    ]));
    $html = view('layouts.snp.report.pdf', compact('records'))->render();
    expect($html)->toContain('TANGGAPAN-UJI', 'TL-PERTAMA', 'TL-KEDUA', 'REVIU-TANGGAPAN', 'REVIU-PERTAMA', 'REVIU-KEDUA', '20-Nov-2026')
        ->and(substr_count($html, 'Selesai Tuntas'))->toBe(1)
        ->and(substr_count($html, 'TL-PERTAMA'))->toBe(1)
        ->and(substr_count($html, 'TL-KEDUA'))->toBe(1);
});

test('regular PDF does not create empty continuation rows for short butirs without follow ups', function () {
    $records = snpLayoutRecords(['TES', 'INI ISI BUTIR SNP']);
    $html = view('layouts.snp.report.pdf', compact('records'))->render();
    $document = new DOMDocument;
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);
    expect($xpath->query('//tbody/tr'))->toHaveCount(2);
    foreach (['SNP.01' => 'TES', 'SNP.02' => 'INI ISI BUTIR SNP'] as $id => $text) {
        $cells = $xpath->query('//td[@data-snp-butir-content="'.$id.'"]');
        expect($cells)->toHaveCount(1)->and($cells->item(0)->textContent)->toContain($text);
        expect($cells->item(0)->parentNode->getAttribute('class'))->toContain('butir-start', 'butir-end');
    }
    expect($xpath->query('//tbody//span[@class="continuation"]'))->toHaveCount(0);
    if (getenv('SNP_EXPORT_ARTIFACTS')) {
        file_put_contents(storage_path('app/private/snp-layout-short.html'), $html);
        $pdf = new Dompdf;
        $pdf->setPaper('legal', 'landscape');
        $pdf->loadHtml($html);
        $pdf->render();
        file_put_contents(storage_path('app/private/snp-layout-short.pdf'), $pdf->output());
    }
});
