<?php

use Dompdf\Dompdf;

test('Dompdf renders a legal landscape PDF without a browser runtime', function () {
    $dompdf = new Dompdf;
    $dompdf->setPaper('legal', 'landscape');
    $dompdf->loadHtml('<html><body><table><tr><th>Report SNP</th></tr></table></body></html>');
    $dompdf->render();

    expect($dompdf->output())
        ->toStartWith('%PDF-')
        ->and($dompdf->getCanvas()->get_width())
        ->toBe(1008.0)
        ->and($dompdf->getCanvas()->get_height())
        ->toBe(612.0);
});

test('SNP PDF flow exposes preview and separate download endpoints', function () {
    $rootPath = dirname(__DIR__, 2);
    $controller = file_get_contents($rootPath.'/app/Http/Controllers/Snp/ReportSnpController.php');
    $routes = file_get_contents($rootPath.'/routes/web.php');
    $regularTemplate = file_get_contents($rootPath.'/resources/views/layouts/snp/report/pdf.blade.php');
    $customTemplate = file_get_contents($rootPath.'/resources/views/layouts/snp/report/pdf-custom.blade.php');

    expect($controller)
        ->toContain('use Barryvdh\\DomPDF\\Facade\\Pdf;')
        ->toContain("view('layouts.snp.report.preview'")
        ->toContain('Pdf::loadView($view, $data)')
        ->toContain("->setPaper('legal', 'landscape')")
        ->not->toContain('Browsershot')
        ->and($routes)
        ->toContain("->name('snp.report.download')")
        ->toContain("->name('snp.report.download-custom')")
        ->and($regularTemplate)
        ->toContain('size: legal landscape;')
        ->toContain('table-layout: fixed;')
        ->toContain('SIDEWAS SNP DEWAS')
        ->toContain('print-footer')
        ->and($customTemplate)
        ->toContain('size: legal landscape;')
        ->toContain('table-layout: fixed;')
        ->toContain('SIDEWAS SNP DEWAS')
        ->toContain('print-footer');
});

test('preview keeps the selected report values for the download request', function () {
    $rootPath = dirname(__DIR__, 2);
    $preview = file_get_contents($rootPath.'/resources/views/layouts/snp/report/preview.blade.php');

    expect($preview)
        ->toContain('Review sebelum download')
        ->toContain('action="{{ $downloadRoute }}"')
        ->toContain('@foreach ($downloadParameters as $parameterName => $parameterValues)')
        ->toContain('name="{{ $parameterName }}[]"')
        ->toContain('value="{{ $parameterValue }}"')
        ->toContain('srcdoc="{{ $reportHtml }}"')
        ->toContain('style="height: 75vh; min-height: 680px;"')
        ->toContain('zoom: 125')
        ->toContain('requestFullscreen()')
        ->toContain('Download {{ $filename }}');
});
