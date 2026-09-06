<?php

test('all non snp reports use preview then download while retaining their templates', function () {
    $rootPath = dirname(__DIR__, 2);
    $modules = [
        'Ragab' => 'ragab',
        'Rawas' => 'rawas',
        'Djsn' => 'djsn',
        'Eksternal' => 'eksternal',
    ];

    foreach ($modules as $controllerName => $routePrefix) {
        $controller = file_get_contents($rootPath."/app/Http/Controllers/{$controllerName}/Report{$controllerName}Controller.php");

        expect($controller)
            ->toContain("view('layouts.snp.report.preview'")
            ->toContain("route('{$routePrefix}.report.index')")
            ->toContain("if (\$request->boolean('_download'))")
            ->toContain("'Content-Disposition' => 'attachment; filename=\"'.\$filename.'\"'")
            ->toContain('Browsershot::html($html)');
    }
});

test('shared preview preserves parameters and submits an explicit download command', function () {
    $preview = file_get_contents(dirname(__DIR__, 2).'/resources/views/layouts/snp/report/preview.blade.php');

    expect($preview)
        ->toContain('name="_download" value="1"')
        ->toContain("\$backRoute ?? route('snp.report.index')")
        ->toContain('action="{{ $downloadRoute }}"')
        ->toContain('@foreach ($downloadParameters as $parameterName => $parameterValues)');
});
