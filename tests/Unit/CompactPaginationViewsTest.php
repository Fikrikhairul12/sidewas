<?php

test('ragab rawas and djsn pages use compact pagination window', function () {
    $rootPath = dirname(__DIR__, 2);

    $views = [
        'ragab perekaman' => [
            file_get_contents($rootPath.'/resources/views/layouts/ragab/perekaman.blade.php'),
            'records',
        ],
        'ragab tindak lanjut' => [
            file_get_contents($rootPath.'/resources/views/layouts/ragab/tindak-lanjut.blade.php'),
            'tindakLanjutRows',
        ],
        'ragab reviu' => [
            file_get_contents($rootPath.'/resources/views/layouts/ragab/reviu.blade.php'),
            'reviews',
        ],
        'ragab report' => [
            file_get_contents($rootPath.'/resources/views/layouts/ragab/report/index.blade.php'),
            'records',
        ],
        'rawas perekaman' => [
            file_get_contents($rootPath.'/resources/views/layouts/rawas/perekaman.blade.php'),
            'records',
        ],
        'rawas tindak lanjut' => [
            file_get_contents($rootPath.'/resources/views/layouts/rawas/tindak-lanjut.blade.php'),
            'tindakLanjutRows',
        ],
        'rawas reviu' => [
            file_get_contents($rootPath.'/resources/views/layouts/rawas/reviu.blade.php'),
            'reviews',
        ],
        'rawas report' => [
            file_get_contents($rootPath.'/resources/views/layouts/rawas/report/index.blade.php'),
            'records',
        ],
        'djsn perekaman' => [
            file_get_contents($rootPath.'/resources/views/layouts/djsn/perekaman.blade.php'),
            'records',
        ],
        'djsn tanggapan' => [
            file_get_contents($rootPath.'/resources/views/layouts/djsn/tanggapan.blade.php'),
            'butirs',
        ],
        'djsn tindak lanjut' => [
            file_get_contents($rootPath.'/resources/views/layouts/djsn/tindak-lanjut.blade.php'),
            'tindakLanjutRows',
        ],
        'djsn reviu' => [
            file_get_contents($rootPath.'/resources/views/layouts/djsn/reviu.blade.php'),
            'reviews',
        ],
        'djsn report' => [
            file_get_contents($rootPath.'/resources/views/layouts/djsn/report/index.blade.php'),
            'records',
        ],
    ];

    foreach ($views as [$source, $paginator]) {
        expect($source)
            ->toContain("@include('layouts.partials.compact-pagination', ['paginator' => \${$paginator}])")
            ->not->toContain("{{ \${$paginator}->links() }}")
            ->not->toContain("getUrlRange(1, \${$paginator}->lastPage())");
    }
});
