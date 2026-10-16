<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use Dompdf\Dompdf;
use Dompdf\Frame;
use DOMXPath;
use LogicException;

/**
 * Measure content before building page tables because Dompdf cannot split a table cell.
 * Keep related stage columns together while allowing each cell's content to continue.
 */
class ReportPdfPagination
{
    private const SHORT_BUTIR_RATIO = 0.25;

    private const MINIMUM_START_RATIO = 0.10;

    private const CELL_PADDING = 6.0;

    private const BORDER_ALLOWANCE = 1.5;

    public function __construct(private ReportPdfCellContent $content) {}

    public function render(string $html): string
    {
        $document = $this->document($html);
        $xpath = new DOMXPath($document);
        $table = $xpath->query('//table[thead and tbody]')->item(0);
        if (! $table instanceof DOMElement || ! $xpath->query('./tbody/tr', $table)->length) {
            return $html;
        }

        $style = $document->createElement('style', '.report-cell-line { margin:0; padding:0; } .report-cell-line .entry-title { display:inline; margin:0; } .report-butir-label { visibility:hidden; white-space:normal; } .report-continuation { visibility:hidden; color:#666; } .report-page-start td { border-top:1px solid #000; } .report-page-end td { border-bottom:1px solid #000; } .report-page-table { margin:0; }');
        $document->getElementsByTagName('head')->item(0)->appendChild($style);
        [$widths, $headerHeight] = $this->measureColumns($document, $table);
        $capacity = 612 - (8 + 14) * 72 / 25.4 - $headerHeight - 4;
        $rows = $this->prepareRows($document, $table, $widths);
        $this->measureUnits($document, $widths, $rows);
        $pages = $this->paginate($rows, $capacity);

        $template = $table->cloneNode(false);
        foreach ($table->childNodes as $child) {
            if ($child instanceof DOMElement && $child->tagName !== 'tbody') {
                $template->appendChild($child->cloneNode(true));
            }
        }
        $template->appendChild($document->createElement('tbody'));
        foreach ($pages as $page => $fragments) {
            $pageTable = $template->cloneNode(true);
            $pageTable->setAttribute('class', trim($table->getAttribute('class').' report-page-table'));
            $pageTable->setAttribute('data-report-page', (string) $page);
            if ($page > 0) {
                $pageTable->setAttribute('style', $table->getAttribute('style').';page-break-before:always;');
            }
            $body = $pageTable->getElementsByTagName('tbody')->item(0);
            foreach ($fragments as $index => $fragment) {
                $row = $document->createElement('tr');
                $row->setAttribute('data-report-butir', $fragment['id']);
                $row->setAttribute('data-report-page', (string) $page);
                $row->setAttribute('class', trim(($fragment['first'] ? 'butir-start ' : '').($fragment['last'] ? 'butir-end ' : '').($index === 0 ? 'report-page-start ' : '').($index === count($fragments) - 1 ? 'report-page-end' : '')));
                foreach ($fragment['cells'] as $cell) {
                    $copy = $cell['element']->cloneNode(false);
                    $copy->removeAttribute('rowspan');
                    $this->appendHtml($copy, $cell['label'].implode('', array_column($cell['units'], 'html')));
                    $row->appendChild($copy);
                }
                $body->appendChild($row);
            }
            $table->parentNode->insertBefore($pageTable, $table);
        }
        $table->parentNode->removeChild($table);

        return $document->saveHTML();
    }

    /** @return array{list<float>, float} */
    private function measureColumns(DOMDocument $document, DOMElement $table): array
    {
        $probe = $this->document($document->saveHTML());
        $probeTable = $probe->getElementsByTagName('table')->item(0);
        $body = $probeTable->getElementsByTagName('tbody')->item(0);
        while ($body->firstChild) {
            $body->removeChild($body->firstChild);
        }
        $row = $probe->createElement('tr');
        foreach ($table->getElementsByTagName('thead')->item(0)->getElementsByTagName('th') as $column => $header) {
            $cell = $probe->createElement('td');
            $cell->setAttribute('data-measure-column', (string) $column);
            $row->appendChild($cell);
        }
        $body->appendChild($row);
        $widths = [];
        $height = 0.0;
        $this->measure($probe, function (Frame $frame) use (&$widths, &$height): void {
            $node = $frame->get_node();
            if (! $node instanceof DOMElement) {
                return;
            }
            if ($node->hasAttribute('data-measure-column')) {
                $widths[(int) $node->getAttribute('data-measure-column')] = $frame->get_content_box()['w'] / 0.75;
            }
            if ($node->tagName === 'thead') {
                $height = max($height, $frame->get_border_box()['h']);
            }
        });
        ksort($widths);

        return [array_values($widths), $height];
    }

    /**
     * @param  list<float>  $widths
     * @return list<array{id: string, cells: list<array{element: DOMElement, label: string, labelHeight: float, units: list<array{html: string, block: int|string, line: bool, height: float, keepNext?: bool}>}>}>
     */
    private function prepareRows(DOMDocument $document, DOMElement $table, array $widths): array
    {
        $xpath = new DOMXPath($document);
        $rows = [];
        foreach ($xpath->query('./tbody/tr', $table) as $index => $element) {
            $id = $element->getAttribute('data-report-butir');
            $cells = [];
            foreach ($xpath->query('./td', $element) as $column => $cell) {
                $label = '';
                foreach ($xpath->query('.//*[@data-snp-butir-label]', $cell) as $node) {
                    $label .= $document->saveHTML($node);
                }
                $units = $this->content->units($cell, max(16, ($widths[$column] ?? 100) - 1));
                $cells[] = ['element' => $cell, 'label' => $label, 'labelHeight' => 0.0, 'units' => array_map(fn (array $unit): array => [...$unit, 'height' => 0.0], $units)];
            }
            if (! array_filter($cells, fn (array $cell): bool => $cell['label'] !== '')) {
                $column = 0;
                foreach ($cells as $candidate => $cell) {
                    if ($cell['element']->hasAttribute('data-report-content') || $cell['element']->hasAttribute('data-snp-butir-content')) {
                        $column = $candidate;
                        break;
                    }
                }
                $cells[$column]['label'] = '<div class="report-butir-label" data-report-label-id="'.e($id).'" data-snp-butir-label="'.e($id).'" data-snp-show-first="0">'.e($id).'<div class="report-continuation" data-snp-continuation="1">Lanjutan</div></div>';
            }
            $contentOnly = array_filter($cells, fn (array $cell): bool => $cell['units'] !== [] && ! $cell['element']->hasAttribute('data-report-content') && ! $cell['element']->hasAttribute('data-snp-butir-content')) === [];
            $previous = array_key_last($rows);
            if ($contentOnly && $previous !== null && $rows[$previous]['id'] === $id) {
                foreach ($cells as $column => $cell) {
                    $rows[$previous]['cells'][$column]['units'] = [...$rows[$previous]['cells'][$column]['units'], ...$cell['units']];
                }
            } else {
                $rows[] = ['id' => $id ?: 'butir-'.$index, 'cells' => $cells];
            }
        }

        return $rows;
    }

    /**
     * Measure blocks at the printed column width without rendering empty table cells.
     * Minimal padding prevents child margins from collapsing out of the measured block.
     *
     * @param  list<float>  $widths
     * @param  list<array<string, mixed>>  $rows
     */
    private function measureUnits(DOMDocument $document, array $widths, array &$rows): void
    {
        $probe = $this->document($document->saveHTML());
        $probeTable = $probe->getElementsByTagName('table')->item(0);
        $container = $probe->createElement('div');
        $probeTable->parentNode->replaceChild($container, $probeTable);
        foreach ($rows as $rowIndex => $row) {
            foreach ($row['cells'] as $column => $cell) {
                foreach (['label' => $cell['label'], ...array_column($cell['units'], 'html')] as $unitIndex => $html) {
                    if ($html === '') {
                        continue;
                    }
                    $unit = $probe->createElement('div');
                    $unit->setAttribute('class', $cell['element']->getAttribute('class'));
                    $unit->setAttribute('style', 'width:'.$widths[$column].'px;font-size:8px;margin:0;padding:0.01px 0;page-break-inside:avoid;');
                    $unit->setAttribute('data-measure-unit', $rowIndex.':'.$column.':'.$unitIndex);
                    $this->appendHtml($unit, $html);
                    $container->appendChild($unit);
                }
            }
        }
        $this->measure($probe, function (Frame $frame) use (&$rows): void {
            $node = $frame->get_node();
            if (! $node instanceof DOMElement || ! $node->hasAttribute('data-measure-unit')) {
                return;
            }
            [$row, $column, $unit] = explode(':', $node->getAttribute('data-measure-unit'));
            $height = $frame->get_content_box()['h'];
            if ($unit === 'label') {
                $rows[$row]['cells'][$column]['labelHeight'] = $height;
            } else {
                $rows[$row]['cells'][$column]['units'][(int) $unit]['height'] = $height;
            }
        });
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<list<array<string, mixed>>>
     */
    private function paginate(array $rows, float $capacity): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $groups[$row['id']][] = $row;
        }
        $pages = [[]];
        $page = 0;
        $used = 0.0;
        foreach ($groups as $id => $group) {
            $total = array_sum(array_map(fn (array $row): float => $this->height($row['cells']), $group));
            $remaining = $capacity - $used;
            if ($used > 0 && $total > $remaining && ($total <= $capacity * self::SHORT_BUTIR_RATIO || $remaining < $capacity * self::MINIMUM_START_RATIO)) {
                $pages[++$page] = [];
                $used = 0.0;
            }
            $first = true;
            foreach ($group as $rowIndex => $row) {
                while (true) {
                    $remaining = $capacity - $used;
                    $cells = $row['cells'];
                    if ($this->height($cells) > $remaining) {
                        foreach ($cells as $column => $cell) {
                            $limit = $remaining - self::CELL_PADDING - self::BORDER_ALLOWANCE - $cell['labelHeight'];
                            $cells[$column]['units'] = $this->prefix($cell['units'], $limit, $capacity * self::SHORT_BUTIR_RATIO);
                        }
                        if ($first) {
                            foreach ($row['cells'] as $column => $cell) {
                                $isContent = $cell['element']->hasAttribute('data-report-content') || $cell['element']->hasAttribute('data-snp-butir-content');
                                if ($isContent && $cell['units'] !== [] && $cells[$column]['units'] === []) {
                                    foreach ($cells as &$fragment) {
                                        $fragment['units'] = [];
                                    }
                                    unset($fragment);
                                    break;
                                }
                            }
                        }
                        if (! array_filter($cells, fn (array $cell): bool => $cell['units'] !== [])) {
                            if ($used === 0.0) {
                                throw new LogicException('Satu elemen report melebihi area cetak satu halaman.');
                            }
                            $pages[++$page] = [];
                            $used = 0.0;

                            continue;
                        }
                    }
                    foreach ($cells as $column => $cell) {
                        $row['cells'][$column]['units'] = array_slice($row['cells'][$column]['units'], count($cell['units']));
                    }
                    $hasMore = (bool) array_filter($row['cells'], fn (array $cell): bool => $cell['units'] !== []);
                    $pages[$page][] = ['id' => $id, 'cells' => $cells, 'first' => $first, 'last' => ! $hasMore && $rowIndex === count($group) - 1];
                    $first = false;
                    $used += $this->height($cells);
                    if ($hasMore) {
                        $pages[++$page] = [];
                        $used = 0.0;
                    } else {
                        break;
                    }
                }
            }
        }

        return $pages;
    }

    /**
     * @param  list<array<string, mixed>>  $units
     * @return list<array<string, mixed>>
     */
    private function prefix(array $units, float $limit, float $shortHeight): array
    {
        $selected = [];
        $blocks = [];
        foreach ($units as $unit) {
            $key = $unit['block'];
            $last = array_key_last($blocks);
            if (! $unit['line'] || $last === null || $blocks[$last][0]['block'] !== $key) {
                $blocks[] = [];
                $last = array_key_last($blocks);
            }
            $blocks[$last][] = $unit;
        }
        foreach ($blocks as $block) {
            $height = array_sum(array_column($block, 'height'));
            if ($height <= $limit) {
                $selected = [...$selected, ...$block];
                $limit -= $height;

                continue;
            }
            if (! $block[0]['line'] || $height <= $shortHeight) {
                break;
            }
            $count = 0;
            $used = 0.0;
            foreach ($block as $unit) {
                if ($used + $unit['height'] > $limit) {
                    break;
                }
                $used += $unit['height'];
                $count++;
            }
            if (count($block) - $count === 1) {
                $count--;
            }
            if ($count >= 2) {
                $selected = [...$selected, ...array_slice($block, 0, $count)];
            }
            break;
        }

        if (count($selected) < count($units) && $selected !== [] && ($selected[array_key_last($selected)]['keepNext'] ?? false)) {
            $block = $selected[array_key_last($selected)]['block'];
            while ($selected !== [] && $selected[array_key_last($selected)]['block'] === $block) {
                array_pop($selected);
            }
        }

        return $selected;
    }

    /** @param list<array<string, mixed>> $cells */
    private function height(array $cells): float
    {
        return max(array_map(fn (array $cell): float => $cell['labelHeight'] + array_sum(array_column($cell['units'], 'height')), $cells)) + self::CELL_PADDING + self::BORDER_ALLOWANCE;
    }

    private function measure(DOMDocument $document, callable $callback): void
    {
        $pdf = app(Dompdf::class);
        $pdf->getOptions()->setIsJavascriptEnabled(false);
        $pdf->setPaper('legal', 'landscape');
        $pdf->setCallbacks([['event' => 'end_frame', 'f' => $callback]]);
        $pdf->loadHtml($document->saveHTML());
        $pdf->render();
    }

    private function appendHtml(DOMElement $element, string $html): void
    {
        $fragment = $this->document('<html><body><div>'.$html.'</div></body></html>');
        foreach ($fragment->getElementsByTagName('div')->item(0)->childNodes as $child) {
            $element->appendChild($element->ownerDocument->importNode($child, true));
        }
    }

    private function document(string $html): DOMDocument
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        foreach (iterator_to_array($document->childNodes) as $node) {
            if ($node->nodeType === XML_PI_NODE) {
                $document->removeChild($node);
            }
        }

        return $document;
    }
}
