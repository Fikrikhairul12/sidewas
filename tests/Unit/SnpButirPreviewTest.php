<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

uses(TestCase::class);

test('SNP previews preserve complete plain text and escape markup', function () {
    $content = "Paragraf pertama & kutipan \"asli\".\n\n".str_repeat('Isi panjang. ', 80).'<img src=x onerror=alert(1)>';
    $html = Blade::render('<x-snp-butir-preview :content="$content" butir-id="SNP.01" />', compact('content'));
    $document = new DOMDocument;
    $previousErrors = libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="UTF-8">'.$html);
    libxml_clear_errors();
    libxml_use_internal_errors($previousErrors);
    $xpath = new DOMXPath($document);

    expect($xpath->query('//p[@class="snp-butir-preview__text"]')->item(0)->textContent)
        ->toBe($content)
        ->and($xpath->query('//img')->length)->toBe(0)
        ->and($xpath->query('//button')->item(0)->getAttribute('type'))->toBe('button');
});

test('SNP expanded previews use the complete dynamic butir instead of a shortened PIC field', function () {
    $html = Blade::render('<x-snp-butir-preview content-expression="detailButir?.butir_snp" id-expression="detailButir?.id_butir_snp" :expanded="true" />');

    expect($html)->toContain('snp-butir-preview--expanded', 'detailButir?.butir_snp', 'Perbesar bacaan');

    foreach (['tanggapan', 'tindak-lanjut'] as $page) {
        expect(file_get_contents(resource_path('views/layouts/snp/'.$page.'.blade.php')))
            ->toContain('content-expression="detailButir?.butir_snp"')
            ->not->toContain('isi_butir_singkat');
    }
});

test('SNP reading room works with Alpine forms reports and pending edits in a browser', function () {
    $content = "Butir asli: O'Brien & \"peserta\".\n\n".str_repeat("Paragraf lengkap untuk dibaca, bukan dipotong.\n\n", 24).'<img src=x onerror="window.injected=true">';
    $items = [
        ['id' => 1, 'id_butir_snp' => 'SNP.01', 'butir_snp' => $content, 'pic_pendukung' => []],
        ['id' => 2, 'id_butir_snp' => 'SNP.02', 'butir_snp' => "Isi kedua.\nBaris kedua.", 'pic_pendukung' => []],
    ];
    $reportView = file_get_contents(resource_path('views/layouts/snp/report/index.blade.php'));
    $reportModal = Str::before(Str::after($reportView, '<div id="customReportModal"'), '<div id="reportFormatModal"');
    $pengajuanView = file_get_contents(resource_path('views/layouts/administrasi/pengajuan.blade.php'));
    $pengajuanModal = Str::beforeLast(Str::before(Str::after($pengajuanView, '<div id="pengajuanDetailModal"'), '</x-app-layout>'), '</div>');
    $pending = [
        'type_code' => 'snp', 'record_key' => 'SNP', 'title' => 'Pengajuan Edit SNP',
        'butir' => ['ID Butir' => 'SNP.01'], 'isi_butir' => $content,
    ];
    $html = Blade::render(<<<'BLADE'
        <div x-data="{ open: true, selectedPic: 'unit-2', items: @js($items) }">
            <div id="parentForm" x-show="open" @click.outside="open = false" @keydown.escape.window="open = false">
                <textarea id="draft">Draf tanggapan belum disimpan.</textarea>
                <select id="pic" x-model="selectedPic"><option>unit-1</option><option>unit-2</option></select>
                <x-snp-butir-preview id="staticPreview" :content="$content" butir-id="SNP.01" context="Tanggapan SNP" />
                <x-snp-butir-preview id="dynamicPreview" content-expression="items[1].butir_snp" id-expression="items[1].id_butir_snp" items-expression="items" :expanded="true" context="Reviu SNP" />
            </div>
        </div>
        <input type="checkbox" name="record_ids[]" value="1" checked data-record-label="Surat contoh" data-butirs='@json($items)'>
        <button type="button" id="openCustomReportModalBtn">Report custom</button>
        <button type="button" data-pengajuan-detail-trigger data-detail-pengajuan="{{ base64_encode(json_encode($pending)) }}">Pengajuan SNP</button>
        <button type="button" id="otherModulePending" data-pengajuan-detail-trigger data-detail-pengajuan="{{ base64_encode(json_encode(array_merge($pending, ['type_code' => 'djsn']))) }}">Pengajuan DJSN</button>
        BLADE, compact('content', 'items', 'pending'));
    $html .= Blade::render('<div id="customReportModal"'.$reportModal);
    $html .= Blade::render('<div id="pengajuanDetailModal"'.$pengajuanModal);
    $modalMarkers = [
        'perekaman' => ['{{-- Modal Detail Butir --}}', '{{-- Modal Tambah Perekaman --}}'],
        'tanggapan' => ['{{-- Modal Detail Tanggapan --}}', '<div x-show="openEditModal"'],
        'reviu' => ['{{-- Modal Detail Surat Reviu --}}', '{{-- Modal Reviu --}}'],
        'tindak-lanjut' => ['{{-- Modal Detail Tindak Lanjut --}}', '<div x-show="openEditModal"'],
    ];
    foreach ($modalMarkers as $page => [$start, $end]) {
        $source = file_get_contents(resource_path('views/layouts/snp/'.$page.'.blade.php'));
        $opening = Str::before(Str::after($source, '<x-app-layout>'), '<div class="rounded-2xl border border-blue-100 bg-white p-6 shadow-sm">');
        $body = Str::between($source, $start, $end);
        $payload = [
            'id_snp' => 'SNP', 'id_butir_snp' => 'SNP.01', 'butir_snp' => $content,
            'butirs' => $items, 'pic_tanggapans' => [], 'tindak_lanjuts' => [],
        ];
        $html .= '<section id="detail-'.$page.'">'.Blade::render(
            $opening.'<button type="button" data-open-detail @click="openDetailModalFor(@js($payload), 1)">Buka detail</button>'.$body.'</div>',
            ['payload' => $payload, 'clusters' => [], 'direktorats' => [], 'butirSiapTindakLanjut' => collect()],
        ).'</section>';
    }
    $kompilasiView = file_get_contents(resource_path('views/layouts/snp/kompilasi.blade.php'));
    $kompilasiForm = Str::beforeLast(Str::afterLast($kompilasiView, '@if ($canCreateKompilasi)'), '@endif');
    $selectedItem = $items[0] + ['tahap' => 'tanggapan', 'tahap_label' => 'Kompilasi Tanggapan', 'hasil_kompilasi' => '', 'deliverables' => '', 'ubah_tgl' => ''];
    $html .= Blade::render('<section id="kompilasiForm" x-data="{ openModal: false, selectedItem: @js($selectedItem) }"><button type="button" data-open-kompilasi @click="openModal = true">Kompilasi</button>'.$kompilasiForm.'</section>', compact('selectedItem'));
    $html .= view('components.snp-butir-reader')->render();

    $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true, flags: JSON_THROW_ON_ERROR);
    $process = new Process(['node', base_path('tests/Unit/SnpButirReader.browser.mjs')], base_path());
    $process->setInput(json_encode([
        'html' => $html,
        'content' => $content,
        'css' => $manifest['resources/css/app.css']['file'],
        'js' => $manifest['resources/js/app.js']['file'],
    ], JSON_THROW_ON_ERROR));
    $process->setTimeout(60);
    $process->run();

    expect($process->isSuccessful())->toBeTrue($process->getOutput().$process->getErrorOutput());
});
