<?php

use App\Exports\SnpReportExport;
use App\Http\Controllers\Snp\ReportSnpController;
use App\Models\SnpButir;
use App\Models\SnpRecord;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ViewErrorBag;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\Process\Process;
use Tests\TestCase;

uses(TestCase::class);

test('SNP regular and custom previews reuse the current tab and restore report selections while downloads retain their parameters', function () {
    URL::forceRootUrl('http://localhost');
    $this->app->instance('request', Request::create('http://localhost/snp/report?keyword=SURAT&status=dalam_proses&page=2'));
    $records = collect([1, 2])->map(function (int $id): SnpRecord {
        $butirs = collect($id === 1 ? [11, 12] : [21])->map(function (int $id): SnpButir {
            $butir = (new SnpButir)->forceFill(['id' => $id, 'id_butir_snp' => 'SNP.'.$id, 'butir_snp' => 'Isi lengkap butir '.$id]);
            foreach (['butirPics', 'kompilasis', 'kompilasiTindakLanjuts', 'tanggapan', 'tindakLanjuts', 'reviews'] as $relation) {
                $butir->setRelation($relation, collect());
            }
            $butir->setRelation('kompilasiTanggapan', null)->setRelation('kompilasiTindakLanjut', null);
            $butir->setRelation('butirPics', collect([7, 8])->map(fn (int $unit): object => (object) ['jenis_pic' => $unit === 7 ? 'utama' : 'pendukung', 'unitKerja' => (object) ['id' => $unit, 'kode_unit' => 'UNIT-'.$unit, 'nama_unit' => 'Unit '.$unit]]));

            return $butir;
        });
        $record = (new SnpRecord)->forceFill(['id' => $id, 'id_snp' => 'SNP-'.$id, 'nomor_surat' => 'SURAT-'.$id, 'perihal_surat' => 'Uji navigasi laporan', 'tanggal_surat' => '2026-10-07', 'status' => 'dalam_proses', 'butir_snp_count' => $butirs->count()]);
        $record->setRelation('butirSnp', $butirs);

        return $record;
    });
    $renderPage = fn (string $path, array $data): string => Blade::render(str_replace(['<x-app-layout>', '</x-app-layout>'], '', file_get_contents(resource_path('views/'.$path))), $data);
    $index = $renderPage('layouts/snp/report/index.blade.php', [
        'records' => new LengthAwarePaginator($records, 20, 10, 2, ['path' => route('snp.report.index')]),
        'direktorats' => collect(), 'unitKerjas' => collect(), 'komites' => collect(), 'errors' => new ViewErrorBag,
    ]);
    $controller = app(ReportSnpController::class);
    $fields = ['id_butir', 'isi_butir'];
    $labels = array_combine($fields, $fields);
    $data = ['records' => $records->take(1), 'selectedFields' => $fields, 'fieldLabels' => $labels];
    $parameters = ['record_ids' => [1]];
    $customParameters = $parameters + ['butir_ids' => [12], 'fields' => $fields, 'tanggapan_unit_kerja_ids' => [7], 'tindak_lanjut_unit_kerja_ids' => [8]];
    $fixtures = ['index' => $index];
    foreach (['regular' => ['pdf', $parameters, 'snp.report.download'], 'custom' => ['pdf-custom', $customParameters, 'snp.report.download-custom']] as $name => [$template, $selection, $downloadRoute]) {
        $preview = (new ReflectionMethod($controller, 'previewPdf'))->invoke($controller, 'layouts.snp.report.'.$template, $data, route($downloadRoute), $selection, 'Pratinjau Report SNP', 'report-snp.pdf');
        expect($preview->getData()['snpReportIndexUrl'])->toBe(route('snp.report.index'));
        $fixtures[$name] = $renderPage('layouts/snp/report/preview.blade.php', $preview->getData());
    }
    $otherPreview = [
        'title' => 'Pratinjau Report RAGAB', 'filename' => 'report-ragab.pdf', 'reportHtml' => '<p>Laporan RAGAB</p>',
        'downloadRoute' => route('ragab.report.cetak'), 'downloadParameters' => $parameters, 'backRoute' => route('ragab.report.index'),
    ];
    $fixtures['other'] = $renderPage('layouts/snp/report/preview.blade.php', $otherPreview);
    expect($fixtures['other'])->not->toContain('data-snp-report-return')
        ->and($index)->not->toContain('target="_blank"')
        ->and(substr_count($index, 'data-snp-pdf-preview'))->toBe(2);
    $pdf = (new ReflectionMethod($controller, 'downloadPdf'))->invoke($controller, 'layouts.snp.report.pdf', $data, 'report-snp.pdf')->getContent();
    $excel = Excel::raw(new SnpReportExport($data['records'], $fields, $labels), Maatwebsite\Excel\Excel::XLSX);
    $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true, flags: JSON_THROW_ON_ERROR);
    $process = new Process(['node', base_path('tests/Unit/SnpReportNavigation.browser.mjs')], base_path());
    $process->setInput(json_encode([
        'fixtures' => $fixtures, 'pdf' => base64_encode($pdf), 'excel' => base64_encode($excel),
        'css' => $manifest['resources/css/app.css']['file'], 'js' => $manifest['resources/js/app.js']['file'],
    ], JSON_THROW_ON_ERROR));
    $process->setTimeout(90);
    $process->run();
    expect($process->isSuccessful())->toBeTrue($process->getOutput().$process->getErrorOutput());
});
