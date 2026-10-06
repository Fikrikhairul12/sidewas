<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

uses(TestCase::class);

test('shared butir previews retain complete plain text and escape markup', function () {
    $content = "Keputusan O'Brien & peserta.\n\n".str_repeat('Isi lengkap ', 150).'<img src=x onerror=alert(1)>';
    $html = Blade::render('<x-butir-preview :content="$content" butir-id="RAGAB.01" label="Keputusan RAGAB" :expanded="true" />', compact('content'));
    $document = new DOMDocument;
    $previousErrors = libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="UTF-8">'.$html);
    libxml_clear_errors();
    libxml_use_internal_errors($previousErrors);
    $xpath = new DOMXPath($document);
    expect($xpath->query('//p[@class="snp-butir-preview__text"]')->item(0)->textContent)->toBe($content)
        ->and($xpath->query('//img')->length)->toBe(0)
        ->and($xpath->query('//button')->item(0)->getAttribute('type'))->toBe('button')
        ->and($html)->toContain('Keputusan RAGAB', 'Perbesar bacaan', "format: 'plain'");
});

test('all seventeen module pages provide full reading without disturbing drafts navigation or report selection', function () {
    $content = "Keputusan O'Brien & \"peserta\".\n\n".str_repeat("Paragraf lengkap untuk dibaca, tanpa pemotongan.\n\n", 24).'<img src=x onerror="window.injected=true">';
    $fixtures = [];
    $pagesChecked = 0;
    foreach (['ragab' => 'keputusan_ragab', 'rawas' => 'keputusan_rawas', 'djsn' => 'butir_djsn', 'eksternal' => 'keputusan_eksternal'] as $module => $field) {
        $idField = 'id_butir_'.$module;
        $items = [
            ['id' => 1, $idField => strtoupper($module).'.01', $field => $content],
            ['id' => 2, $idField => strtoupper($module).'.02', $field => "Isi kedua.\nBaris kedua."],
        ];
        $butir = (object) $items[0];
        $firstButir = $butir;
        $review = (object) [$idField => $items[0][$idField], 'butir' => $butir];
        $html = Blade::render(<<<'BLADE'
            <div x-data="{ open: true, selectedButir: @js($items[0]), selectedReview: @js($items[0]), selectedDetailButir: @js($items[0]), detailButir: @js($items[0]), detailRecord: { butirs: @js($items) } }">
                <div id="parentForm" x-show="open" @click.outside="open = false" @keydown.escape.window="open = false">
                    <form id="workflowForm" action="/existing-workflow" method="POST">
                        <textarea id="draft" name="tindak_lanjut">Draf belum disimpan.</textarea>
                        <select id="pic" name="unit_kerja_id"><option value="1">Unit 1</option><option value="2" selected>Unit 2</option></select>
                        <input type="hidden" name="butir_id" value="1">
            BLADE, compact('items'));
        $sections = [];
        foreach ($module === 'djsn' ? ['perekaman', 'tindak-lanjut', 'reviu', 'tanggapan'] : ['perekaman', 'tindak-lanjut', 'reviu'] as $page) {
            $source = file_get_contents(resource_path('views/layouts/'.$module.'/'.$page.'.blade.php'));
            preg_match_all('/<x-butir-preview\b.*?\/>/s', $source, $matches);
            expect($matches[0])->not->toBeEmpty();
            foreach ($matches[0] as $index => $tag) {
                $section = $page.'-'.$index;
                $sections[] = $section;
                $html .= '<section id="'.$section.'">'.Blade::render($tag, compact('butir', 'firstButir', 'review')).'</section>';
            }
            $pagesChecked++;
        }
        $html .= '</form></div></div>';
        $reportSource = file_get_contents(resource_path('views/layouts/'.$module.'/report/index.blade.php'));
        preg_match('/\$butirsForReport\s*=\s*\$record->butir\w+.*?->values\(\);/s', $reportSource, $mapping);
        expect($mapping)->not->toBeEmpty();
        $relation = 'butir'.ucfirst($module);
        $record = (object) [$relation => collect($items)->map(fn ($item) => (object) ($item + ['tanggal_'.$module => null, 'agenda_'.$module => 'Agenda contoh']))];
        $reportItems = json_decode(Blade::render('@php '.$mapping[0].' @endphp @json($butirsForReport)', compact('record')), true, flags: JSON_THROW_ON_ERROR);
        expect($reportItems[0][$field])->toBe($content);
        $html .= Blade::render(<<<'BLADE'
            <input type="checkbox" name="record_ids[]" value="1" checked data-record-label="Surat contoh" data-butirs='@json($reportItems)'>
            <button type="button" id="openCustomReportModalBtn">Report custom</button>
            BLADE, compact('reportItems'));
        $reportModal = Str::after($reportSource, '<div id="customReportModal"');
        $reportModal = str_contains($reportModal, '<div id="reportFormatModal"')
            ? Str::before($reportModal, '<div id="reportFormatModal"')
            : Str::beforeLast(Str::before($reportModal, '</x-app-layout>'), '</div>');
        $html .= Blade::render('<div id="customReportModal"'.$reportModal, ['reportFields' => ['id_butir' => 'ID Butir', 'isi_butir' => 'Isi Butir']]);
        $html .= view('components.snp-butir-reader')->render();
        $fixtures[$module] = ['html' => $html, 'sections' => $sections, 'id' => $items[0][$idField]];
        $pagesChecked++;
    }
    expect($pagesChecked)->toBe(17);
    $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true, flags: JSON_THROW_ON_ERROR);
    $process = new Process(['node', base_path('tests/Unit/MultiModuleButirPreview.browser.mjs')], base_path());
    $process->setInput(json_encode(['fixtures' => $fixtures, 'content' => $content, 'css' => $manifest['resources/css/app.css']['file'], 'js' => $manifest['resources/js/app.js']['file']], JSON_THROW_ON_ERROR));
    $process->setTimeout(90);
    $process->run();
    expect($process->isSuccessful())->toBeTrue($process->getOutput().$process->getErrorOutput());
});
