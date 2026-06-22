<?php

test('snp kompilasi paginates two rows and sorts newest items first', function () {
    $rootPath = dirname(__DIR__, 2);

    $controller = file_get_contents($rootPath.'/app/Http/Controllers/Snp/KompilasiSnpController.php');
    $view = file_get_contents($rootPath.'/resources/views/layouts/snp/kompilasi.blade.php');
    $pagination = file_get_contents($rootPath.'/resources/views/layouts/partials/compact-pagination.blade.php');

    expect($controller)
        ->toContain('$perPage = 2;')
        ->toContain('->sortByDesc(function ($item) {')
        ->toContain('$item->kompilasi?->updated_at?->timestamp')
        ->toContain('$latestUnitInput')
        ->toContain('$item->butir?->created_at?->timestamp');

    expect($view)
        ->not->toContain('{{ $kompilasiItems->links() }}')
        ->toContain('{{ $kompilasiItems->firstItem() ?? 0 }}')
        ->toContain('{{ $kompilasiItems->lastItem() ?? 0 }}')
        ->toContain("@include('layouts.partials.compact-pagination', ['paginator' => \$kompilasiItems])")
        ->toContain('$visibleDataUnit = $item->data_unit->take(2);')
        ->toContain('$remainingDataUnitCount')
        ->toContain('tanggapan lainnya')
        ->toContain('tindak lanjut lainnya');

    expect($pagination)
        ->toContain('$pageStart = max(1, min($currentPage - 2, $lastPage - 4));')
        ->toContain('$pageEnd = min($lastPage, $pageStart + 4);')
        ->toContain('...');
});

test('snp pages use compact pagination window', function () {
    $rootPath = dirname(__DIR__, 2);

    $views = [
        'perekaman' => [
            file_get_contents($rootPath.'/resources/views/layouts/snp/perekaman.blade.php'),
            'records',
        ],
        'tanggapan' => [
            file_get_contents($rootPath.'/resources/views/layouts/snp/tanggapan.blade.php'),
            'butirs',
        ],
        'tindak lanjut' => [
            file_get_contents($rootPath.'/resources/views/layouts/snp/tindak-lanjut.blade.php'),
            'tindakLanjutRows',
        ],
        'reviu' => [
            file_get_contents($rootPath.'/resources/views/layouts/snp/reviu.blade.php'),
            'reviews',
        ],
        'report' => [
            file_get_contents($rootPath.'/resources/views/layouts/snp/report/index.blade.php'),
            'records',
        ],
        'kompilasi' => [
            file_get_contents($rootPath.'/resources/views/layouts/snp/kompilasi.blade.php'),
            'kompilasiItems',
        ],
    ];

    foreach ($views as [$source, $paginator]) {
        expect($source)
            ->toContain("@include('layouts.partials.compact-pagination', ['paginator' => \${$paginator}])")
            ->not->toContain("{{ \${$paginator}->links() }}")
            ->not->toContain("getUrlRange(1, \${$paginator}->lastPage())");
    }
});
