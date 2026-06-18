<?php

test('snp kompilasi paginates two rows and sorts newest items first', function () {
    $rootPath = dirname(__DIR__, 2);

    $controller = file_get_contents($rootPath.'/app/Http/Controllers/Snp/KompilasiSnpController.php');
    $view = file_get_contents($rootPath.'/resources/views/layouts/snp/kompilasi.blade.php');

    expect($controller)
        ->toContain('$perPage = 2;')
        ->toContain('->sortByDesc(function ($item) {')
        ->toContain('$item->kompilasi?->updated_at?->timestamp')
        ->toContain('$latestUnitInput')
        ->toContain('$item->butir?->created_at?->timestamp');

    expect($view)
        ->toContain('{{ $kompilasiItems->links() }}')
        ->toContain('{{ $kompilasiItems->firstItem() ?? 0 }}')
        ->toContain('{{ $kompilasiItems->lastItem() ?? 0 }}');
});
