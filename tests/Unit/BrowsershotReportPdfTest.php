<?php

use Symfony\Component\Process\Process;

test('all non SNP report PDF controllers use DomPDF and the same labels as SNP', function () {
    $rootPath = dirname(__DIR__, 2);
    foreach (['Ragab', 'Rawas', 'Djsn', 'Eksternal'] as $name) {
        $module = strtolower($name);
        $source = file_get_contents($rootPath.'/app/Http/Controllers/'.$name.'/Report'.$name.'Controller.php');
        expect($source)
            ->toContain('use Barryvdh\\DomPDF\\Facade\\Pdf;')
            ->toContain('private function downloadPdf(string $view, array $data, string $filename): Response')
            ->toContain('Pdf::loadView($view, $data)')
            ->toContain("->setPaper('legal', 'landscape')")
            ->toContain('SnpReportPdfLabels::class)->callbacks()')
            ->toContain('->download($filename)')
            ->toContain("downloadPdf('layouts.".$module.".report.pdf'")
            ->toContain("downloadPdf('layouts.".$module.".report.pdf-custom'")
            ->not->toContain('Browsershot', 'streamBrowsershotPdf', 'waitForFunction');
    }
});

test('all report downloads also pass under PHP with proc_open disabled', function () {
    $rootPath = dirname(__DIR__, 2);
    $process = new Process([
        PHP_BINARY, '-d', 'disable_functions=proc_open',
        $rootPath.'/vendor/pestphp/pest/bin/pest',
        $rootPath.'/tests/Unit/MultiModuleRichReportTest.php',
        '--filter=report download responses contain actual DomPDF bytes',
        '--compact',
    ], $rootPath, ['REPORT_TEST_PROC_OPEN_DISABLED' => '1']);
    $process->setTimeout(120);
    $process->run();
    expect($process->isSuccessful())->toBeTrue($process->getOutput().$process->getErrorOutput())
        ->and($process->getOutput())->toMatch('/Tests:\s+4 (?:warnings|passed)/');
});
