<?php

use App\Exports\SnpReportExport;
use App\Http\Controllers\Snp\SnpButirImageController;
use App\Models\SnpButir;
use App\Models\SnpRecord;
use App\Models\User;
use App\Services\SnpButirContent;
use Dompdf\Dompdf;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

uses(TestCase::class);

function snpTestImage(): string
{
    Storage::fake('local');
    $name = '12345678-1234-1234-1234-123456789abc.png';
    $file = UploadedFile::fake()->image('image.png', 600, 400);
    Storage::disk('local')->put('snp-images/1/'.$name, file_get_contents($file->getPathname()));

    return '/snp/perekaman/1/gambar/'.$name;
}

function snpReportRecords(string $content): Collection
{
    $butir = (new SnpButir)->forceFill(['id' => 1, 'id_butir_snp' => 'SNP.01', 'butir_snp' => $content, 'status' => 'terbit']);
    foreach (['butirPics', 'kompilasis', 'kompilasiTindakLanjuts', 'tanggapan', 'tindakLanjuts', 'reviews'] as $relation) {
        $butir->setRelation($relation, collect());
    }
    $butir->setRelation('kompilasiTanggapan', null)->setRelation('kompilasiTindakLanjut', null);
    $record = (new SnpRecord)->forceFill(['id' => 1, 'id_snp' => 'SNP', 'nomor_surat' => 'Surat uji', 'perihal_surat' => 'Laporan isi lengkap', 'status' => 'terbit']);
    $record->setRelation('butirSnp', collect([$butir]));

    return collect([$record]);
}

test('legacy content stays literal and rich formatting is canonical and safe', function () {
    $service = new SnpButirContent;
    $legacy = "Baris satu\n<script>alert('teks lama')</script> & asli";
    expect($service->normalize($legacy, 1))->toBe($legacy)
        ->and($service->plain($legacy))->toBe($legacy)
        ->and($service->html($legacy))->not->toContain('<script>');
    $image = snpTestImage();
    $value = SnpButirContent::PREFIX.'<p onclick="alert(1)" style="text-align:center;color:red"><strong>Tebal</strong><em>Miring</em><u>Garis</u><span style="font-size:24px;position:fixed">Besar</span><script>alert(1)</script><img src="'.$image.'" data-width="50" onerror="alert(1)"></p><ol start="3"><li><p>Poin</p></li></ol><img src="https://evil.test/x">';
    $normalized = $service->normalize($value, 1);
    expect($normalized)->toContain('text-align:center', 'font-size:24px', 'data-width="50"', '<ol start="3">')
        ->not->toContain('onclick', 'onerror', 'script', 'position', 'evil.test', 'color:red')
        ->and($service->normalize($normalized, 1))->toBe($normalized)
        ->and($service->images($normalized))->toHaveCount(1);
});

test('rich content rejects empty oversized missing and cross-record images', function () {
    $service = new SnpButirContent;
    $image = snpTestImage();
    foreach ([SnpButirContent::PREFIX.'<p><br></p>', str_repeat('x', 500001), SnpButirContent::PREFIX.'<img src="'.str_replace('/1/', '/2/', $image).'">'] as $invalid) {
        expect(fn () => $service->normalize($invalid, 1))->toThrow(ValidationException::class);
    }
    expect(fn () => $service->normalize(SnpButirContent::PREFIX.str_repeat('<img src="'.$image.'">', 21), 1))->toThrow(ValidationException::class);
    Storage::disk('local')->delete(SnpButirContent::imageReference($image)['path']);
    expect(fn () => $service->normalize(SnpButirContent::PREFIX.'<img src="'.$image.'">', 1))->toThrow(ValidationException::class);
});

test('image uploads enforce authorization file type size and private storage', function () {
    Storage::fake('local');
    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('canCreateSnpPerekaman')->andReturn(true);
    $user->shouldReceive('canAccessSnpPerekaman')->andReturn(true);
    $record = (new SnpRecord)->forceFill(['id' => 1]);
    $controller = new SnpButirImageController;
    $request = Request::create('/upload', 'POST', [], [], ['image' => UploadedFile::fake()->image('valid.png')]);
    $request->setUserResolver(fn () => $user);
    $result = $controller->store($request, $record);
    expect($result->getStatusCode())->toBe(201);
    $reference = SnpButirContent::imageReference($result->getData(true)['url']);
    Storage::disk('local')->assertExists($reference['path']);
    $response = $controller->show($request, $record, basename($reference['path']));
    expect($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('Cache-Control'))->toContain('private');
    foreach ([UploadedFile::fake()->create('bad.svg', 1, 'image/svg+xml'), UploadedFile::fake()->image('large.jpg')->size(2049)] as $invalid) {
        $request = Request::create('/upload', 'POST', [], [], ['image' => $invalid]);
        $request->setUserResolver(fn () => $user);
        expect(fn () => $controller->store($request, $record))->toThrow(ValidationException::class);
    }
    $outsider = Mockery::mock(User::class)->makePartial();
    $outsider->shouldReceive('canCreateSnpPerekaman', 'canAccessSnpPerekaman')->andReturn(false);
    $request->setUserResolver(fn () => $outsider);
    expect(fn () => $controller->store($request, $record))->toThrow(HttpException::class)
        ->and(fn () => $controller->show($request, $record, basename($reference['path'])))->toThrow(HttpException::class);
});

test('Excel exports complete plain text and images alongside the related butir', function () {
    $image = snpTestImage();
    $value = SnpButirContent::PREFIX.'<p>=SUM(A1:A2) '.str_repeat('Konten lengkap berulang. ', 2000).'AKHIR-ISI</p><p><img src="'.$image.'" data-width="50"></p>';
    $records = snpReportRecords($value);
    $second = clone $records[0]->butirSnp[0];
    $second->id_butir_snp = 'SNP.02';
    $second->butir_snp = SnpButirContent::PREFIX.'<p>Butir kedua</p><img src="'.$image.'">';
    $records[0]->butirSnp->push($second);
    $bytes = Excel::raw(new SnpReportExport($records, ['id_butir', 'isi_butir'], ['id_butir' => 'ID', 'isi_butir' => 'ISI']), Maatwebsite\Excel\Excel::XLSX);
    $path = tempnam(sys_get_temp_dir(), 'snp-excel-');
    file_put_contents($path, $bytes);
    try {
        $sheet = IOFactory::load($path)->getActiveSheet();
        $text = implode("\n", array_map(fn (array $row): string => $row[1] ?? '', $sheet->toArray()));
        expect($text)->toContain('AKHIR-ISI', 'Butir kedua')->not->toContain('<p>', 'snp-rich', '<img')
            ->and(substr_count(preg_replace('/\s+/', ' ', $text), 'Konten lengkap'))->toBe(2000)
            ->and($sheet->getCell('B2')->getDataType())->toBe('s')
            ->and($sheet->getDrawingCollection())->toHaveCount(2);
        foreach ($sheet->getDrawingCollection() as $drawing) {
            $row = $drawing->getCoordinates();
            $idCell = $sheet->getCell(str_replace('B', 'A', $row));
            if ($mergeRange = $idCell->getMergeRange()) {
                $idCell = $sheet->getCell(explode(':', $mergeRange)[0]);
            }
            expect($idCell->getValue())->toBe(str_contains($drawing->getName(), 'SNP.02') ? 'SNP.02' : 'SNP.01');
        }
        if (getenv('SNP_EXPORT_ARTIFACTS')) {
            file_put_contents(storage_path('app/private/snp-rich-report.xlsx'), $bytes);
        }
    } finally {
        unlink($path);
    }
});

test('regular and custom PDF render long rich content across pages with embedded images', function () {
    $image = snpTestImage();
    $value = SnpButirContent::PREFIX.'<p style="text-align:center"><strong>JUDUL BERFORMAT</strong></p><p>'.str_repeat('Paragraf lengkap tidak terpotong. ', 650).'</p><p><img src="'.$image.'" data-width="50"></p><p>AKHIR-KONTEN-SNP</p>';
    $records = snpReportRecords($value);
    foreach (['pdf', 'pdf-custom'] as $template) {
        $html = view('layouts.snp.report.'.$template, ['records' => $records, 'selectedFields' => ['id_butir', 'isi_butir'], 'fieldLabels' => ['id_butir' => 'ID', 'isi_butir' => 'ISI']])->render();
        expect($html)->toContain('data:image/png;base64', 'AKHIR-KONTEN-SNP')->not->toContain('&lt;p&gt;');
        expect($html)->not->toContain('lihat lampiran butir', 'snp-content-appendix');
        $document = new DOMDocument;
        @$document->loadHTML($html);
        $xpath = new DOMXPath($document);
        expect($xpath->query('//img[not(ancestor::td[@data-snp-butir-content])]'))->toHaveCount(0);
        $completeText = '';
        foreach ($xpath->query('//td[@data-snp-butir-content]/div[@class="snp-content-line"]') as $line) {
            $completeText .= $line->textContent."\n";
        }
        expect(substr_count(preg_replace('/\s+/u', ' ', $completeText), 'Paragraf lengkap tidak terpotong.'))->toBe(650);
        $dompdf = new Dompdf;
        $dompdf->setPaper('legal', 'landscape');
        $dompdf->loadHtml($html);
        $dompdf->render();
        expect($dompdf->getCanvas()->get_page_count())->toBeGreaterThan(2);
        if (getenv('SNP_EXPORT_ARTIFACTS')) {
            file_put_contents(storage_path('app/private/snp-rich-'.$template.'.pdf'), $dompdf->output());
        }
    }
    $withoutContent = view('layouts.snp.report.pdf-custom', ['records' => $records, 'selectedFields' => ['id_butir'], 'fieldLabels' => ['id_butir' => 'ID']])->render();
    expect($withoutContent)->not->toContain('AKHIR-KONTEN-SNP', 'data:image/png;base64');
});

test('rich editor preserves drafts formatting images and full-screen editing in the browser', function () {
    $image = snpTestImage();
    $initial = SnpButirContent::PREFIX.'<p style="text-align:center"><strong>Isi awal</strong></p><p><img src="'.$image.'" data-width="50"></p>';
    $html = Blade::render(<<<'BLADE'
        <form id="editorForm" x-data="{ selected: 0, drafts: [@js($initial), 'Teks lama <literal>'], record: 1 }" @submit.prevent>
            <button type="button" id="switch" @click="selected = selected === 0 ? 1 : 0">Ganti butir</button>
            <div @snp-editor-change.stop="drafts[selected] = $event.detail">
                <x-snp-butir-editor content-expression="drafts[selected]" record-expression="record" key-expression="selected" />
            </div>
            <button type="submit" id="save">Simpan</button>
            <x-snp-butir-preview id="richPreview" content-expression="drafts[selected]" :expanded="true" />
        </form>
        <x-snp-butir-reader />
        BLADE, compact('initial'));
    $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
    $process = new Process(['node', base_path('tests/Unit/SnpRichEditor.browser.mjs')], base_path());
    $process->setInput(json_encode(['html' => $html, 'uploadPath' => Storage::disk('local')->path(SnpButirContent::imageReference($image)['path']), 'css' => $manifest['resources/css/app.css']['file'], 'js' => $manifest['resources/js/app.js']['file']]));
    $process->setTimeout(90);
    $process->run();
    expect($process->isSuccessful())->toBeTrue($process->getOutput().$process->getErrorOutput());
});
