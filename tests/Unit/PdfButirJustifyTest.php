<?php

use App\Services\ButirReportPdfAlignment;
use App\Services\SnpButirContent;
use Dompdf\Canvas;
use Dompdf\Frame;
use Dompdf\FrameDecorator\Block;
use Dompdf\FrameDecorator\Text;
use Illuminate\Support\Collection;
use Tests\TestCase;

uses(TestCase::class);

function justifiedReportRecords(string $module, string $content): Collection
{
    $recordClass = 'App\\Models\\'.ucfirst($module).'Record';
    $butirClass = 'App\\Models\\'.ucfirst($module).'Butir';
    $butir = (new $butirClass)->forceFill([
        'id' => 1,
        'id_butir_'.$module => strtoupper($module).'.01',
        SnpButirContent::contentField($module) => $content,
        'status' => 'dalam_proses',
        'tanggal_'.$module => '2026-10-07',
        'agenda_'.$module => 'Uji format paragraf',
    ]);
    foreach (['butirPics', 'butirDirektorats', 'reviews', 'kompilasis', 'kompilasiTindakLanjuts', 'tindakLanjuts'] as $relation) {
        $butir->setRelation($relation, collect());
    }
    foreach (['tanggapan', 'kompilasiTanggapan', 'kompilasiTindakLanjut', 'reviewTindakLanjut'] as $relation) {
        $butir->setRelation($relation, null);
    }
    $record = (new $recordClass)->forceFill(['id' => 1, 'nomor_surat' => 'SURAT-UJI', 'tanggal_surat' => '2026-10-07', 'perihal_surat' => 'Uji justify', 'jth_tempo' => '2026-11-06']);
    $record->setRelation('butir'.ucfirst($module), collect([$butir]));

    return collect([$record]);
}

test('downloaded PDFs justify soft wrapped lines to the actual column edge while preserving paragraph endings and other alignments', function (string $module, string $template) {
    $sentence = 'Pengawasan sesuai rencana dan hasil evaluasi. ';
    $content = SnpButirContent::PREFIX
        .'<p style="text-align:left"><span style="font-size:12px">TES-KIRI</span></p>'
        .'<p style="text-align:right"><span style="font-size:12px">TES-KANAN</span></p>'
        .'<p style="text-align:center"><span style="font-size:12px">TES-TENGAH</span></p>'
        .'<p style="text-align:justify"><span style="font-size:12px">'.str_repeat($sentence, 100).'<strong>format tebal</strong> <em>format miring</em> <u>garis bawah</u> '.str_repeat($sentence, 100).'END-PARAGRAPH</span></p>'
        .'<p style="text-align:justify"><span style="font-size:12px">BARIS-PENDEK<br>SETELAH-BR</span></p>'
        .'<ol start="3"><li><p style="text-align:justify"><span style="font-size:18px">'.str_repeat('Daftar dengan beberapa kata. ', 20).'END-LIST</span></p></li></ol>'
        .'<p style="text-align:justify"><span style="font-size:12px">'.str_repeat('X', 200).' END-TOKEN</span></p>';
    $field = in_array($module, ['snp', 'djsn'], true) ? 'isi_butir' : 'keputusan';
    $view = $template === 'pdf' ? 'pdf' : 'pdf-custom';
    $fields = $template === 'content-only' ? [$field] : (in_array($module, ['snp', 'djsn'], true)
        ? ['surat', 'id_butir', $field, 'pic_unit', 'tanggapan_tl', 'deliverable', 'dokumen', 'jatuh_tempo', 'komite', 'hasil_reviu', 'status']
        : ['surat', 'tgl_agenda', $field, 'direktorat', 'unit_pic', 'tindak_lanjut', 'deliverable', 'dokumen', 'jatuh_tempo', 'hasil_reviu', 'status']);
    $data = ['records' => justifiedReportRecords($module, $content), 'selectedFields' => $fields, 'fieldLabels' => array_combine($fields, $fields)];
    $lines = [];
    $observeLines = function (Frame $frame, Canvas $canvas) use (&$lines): void {
        $node = $frame->get_node();
        $block = $frame instanceof Block ? $frame : $frame->get_decorator();
        if (! $node instanceof DOMElement || ! str_contains($node->getAttribute('class'), 'snp-content-line') || ! $block instanceof Block) {
            return;
        }
        foreach ($block->get_line_boxes() as $line) {
            $textFrames = array_values(array_filter($line->get_frames(), fn ($child): bool => $child instanceof Text && $child->get_text() !== ''));
            if ($textFrames === []) {
                continue;
            }
            $bounds = array_map(fn (Text $text): array => $text->get_border_box(), $textFrames);
            $lines[] = [
                'text' => trim(implode('', array_map(fn (Text $text): string => $text->get_text(), $textFrames))),
                'alignment' => $frame->get_style()->text_align,
                'box' => $frame->get_content_box(),
                'left' => min(array_column($bounds, 'x')),
                'right' => max(array_map(fn (array $box): float => $box['x'] + $box['w'], $bounds)),
                'spacing' => max(array_map(fn (Text $text): float => $text->get_text_spacing(), $textFrames)),
                'page' => $canvas->get_page_number(),
                'bold' => count(array_filter($textFrames, fn (Text $text): bool => $text->get_style()->font_weight === 'bold' || (float) $text->get_style()->font_weight >= 700)),
                'italic' => count(array_filter($textFrames, fn (Text $text): bool => $text->get_style()->font_style === 'italic')),
                'underline' => count(array_filter($textFrames, fn (Text $text): bool => in_array('underline', (array) $text->get_parent()->get_style()->text_decoration, true))),
            ];
        }
    };
    $this->app->instance(ButirReportPdfAlignment::class, new class($observeLines) extends ButirReportPdfAlignment
    {
        public function __construct(private Closure $observeLines) {}

        public function callbacks(): array
        {
            return [...parent::callbacks(), ['event' => 'end_frame', 'f' => $this->observeLines]];
        }
    });
    $controllerClass = 'App\\Http\\Controllers\\'.ucfirst($module).'\\Report'.ucfirst($module).'Controller';
    $response = (new ReflectionMethod($controllerClass, 'downloadPdf'))->invoke(app($controllerClass), 'layouts.'.$module.'.report.'.$view, $data, $module.'-justify.pdf');
    expect($response->getContent())->toStartWith('%PDF-')
        ->and($response->headers->get('content-type'))->toBe('application/pdf');
    expect($lines)->not->toBeEmpty();
    $justifiedPages = [];
    $justifiedCount = 0;
    foreach ($lines as $line) {
        $right = $line['box']['x'] + $line['box']['w'];
        expect($line['left'])->toBeGreaterThanOrEqual($line['box']['x'] - 0.1)
            ->and($line['right'])->toBeLessThanOrEqual($right + 0.1);
        if ($line['alignment'] === 'justify' && str_contains($line['text'], ' ') && ! preg_match('/END-|BARIS-PENDEK|SETELAH-BR/', $line['text'])) {
            expect(abs($right - $line['right']))->toBeLessThan(0.1, $module.'/'.$template.': '.$line['text']);
            $justifiedPages[$line['page']] = true;
            $justifiedCount++;
        } elseif ($line['alignment'] === 'justify') {
            expect($line['spacing'])->toBe(0.0);
        }
        if ($line['text'] === 'TES-KIRI') {
            expect(abs($line['left'] - $line['box']['x']))->toBeLessThan(0.1);
        } elseif ($line['text'] === 'TES-KANAN') {
            expect(abs($right - $line['right']))->toBeLessThan(0.1);
        } elseif ($line['text'] === 'TES-TENGAH') {
            expect(abs(($line['left'] + $line['right']) / 2 - ($line['box']['x'] + $right) / 2))->toBeLessThan(0.1);
        }
    }
    $text = preg_replace('/\s+/u', ' ', implode(' ', array_column($lines, 'text')));
    expect(substr_count($text, trim($sentence)))->toBe(200)
        ->and($text)->toContain('END-PARAGRAPH', 'BARIS-PENDEK SETELAH-BR', '3. Daftar', 'END-LIST', 'END-TOKEN')
        ->and($justifiedCount)->toBeGreaterThan(5)
        ->and(count($justifiedPages))->toBeGreaterThan(1)
        ->and(array_sum(array_column($lines, 'bold')))->toBeGreaterThan(0)
        ->and(array_sum(array_column($lines, 'italic')))->toBeGreaterThan(0)
        ->and(array_sum(array_column($lines, 'underline')))->toBeGreaterThan(0);
    if (getenv('JUSTIFY_REPORT_ARTIFACTS')) {
        file_put_contents(getenv('JUSTIFY_REPORT_ARTIFACTS').'/'.$module.'-'.$template.'.pdf', $response->getContent());
    }
})->with(['snp' => ['snp'], 'ragab' => ['ragab'], 'rawas' => ['rawas'], 'djsn' => ['djsn'], 'eksternal' => ['eksternal']])->with(['regular' => ['pdf'], 'custom' => ['pdf-custom'], 'content only' => ['content-only']]);
