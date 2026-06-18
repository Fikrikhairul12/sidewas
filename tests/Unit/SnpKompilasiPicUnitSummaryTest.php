<?php

test('snp kompilasi pic unit column uses summarized content', function () {
    $view = file_get_contents(dirname(__DIR__, 2) . '/resources/views/layouts/snp/kompilasi.blade.php');

    expect($view)
        ->toContain('Tanggapan Singkat')
        ->toContain('Tindak Lanjut Singkat')
        ->toContain('Str::limit($previewText ?? \'-\', 140)')
        ->toContain('Str::limit($previewRow?->deliverables ?? \'-\', 100)')
        ->toContain('$previewRow = $rows->first()')
        ->toContain('+ {{ $rows->count() - 1 }} data lainnya dari unit ini.')
        ->not->toContain('@foreach ($rows as $row)');
});
