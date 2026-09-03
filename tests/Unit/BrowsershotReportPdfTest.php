<?php

test('non SNP report pdf controllers still use browsershot', function () {
    $rootPath = dirname(__DIR__, 2);

    $controllers = [
        'ragab' => file_get_contents($rootPath.'/app/Http/Controllers/Ragab/ReportRagabController.php'),
        'rawas' => file_get_contents($rootPath.'/app/Http/Controllers/Rawas/ReportRawasController.php'),
        'djsn' => file_get_contents($rootPath.'/app/Http/Controllers/Djsn/ReportDjsnController.php'),
    ];

    foreach ($controllers as $source) {
        expect($source)
            ->toContain('use Spatie\\Browsershot\\Browsershot;')
            ->toContain('private function streamBrowsershotPdf(string $view, array $data, string $filename): Response')
            ->toContain('Browsershot::html($html)')
            ->toContain("->format('Legal')")
            ->toContain('->landscape()')
            ->toContain('->showBackground()')
            ->toContain('Content-Disposition')
            ->not->toContain('Pdf::loadView')
            ->not->toContain('Barryvdh\\DomPDF\\Facade\\Pdf');
    }

    expect($controllers['ragab'])
        ->toContain("streamBrowsershotPdf('layouts.ragab.report.pdf'")
        ->toContain("streamBrowsershotPdf('layouts.ragab.report.pdf-custom'");

    expect($controllers['rawas'])
        ->toContain("streamBrowsershotPdf('layouts.rawas.report.pdf'")
        ->toContain("streamBrowsershotPdf('layouts.rawas.report.pdf-custom'");

    expect($controllers['djsn'])
        ->toContain("streamBrowsershotPdf('layouts.djsn.report.pdf'")
        ->toContain("streamBrowsershotPdf('layouts.djsn.report.pdf-custom'");
});
