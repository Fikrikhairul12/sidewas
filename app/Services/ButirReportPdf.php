<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Database\Eloquent\Model;

class ButirReportPdf
{
    public function __construct(private SnpButirReportContent $content) {}

    /** @param iterable<Model> $records */
    public function render(string $html, iterable $records, string $module): string
    {
        $values = [];
        $relation = 'butir'.ucfirst($module);
        foreach ($records as $record) {
            foreach ($record->{$relation} as $butir) {
                $values[$butir->{'id_butir_'.$module}] = $butir->{SnpButirContent::contentField($module)};
            }
        }
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $xpath = new DOMXPath($document);
        $headers = $xpath->query('//table/thead/tr/th');
        $columnCount = $headers->length;
        $contentColumn = null;
        foreach ($headers as $index => $header) {
            if ($header->hasAttribute('data-report-content')) {
                $contentColumn = $index;
            }
        }
        $percentage = max(28, 100 * 4 / max(1, $columnCount + 3));
        if ($contentColumn !== null) {
            foreach ($headers as $index => $header) {
                $header->setAttribute('style', 'width:'.($index === $contentColumn ? $percentage : (100 - $percentage) / max(1, $columnCount - 1)).'%;');
            }
        }
        $body = $xpath->query('//table/tbody')->item(0);
        if ($body instanceof DOMElement) {
            $groups = [];
            $spans = [];
            foreach ($xpath->query('./tr', $body) as $row) {
                $id = $row->getAttribute('data-report-butir');
                $cells = [];
                foreach ($spans as $column => $span) {
                    $cells[$column] = $this->emptyCell($span['cell']);
                    if (--$spans[$column]['remaining'] === 0) {
                        unset($spans[$column]);
                    }
                }
                $column = 0;
                foreach ($xpath->query('./td', $row) as $cell) {
                    while (isset($cells[$column])) {
                        $column++;
                    }
                    $copy = $cell->cloneNode(true);
                    $copy->removeAttribute('rowspan');
                    $cells[$column] = $copy;
                    $rowspan = (int) $cell->getAttribute('rowspan');
                    if ($rowspan > 1) {
                        $spans[$column] = ['cell' => $copy, 'remaining' => $rowspan - 1];
                    }
                    $column++;
                }
                ksort($cells);
                $groups[$id][] = $cells;
            }
            while ($body->firstChild) {
                $body->removeChild($body->firstChild);
            }
            foreach ($groups as $id => $rows) {
                $chunks = $contentColumn === null ? [] : $this->content->pdfChunks($values[$id] ?? '', 1238 * $percentage / 100 - 10);
                $rowCount = max(count($rows), count($chunks));
                for ($index = 0; $index < $rowCount; $index++) {
                    $row = $document->createElement('tr');
                    $row->setAttribute('data-report-butir', $id);
                    $row->setAttribute('class', ($index === 0 ? 'butir-start ' : '').($index === $rowCount - 1 ? 'butir-end' : ''));
                    for ($column = 0; $column < $columnCount; $column++) {
                        $cell = $rows[$index][$column] ?? $this->emptyCell($rows[0][$column]);
                        if ($cell->hasAttribute('data-report-label')) {
                            if ($module === 'djsn') {
                                while ($cell->firstChild) {
                                    $cell->removeChild($cell->firstChild);
                                }
                            }
                            $label = $document->createElement('div');
                            $label->setAttribute('data-report-label-id', $id);
                            $label->setAttribute('class', 'report-butir-label');
                            if ($index === 0 && $module === 'djsn') {
                                $label->setAttribute('data-report-show-first', '1');
                            }
                            $label->appendChild($document->createTextNode($id));
                            $continuation = $document->createElement('div', 'Lanjutan');
                            $continuation->setAttribute('class', 'report-continuation');
                            $label->appendChild($continuation);
                            $cell->appendChild($label);
                        }
                        if ($column === $contentColumn) {
                            while ($cell->firstChild) {
                                $cell->removeChild($cell->firstChild);
                            }
                            $cell->setAttribute('class', 'report-butir-content');
                            $cell->setAttribute('data-report-content', $id);
                            $fragment = new DOMDocument('1.0', 'UTF-8');
                            @$fragment->loadHTML('<?xml encoding="UTF-8"><div>'.($chunks[$index] ?? '').'</div>', LIBXML_NONET);
                            foreach ($fragment->getElementsByTagName('div')->item(0)?->childNodes ?? [] as $child) {
                                $cell->appendChild($document->importNode($child, true));
                            }
                        }
                        $row->appendChild($cell);
                    }
                    $body->appendChild($row);
                }
            }
        }
        $style = $document->createElement('style', '@page { size:legal landscape; margin:8mm 8mm 14mm; } body { margin:0; width:1283px; } thead { display:table-header-group; } tbody td { border-top:0; border-bottom:0; } .butir-start td { border-top:1px solid #000; } .butir-end td { border-bottom:1px solid #000; } tr { break-inside:avoid; } .report-butir-content { white-space:normal; } .report-butir-content img { max-width:100%; } .report-butir-label { visibility:hidden; white-space:normal; } [data-report-show-first] { visibility:visible; } .report-continuation { visibility:hidden; color:#666; } .print-footer { left:0; bottom:0; font-size:6px; }');
        $document->getElementsByTagName('head')->item(0)->appendChild($style);

        return str_replace('</body>', view('layouts.partials.report-page-labels')->render().'</body>', $document->saveHTML());
    }

    private function emptyCell(DOMElement $original): DOMElement
    {
        $cell = $original->cloneNode(false);
        $cell->removeAttribute('rowspan');

        return $cell;
    }
}
