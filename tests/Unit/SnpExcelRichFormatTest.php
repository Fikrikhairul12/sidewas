<?php

use App\Exports\SnpReportExport;
use App\Models\SnpButir;
use App\Models\SnpRecord;
use App\Services\SnpButirContent;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\RichText\Run;
use PhpOffice\PhpSpreadsheet\Shared\Drawing as DrawingDimensions;
use PhpOffice\PhpSpreadsheet\Style\Font;
use Tests\TestCase;

uses(TestCase::class);

test('SNP Excel retains paragraph alignment inline fonts explicit breaks lists and aligned images', function (array $fields) {
    Storage::fake('local');
    $image = imagecreatetruecolor(200, 80);
    imagefill($image, 0, 0, imagecolorallocate($image, 219, 234, 254));
    imagestring($image, 4, 20, 20, 'Gambar SNP', imagecolorallocate($image, 30, 64, 175));
    ob_start();
    imagepng($image);
    $bytes = ob_get_clean();
    imagedestroy($image);
    $name = '12345678-1234-1234-1234-123456789abc.png';
    Storage::disk('local')->put('snp-images/1/'.$name, $bytes);
    $url = '/snp/perekaman/1/gambar/'.$name;
    $paragraph = str_repeat('Isi lengkap dengan format tetap terbaca. ', 250);
    $value = SnpButirContent::PREFIX.'<p>Tes Kiri</p><p style="text-align:right">Tes Kanan</p><p style="text-align:center">Tes Tengah</p><p style="text-align:justify">'.$paragraph.'</p><p><em>Miring</em> <strong>Tebal</strong> <u>Garis bawah</u> Normal <strong><em><u>Gabungan</u></em></strong></p><p><span style="font-size:24px">Huruf besar</span></p><p><span style="font-size:18px">18px</span></p><p>Baris satu<br>Baris dua</p><ol start="3"><li><p>Nomor tiga</p></li><li><p><strong>Nomor empat</strong></p></li></ol><ul><li><p>Bullet</p></li></ul><p style="text-align:left"><img src="'.$url.'" data-width="100"></p><p style="text-align:center"><img src="'.$url.'" data-width="100"></p><p style="text-align:right"><img src="'.$url.'" data-width="100"></p><p>=BUKAN-FORMULA</p>';
    $butir = (new SnpButir)->forceFill(['id' => 1, 'id_butir_snp' => 'SNP.01', 'butir_snp' => $value]);
    foreach (['butirPics', 'kompilasis', 'kompilasiTindakLanjuts', 'tanggapan', 'tindakLanjuts', 'reviews'] as $relation) {
        $butir->setRelation($relation, collect());
    }
    $butir->setRelation('kompilasiTanggapan', null)->setRelation('kompilasiTindakLanjut', null);
    $record = (new SnpRecord)->forceFill(['id' => 1, 'nomor_surat' => 'SURAT-UJI']);
    $record->setRelation('butirSnp', collect([$butir]));
    $export = Excel::raw(new SnpReportExport(collect([$record]), $fields, array_combine($fields, $fields)), Maatwebsite\Excel\Excel::XLSX);
    $path = tempnam(sys_get_temp_dir(), 'snp-format-');
    file_put_contents($path, $export);
    try {
        $sheet = IOFactory::load($path)->getActiveSheet();
        $column = Coordinate::stringFromColumnIndex(array_search('isi_butir', $fields, true) + 1);
        $cells = [];
        for ($row = 2; $row <= $sheet->getHighestRow(); $row++) {
            $cells[] = $column.$row;
            expect($sheet->getRowDimension($row)->getRowHeight())->toBeLessThanOrEqual(409);
        }
        $findCell = fn (string $text) => collect($cells)->first(fn ($cell) => str_contains((string) $sheet->getCell($cell)->getValue(), $text));
        foreach (['Tes Kiri' => 'left', 'Tes Kanan' => 'right', 'Tes Tengah' => 'center'] as $text => $alignment) {
            $cell = $findCell($text);
            expect((string) $sheet->getCell($cell)->getValue())->toBe($text)
                ->and($sheet->getStyle($cell)->getAlignment()->getHorizontal())->toBe($alignment);
        }
        $paragraphCells = collect($cells)->filter(fn ($cell) => str_contains((string) $sheet->getCell($cell)->getValue(), 'Isi lengkap'));
        expect($paragraphCells->count())->toBeGreaterThan(1)
            ->and($paragraphCells->map(fn ($cell) => (string) $sheet->getCell($cell)->getValue())->implode(''))->toBe($paragraph);
        foreach ($paragraphCells as $cell) {
            expect($sheet->getStyle($cell)->getAlignment()->getHorizontal())->toBe('justify');
        }
        $styled = $sheet->getCell($findCell('Miring'))->getValue();
        expect($styled)->toBeInstanceOf(RichText::class);
        $runs = collect($styled->getRichTextElements());
        foreach (['Miring', 'Tebal', 'Garis bawah', 'Gabungan', 'Normal'] as $text) {
            $run = $runs->first(fn ($run) => str_contains($run->getText(), $text));
            expect($run)->toBeInstanceOf(Run::class)
                ->and($run->getFont()->getBold())->toBe(in_array($text, ['Tebal', 'Gabungan'], true))
                ->and($run->getFont()->getItalic())->toBe(in_array($text, ['Miring', 'Gabungan'], true))
                ->and($run->getFont()->getUnderline())->toBe(in_array($text, ['Garis bawah', 'Gabungan'], true) ? Font::UNDERLINE_SINGLE : Font::UNDERLINE_NONE)
                ->and($run->getFont()->getSize())->toBe(11.0);
        }
        foreach (['Huruf besar' => 18.0, '18px' => 13.5] as $text => $size) {
            expect($sheet->getCell($findCell($text))->getValue()->getRichTextElements()[0]->getFont()->getSize())->toBe($size);
        }
        expect((string) $sheet->getCell($findCell('Baris satu'))->getValue())->toBe("Baris satu\nBaris dua")
            ->and((string) $sheet->getCell($findCell('Nomor tiga'))->getValue())->toBe('3. Nomor tiga')
            ->and((string) $sheet->getCell($findCell('Nomor empat'))->getValue())->toBe('4. Nomor empat')
            ->and((string) $sheet->getCell($findCell('Bullet'))->getValue())->toBe('• Bullet')
            ->and($sheet->getStyle($findCell('Nomor tiga'))->getAlignment()->getIndent())->toBe(1)
            ->and($sheet->getCell($findCell('=BUKAN-FORMULA'))->getDataType())->toBe('s');
        $drawings = iterator_to_array($sheet->getDrawingCollection());
        usort($drawings, fn ($first, $second) => ((int) substr($first->getCoordinates(), strlen($column))) <=> ((int) substr($second->getCoordinates(), strlen($column))));
        expect($drawings)->toHaveCount(3);
        $columnWidth = DrawingDimensions::cellDimensionToPixels(90, $sheet->getParent()->getDefaultStyle()->getFont());
        foreach ($drawings as $index => $drawing) {
            expect($drawing->getCoordinates())->toStartWith($column)
                ->and($drawing->getOffsetX())->toBe([8, (int) round(($columnWidth - 200) / 2), $columnWidth - 208][$index])
                ->and($drawing->getOffsetX() + $drawing->getWidth())->toBeLessThanOrEqual($columnWidth - 8);
        }
        expect($butir->butir_snp)->toBe($value);
        if (getenv('SNP_EXPORT_ARTIFACTS')) {
            file_put_contents(storage_path('app/private/snp-format-'.count($fields).'.xlsx'), $export);
        }
    } finally {
        unlink($path);
    }
})->with([
    [['surat', 'id_butir', 'isi_butir', 'status']],
    [['isi_butir']],
]);
