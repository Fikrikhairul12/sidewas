<?php

use App\Services\SnpButirContent;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ViewErrorBag;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\Process\Process;
use Tests\TestCase;

uses(TestCase::class);

test('module previews reuse the current tab and restore filters and custom choices while PDF and Excel downloads retain selections', function (string $module) {
    URL::forceRootUrl('http://localhost');
    $this->app->instance('request', Request::create('http://localhost/'.$module.'/report?keyword=SURAT&status=dalam_proses&page=2'));
    $recordClass = 'App\\Models\\'.ucfirst($module).'Record';
    $butirClass = 'App\\Models\\'.ucfirst($module).'Butir';
    $records = collect([1, 2])->map(function (int $id) use ($module, $recordClass, $butirClass): object {
        $butirs = collect($id === 1 ? [11, 12] : [21])->map(function (int $id) use ($module, $butirClass): object {
            $butir = (new $butirClass)->forceFill([
                'id' => $id, 'id_butir_'.$module => strtoupper($module).'.'.$id,
                SnpButirContent::contentField($module) => 'Isi lengkap butir '.$id,
                'status' => 'dalam_proses', 'tanggal_'.$module => '2026-10-07', 'agenda_'.$module => 'Agenda '.$id,
            ]);
            foreach (['butirPics', 'butirDirektorats', 'reviews', 'tindakLanjuts'] as $relation) {
                $butir->setRelation($relation, collect());
            }
            $butir->setRelation('reviewTindakLanjut', null)->setRelation('tanggapan', null);

            return $butir;
        });
        $record = (new $recordClass)->forceFill([
            'id' => $id, 'id_'.$module => strtoupper($module).'-'.$id, 'nomor_surat' => 'SURAT-'.$id,
            'perihal_surat' => 'Uji navigasi laporan', 'tanggal_surat' => '2026-10-07',
            'status' => 'dalam_proses', 'butir_'.$module.'_count' => $butirs->count(),
        ]);
        $record->setRelation('butir'.ucfirst($module), $butirs);

        return $record;
    });
    $controller = app('App\\Http\\Controllers\\'.ucfirst($module).'\\Report'.ucfirst($module).'Controller');
    $fieldLabels = (new ReflectionMethod($controller, 'reportFieldLabels'))->invoke($controller);
    $fields = $module === 'djsn' ? ['id_butir', 'isi_butir'] : ['tgl_agenda', 'keputusan'];
    $renderPage = fn (string $path, array $data): string => Blade::render(str_replace(['<x-app-layout>', '</x-app-layout>'], '', file_get_contents(resource_path('views/'.$path))), $data);
    $fixtures = ['index' => $renderPage('layouts/'.$module.'/report/index.blade.php', [
        'records' => new LengthAwarePaginator($records, 20, 10, 2, ['path' => route($module.'.report.index')]),
        'direktorats' => collect(), 'unitKerjas' => collect(), 'komites' => collect(), 'errors' => new ViewErrorBag,
        'statusOptions' => ['draft' => 'Draft', 'dalam_proses' => 'Dalam Proses', 'tuntas' => 'Tuntas'],
        'reportFields' => $fieldLabels,
    ])];
    $data = ['records' => $records->take(1), 'selectedFields' => $fields, 'fieldLabels' => $fieldLabels];
    $parameters = ['record_ids' => [1]];
    $customParameters = $parameters + ['butir_ids' => [12], 'fields' => $fields];
    foreach (['regular' => ['pdf', $parameters], 'custom' => ['pdf-custom', $customParameters]] as $name => [$template, $selection]) {
        $filename = 'report-'.$module.($name === 'custom' ? '-custom' : '').'.pdf';
        $preview = (new ReflectionMethod($controller, 'previewPdf'))->invoke($controller, 'layouts.'.$module.'.report.'.$template, $data, $selection, 'Pratinjau Report '.strtoupper($module), $filename);
        expect($preview->getData()['reportModule'])->toBe($module)
            ->and($preview->getData()['backRoute'])->toBe(route($module.'.report.index'));
        $fixtures[$name] = $renderPage('layouts/snp/report/preview.blade.php', $preview->getData());
    }
    $fixtures['other'] = $renderPage('layouts/snp/report/preview.blade.php', [
        'title' => 'Pratinjau Report SNP', 'filename' => 'report-snp.pdf', 'reportHtml' => '<p>Laporan SNP</p>',
        'downloadRoute' => route('snp.report.download'), 'downloadParameters' => $parameters,
        'backRoute' => route('snp.report.index'), 'reportModule' => 'snp',
    ]);
    expect($fixtures['index'])->not->toContain('target="_blank"')
        ->toContain('Pratinjau PDF', 'Pratinjau PDF Custom')
        ->and(substr_count($fixtures['index'], 'data-report-pdf-preview'))->toBe(2);
    $pdf = (new ReflectionMethod($controller, 'downloadPdf'))->invoke($controller, 'layouts.'.$module.'.report.pdf', $data, 'report-'.$module.'.pdf')->getContent();
    $exportClass = 'App\\Exports\\'.ucfirst($module).'ReportExport';
    $excel = Excel::raw(new $exportClass($data['records'], $fields, $fieldLabels), Maatwebsite\Excel\Excel::XLSX);
    expect($pdf)->toStartWith('%PDF-')->and(bin2hex(substr($excel, 0, 4)))->toBe('504b0304');
    $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true, flags: JSON_THROW_ON_ERROR);
    $process = new Process(['node', base_path('tests/Unit/ReportNavigation.browser.mjs')], base_path());
    $process->setInput(json_encode([
        'module' => $module, 'otherModule' => 'snp', 'fields' => $fields, 'fixtures' => $fixtures,
        'pdf' => base64_encode($pdf), 'excel' => base64_encode($excel),
        'css' => $manifest['resources/css/app.css']['file'], 'js' => $manifest['resources/js/app.js']['file'],
    ], JSON_THROW_ON_ERROR));
    $process->setTimeout(90);
    $process->run();
    expect($process->isSuccessful())->toBeTrue($process->getOutput().$process->getErrorOutput());
})->with(['RAGAB' => 'ragab', 'RAWAS' => 'rawas', 'DJSN' => 'djsn', 'Eksternal' => 'eksternal']);
