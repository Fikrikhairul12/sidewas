<?php

use App\Services\ButirReportPdfAlignment;
use App\Services\SnpButirContent;
use App\Services\SnpReportPdfLabels;
use Dompdf\Canvas;
use Dompdf\Dompdf;
use Dompdf\Frame;
use Illuminate\Support\Collection;
use Tests\TestCase;

uses(TestCase::class);

function paginatedReportRecords(string $module, array $contents): Collection
{
    $recordClass = 'App\\Models\\'.ucfirst($module).'Record';
    $butirClass = 'App\\Models\\'.ucfirst($module).'Butir';
    $butirs = collect($contents)->map(function (string $content, int $index) use ($module, $butirClass) {
        $butir = (new $butirClass)->forceFill([
            'id' => $index + 1,
            'id_butir_'.$module => strtoupper($module).'.0'.($index + 1),
            SnpButirContent::contentField($module) => $content,
            'tanggal_'.$module => '2026-10-16',
            'agenda_'.$module => 'Agenda pengawasan',
        ]);
        foreach (['butirPics', 'butirDirektorats', 'reviews', 'kompilasis', 'kompilasiTindakLanjuts', 'tindakLanjuts'] as $relation) {
            $butir->setRelation($relation, collect());
        }
        foreach (['tanggapan', 'kompilasiTanggapan', 'kompilasiTindakLanjut', 'reviewTindakLanjut'] as $relation) {
            $butir->setRelation($relation, null);
        }

        return $butir;
    });
    $record = (new $recordClass)->forceFill(['id' => 1, 'nomor_surat' => 'SURAT-UJI', 'tanggal_surat' => '2026-10-16', 'perihal_surat' => 'Uji pembagian halaman', 'jth_tempo' => '2026-11-16']);
    $record->setRelation('butir'.ucfirst($module), $butirs);

    return collect([$record]);
}

function paginatedReportHtml(string $module, string $template, Collection $records, ?array $fields = null): string
{
    $fields ??= in_array($module, ['snp', 'djsn'], true)
        ? ['surat', 'id_butir', 'isi_butir', 'pic_unit', 'tanggapan_tl', 'deliverable', 'dokumen', 'jatuh_tempo', 'komite', 'hasil_reviu', 'status']
        : ['surat', 'tgl_agenda', 'keputusan', 'direktorat', 'unit_pic', 'tindak_lanjut', 'deliverable', 'dokumen', 'jatuh_tempo', 'hasil_reviu', 'status'];

    return view('layouts.'.$module.'.report.'.$template, [
        'records' => $records, 'selectedFields' => $fields, 'fieldLabels' => array_combine($fields, $fields),
        'printedBy' => 'Penguji', 'printedAt' => '16/10/2026 09:00',
    ])->render();
}

/** @return array{pdf: Dompdf, rows: list<array<string, mixed>>, text: string, headers: array<int, int>} */
function inspectPaginatedReport(string $html): array
{
    $rows = [];
    $text = [];
    $headers = [];
    $pdf = new Dompdf;
    $pdf->setPaper('legal', 'landscape');
    $pdf->getOptions()->setIsJavascriptEnabled(false);
    $pdf->setCallbacks([...app(ButirReportPdfAlignment::class)->callbacks(), ...app(SnpReportPdfLabels::class)->callbacks(), ['event' => 'end_frame', 'f' => function (Frame $frame, Canvas $canvas) use (&$rows, &$text, &$headers): void {
        $node = $frame->get_node();
        if ($node instanceof DOMText) {
            $text[] = $node->textContent;
        }
        if (! $node instanceof DOMElement) {
            return;
        }
        $page = $canvas->get_page_number();
        if ($node->tagName === 'thead') {
            $headers[$page] = ($headers[$page] ?? 0) + 1;
        }
        if ($node->tagName === 'tr' && $node->hasAttribute('data-report-butir')) {
            $rows[] = ['id' => $node->getAttribute('data-report-butir'), 'page' => $page, 'planned' => (int) $node->getAttribute('data-report-page') + 1, 'box' => $frame->get_border_box(), 'class' => $node->getAttribute('class')];
        }
    }]]);
    $pdf->loadHtml($html);
    $pdf->render();

    return ['pdf' => $pdf, 'rows' => $rows, 'text' => preg_replace('/\s+/u', ' ', implode(' ', $text)), 'headers' => $headers];
}

function reportParagraphs(string $prefix, int $count): string
{
    return SnpButirContent::PREFIX.implode('', array_map(fn (int $number): string => '<p>'.$prefix.'-'.$number.'</p>', range(1, $count)));
}

dataset('paginated report templates', collect(['snp', 'ragab', 'rawas', 'djsn', 'eksternal'])->flatMap(fn (string $module): array => [
    $module.' regular' => [$module, 'pdf'], $module.' custom' => [$module, 'pdf-custom'],
])->all());

test('short butirs move together when the remaining page cannot contain them', function (string $module, string $template) {
    $records = paginatedReportRecords($module, [reportParagraphs('PENGISI', 39), reportParagraphs('BUTIR-PENDEK', 8)]);
    $result = inspectPaginatedReport(paginatedReportHtml($module, $template, $records));
    $pages = collect($result['rows'])->groupBy('id')->map(fn (Collection $rows): array => $rows->pluck('page')->unique()->values()->all());
    expect($pages[strtoupper($module).'.01'])->toBe([1])
        ->and($pages[strtoupper($module).'.02'])->toBe([2])
        ->and($result['pdf']->getCanvas()->get_page_count())->toBe(2);
    foreach (range(1, 8) as $number) {
        expect($result['text'])->toContain('BUTIR-PENDEK-'.$number);
    }
})->with('paginated report templates');

test('long butirs use meaningful remaining space and close each page without clipping or blank pages', function (string $module, string $template) {
    $records = paginatedReportRecords($module, [reportParagraphs('PENGISI', 30), reportParagraphs('BUTIR-PANJANG', 42)]);
    $html = paginatedReportHtml($module, $template, $records);
    $result = inspectPaginatedReport($html);
    $second = collect($result['rows'])->where('id', strtoupper($module).'.02')->pluck('page')->unique()->values()->all();
    expect($second)->toBe([1, 2], json_encode($result['rows']))->and($result['pdf']->getCanvas()->get_page_count())->toBe(2);
    foreach ($result['rows'] as $row) {
        expect($row['page'])->toBe($row['planned'])
            ->and($row['box']['y'])->toBeGreaterThanOrEqual(8 * 72 / 25.4)
            ->and($row['box']['y'] + $row['box']['h'])->toBeLessThanOrEqual(612 - 14 * 72 / 25.4);
    }
    foreach (collect($result['rows'])->groupBy('page') as $page => $rows) {
        expect($rows->first()['class'])->toContain('report-page-start')
            ->and($rows->last()['class'])->toContain('report-page-end')
            ->and($result['headers'][$page])->toBe(1);
    }
    foreach (range(1, 42) as $number) {
        expect($result['text'])->toContain('BUTIR-PANJANG-'.$number);
    }
    if (getenv('REPORT_PAGINATION_ARTIFACTS')) {
        file_put_contents(getenv('REPORT_PAGINATION_ARTIFACTS').'/'.$module.'-'.$template.'.pdf', $result['pdf']->output());
        file_put_contents(getenv('REPORT_PAGINATION_ARTIFACTS').'/'.$module.'-'.$template.'.html', $html);
    }
})->with('paginated report templates');

test('long responses and reviews split safely even when the main butir is short or omitted', function (string $module, string $template) {
    $records = paginatedReportRecords($module, ['BUTIR-SINGKAT']);
    $butir = $records[0]->{'butir'.ucfirst($module)}[0];
    $longText = implode(' ', array_map(fn (int $number): string => 'RESPON-'.$number, range(1, 650)));
    $review = implode(' ', array_map(fn (int $number): string => 'REVIU-'.$number, range(1, 500)));
    if ($module === 'snp') {
        $butir->setRelation('kompilasiTanggapan', (object) ['hasil_kompilasi' => $longText, 'deliverables' => 'DELIVERABLE', 'dokumen' => 'kompilasi.pdf', 'ubah_tgl' => null, 'status_pengajuan_tgl' => null]);
        $butir->setRelation('reviews', collect([(object) ['id' => 1, 'tahap_review' => 'tanggapan', 'hasil_review' => $review, 'status' => 'selesai_tuntas']]));
        $fields = ['kompilasi_tanggapan', 'hasil_reviu', 'dokumen'];
    } elseif ($module === 'djsn') {
        $butir->setRelation('tanggapan', (object) ['tanggapan' => $longText, 'deliverables' => 'DELIVERABLE', 'dokumen' => 'tanggapan.pdf', 'ubah_tgl' => null, 'status_pengajuan_tgl' => null, 'review' => (object) ['hasil_review' => $review, 'status' => 'selesai_tuntas']]);
        $fields = ['tanggapan_tl', 'hasil_reviu', 'dokumen'];
    } else {
        $butir->setRelation('tindakLanjuts', collect([(object) ['id' => 1, 'tindak_lanjut' => $longText, 'deliverables' => 'DELIVERABLE', 'dokumen' => 'tindak-lanjut.pdf', 'jth_tempo' => null, 'unitKerja' => null]]));
        $butir->setRelation('reviewTindakLanjut', (object) ['hasil_review' => $review, 'status' => 'selesai_tuntas']);
        $fields = ['tindak_lanjut', 'hasil_reviu', 'dokumen'];
    }
    $html = paginatedReportHtml($module, $template, $records, $fields);
    $result = inspectPaginatedReport($html);
    expect($result['pdf']->getCanvas()->get_page_count())->toBeGreaterThan(1);
    foreach (range(1, 650) as $number) {
        expect($result['text'])->toContain('RESPON-'.$number);
    }
    foreach (range(1, 500) as $number) {
        expect($result['text'])->toContain('REVIU-'.$number);
    }
    foreach ($result['rows'] as $row) {
        expect($row['page'])->toBe($row['planned'])
            ->and($row['box']['y'] + $row['box']['h'])->toBeLessThanOrEqual(612 - 14 * 72 / 25.4);
    }
    expect($html)->toContain('/storage/', 'Lanjutan');
    if (getenv('REPORT_PAGINATION_ARTIFACTS')) {
        file_put_contents(getenv('REPORT_PAGINATION_ARTIFACTS').'/'.$module.'-'.$template.'-responses.pdf', $result['pdf']->output());
    }
})->with('paginated report templates');

test('a new long butir starts on the next page when only a tiny space remains', function (string $module, string $template) {
    $records = paginatedReportRecords($module, [reportParagraphs('PENGISI', 43), reportParagraphs('LANJUTAN', 55)]);
    $result = inspectPaginatedReport(paginatedReportHtml($module, $template, $records));
    $second = collect($result['rows'])->where('id', strtoupper($module).'.02');
    expect($second->first()['page'])->toBe(2)
        ->and($result['pdf']->getCanvas()->get_page_count())->toBe(3);
    foreach ($result['rows'] as $row) {
        expect($row['page'])->toBe($row['planned']);
    }
})->with('paginated report templates');

test('a heading and its butir identity are not stranded before the paragraph starts on a new page', function (string $module, string $template) {
    $content = SnpButirContent::PREFIX.'<p><strong>JUDUL-PENGAWASAN</strong></p><p>'.implode('<br>', array_fill(0, 8, 'Paragraf pendek yang harus utuh')).'</p><p>'.str_repeat('Paragraf panjang berikutnya. ', 180).'</p>';
    $records = paginatedReportRecords($module, [reportParagraphs('PENGISI', 39), $content]);
    $result = inspectPaginatedReport(paginatedReportHtml($module, $template, $records));
    $second = collect($result['rows'])->where('id', strtoupper($module).'.02');
    expect($second->first()['page'])->toBe(2)
        ->and($result['text'])->toContain('JUDUL-PENGAWASAN', 'Paragraf pendek yang harus utuh');
    foreach ($result['rows'] as $row) {
        expect($row['page'])->toBe($row['planned']);
    }
})->with('paginated report templates');

test('custom SNP unit responses retain their bold unit headings paragraphs and separation across pages', function () {
    $records = paginatedReportRecords('snp', ['BUTIR-UJI']);
    $butir = $records[0]->butirSnp[0];
    $butir->setRelation('tanggapan', collect([1, 2])->map(fn (int $unit): object => (object) [
        'tanggapan' => 'AWAL-UNIT-'.$unit."\n\n".str_repeat('Tanggapan lengkap unit kerja. ', 240).' AKHIR-UNIT-'.$unit,
        'butirPic' => (object) ['unitKerja' => (object) ['kode_unit' => 'UNIT-'.$unit, 'nama_unit' => 'Nama Unit '.$unit]],
    ]));
    $html = paginatedReportHtml('snp', 'pdf-custom', $records, ['isi_butir', 'tanggapan_unit']);
    $result = inspectPaginatedReport($html);
    expect($result['pdf']->getCanvas()->get_page_count())->toBeGreaterThan(1)
        ->and($html)->toContain('entry-title', 'border-bottom:1px solid #999')
        ->and($result['text'])->toContain('UNIT-1', 'Nama Unit 1', 'AWAL-UNIT-1', 'AKHIR-UNIT-1', 'UNIT-2', 'Nama Unit 2', 'AWAL-UNIT-2', 'AKHIR-UNIT-2')
        ->and(substr_count($result['text'], 'Tanggapan lengkap unit kerja.'))->toBe(480);
    foreach ($result['rows'] as $row) {
        expect($row['page'])->toBe($row['planned']);
    }
    if (getenv('REPORT_PAGINATION_ARTIFACTS')) {
        file_put_contents(getenv('REPORT_PAGINATION_ARTIFACTS').'/snp-pdf-custom-unit-responses.pdf', $result['pdf']->output());
    }
});

test('regular and custom downloads keep browser preview scripts out of the PDF', function (string $module, string $template) {
    $records = paginatedReportRecords($module, ['ISI-BUTIR-UJI']);
    $field = in_array($module, ['snp', 'djsn'], true) ? 'isi_butir' : 'keputusan';
    $controller = app('App\\Http\\Controllers\\'.ucfirst($module).'\\Report'.ucfirst($module).'Controller');
    $response = (new ReflectionMethod($controller, 'downloadPdf'))->invoke($controller, 'layouts.'.$module.'.report.'.$template, [
        'records' => $records, 'selectedFields' => [$field], 'fieldLabels' => [$field => 'ISI BUTIR'],
    ], 'report-uji.pdf');
    expect($response->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($response->headers->get('Content-Disposition'))->toContain('attachment', 'report-uji.pdf')
        ->and($response->getContent())->toStartWith('%PDF-')->not->toContain('/JavaScript');
})->with('paginated report templates');
