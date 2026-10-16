<?php

namespace App\Services;

use DOMElement;
use DOMNode;
use Dompdf\Dompdf;
use Dompdf\FontMetrics;

class ReportPdfCellContent
{
    /** @var list<array{text: string, wrappers: list<DOMElement>, size: float, bold: bool, italic: bool}> */
    private array $runs = [];

    /** @var list<array{html: string, block: int|string, line: bool, keepNext?: bool}> */
    private array $units = [];

    private int $block = 0;

    private ?FontMetrics $fonts = null;

    /** @return list<array{html: string, block: int|string, line: bool, keepNext?: bool}> */
    public function units(DOMElement $cell, float $width): array
    {
        $this->runs = $this->units = [];
        $this->block = 0;

        if ($cell->hasAttribute('data-report-content') || $cell->hasAttribute('data-snp-butir-content')) {
            foreach ($cell->childNodes as $child) {
                if (! $child instanceof DOMElement || $child->hasAttribute('data-snp-butir-label')) {
                    continue;
                }
                if (str_contains($child->getAttribute('class'), 'snp-content-line')) {
                    $this->units[] = ['html' => $cell->ownerDocument->saveHTML($child)."\n", 'block' => 'rich-'.$child->getAttribute('data-report-block'), 'line' => true, 'keepNext' => $child->hasAttribute('data-report-keep-next')];
                } elseif ($child->tagName === 'img') {
                    $this->units[] = ['html' => $cell->ownerDocument->saveHTML($child), 'block' => 'image-'.$this->block++, 'line' => false];
                } elseif ($this->units !== [] && preg_match('/height:\s*4px/', $child->getAttribute('style'))) {
                    $this->units[array_key_last($this->units)]['html'] .= $cell->ownerDocument->saveHTML($child);
                } else {
                    $this->units[] = ['html' => $cell->ownerDocument->saveHTML($child), 'block' => 'other-'.$this->block++, 'line' => false];
                }
            }

            return $this->units;
        }

        $this->walk($cell, [], ['size' => 8.0, 'bold' => false, 'italic' => false], str_contains($cell->getAttribute('class'), 'pre-line'), $width);
        $this->flush($width);

        return $this->units;
    }

    /**
     * @param  list<DOMElement>  $wrappers
     * @param  array{size: float, bold: bool, italic: bool}  $style
     */
    private function walk(DOMNode $node, array $wrappers, array $style, bool $preLine, float $width): void
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            $text = $preLine ? preg_replace('/[\t ]*\r?\n[\t ]*/u', "\n", $node->textContent) : preg_replace('/\s+/u', ' ', $node->textContent);
            foreach (explode("\n", $text) as $index => $part) {
                if ($index > 0) {
                    $this->flush($width);
                }
                $this->runs[] = ['text' => $part, 'wrappers' => $wrappers, ...$style];
            }

            return;
        }
        if (! $node instanceof DOMElement || $node->hasAttribute('data-snp-butir-label')) {
            return;
        }
        $tag = strtolower($node->tagName);
        if ($tag === 'br') {
            $this->flush($width);

            return;
        }
        if (str_contains($node->getAttribute('class'), 'status-badge')) {
            $this->flush($width);
            $this->units[] = ['html' => $node->ownerDocument->saveHTML($node), 'block' => $this->block++, 'line' => false];

            return;
        }
        $isBlock = in_array($tag, ['p', 'div', 'li'], true) || str_contains($node->getAttribute('class'), 'entry-title');
        $preLine = $preLine || str_contains($node->getAttribute('class'), 'entry-block');
        if ($isBlock) {
            $this->flush($width);
        }
        if (in_array($tag, ['a', 'span', 'strong', 'b', 'em', 'i', 'u'], true)) {
            $wrappers[] = $node;
            $style['bold'] = $style['bold'] || in_array($tag, ['strong', 'b'], true) || str_contains($node->getAttribute('class'), 'entry-title');
            $style['italic'] = $style['italic'] || in_array($tag, ['em', 'i'], true);
            if (preg_match('/font-size:\s*([\d.]+)px/', $node->getAttribute('style'), $match)) {
                $style['size'] = (float) $match[1];
            }
        }
        foreach ($node->childNodes as $child) {
            $this->walk($child, $wrappers, $style, $preLine, $width);
        }
        if ($isBlock) {
            $this->flush($width);
            if (str_contains($node->getAttribute('class'), 'entry-block') && $node->nextElementSibling instanceof DOMElement && $this->units !== []) {
                $this->units[array_key_last($this->units)]['html'] .= '<div style="height:4px;border-bottom:1px solid #999;margin-bottom:4px;"></div>';
            }
        }
    }

    private function flush(float $width): void
    {
        if (trim(implode('', array_column($this->runs, 'text'))) === '') {
            $this->runs = [];

            return;
        }
        $this->fonts ??= (new Dompdf)->getFontMetrics();
        $html = '';
        $used = 0.0;
        $height = 10.8;
        $block = $this->block++;
        foreach ($this->runs as $run) {
            $font = $this->fonts->getFont('Helvetica', $run['bold'] ? ($run['italic'] ? 'bold_italic' : 'bold') : ($run['italic'] ? 'italic' : 'normal'));
            $measure = fn (string $text): float => $this->fonts->getTextWidth($text, $font, $run['size'] * 0.75) / 0.75;
            foreach (preg_split('/(\s+)/u', $run['text'], -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) as $token) {
                if ($used + $measure($token) > $width && $html !== '') {
                    $this->units[] = $this->line($html, $height, $block);
                    $html = '';
                    $used = 0.0;
                    $height = 10.8;
                }
                if ($used === 0.0 && trim($token) === '') {
                    continue;
                }
                while ($token !== '') {
                    $length = mb_strlen($token);
                    if ($measure($token) > $width - $used) {
                        $low = 1;
                        $high = $length;
                        while ($low < $high) {
                            $middle = (int) ceil(($low + $high) / 2);
                            if ($measure(mb_substr($token, 0, $middle)) <= $width - $used) {
                                $low = $middle;
                            } else {
                                $high = $middle - 1;
                            }
                        }
                        $length = $low;
                    }
                    $part = mb_substr($token, 0, $length);
                    $token = mb_substr($token, $length);
                    $fragment = e($part);
                    foreach (array_reverse($run['wrappers']) as $wrapper) {
                        $copy = $wrapper->cloneNode(false);
                        $copy->appendChild($copy->ownerDocument->createTextNode($part));
                        $outer = $copy->ownerDocument->saveHTML($copy);
                        $start = strpos($outer, '>') + 1;
                        $fragment = substr($outer, 0, $start).$fragment.substr($outer, strrpos($outer, '</'));
                    }
                    $html .= $fragment;
                    $used += $measure($part);
                    $height = max($height, $run['size'] * 1.35);
                    if ($token !== '') {
                        $this->units[] = $this->line($html, $height, $block);
                        $html = '';
                        $used = 0.0;
                        $height = 10.8;
                    }
                }
            }
        }
        if ($html !== '') {
            $this->units[] = $this->line($html, $height, $block);
        }
        $text = trim(implode('', array_column($this->runs, 'text')));
        if (mb_strlen($text) <= 120 && (str_ends_with($text, ':') || ! array_filter($this->runs, fn (array $run): bool => trim($run['text']) !== '' && ! $run['bold']))) {
            foreach ($this->units as &$unit) {
                if ($unit['block'] === $block) {
                    $unit['keepNext'] = true;
                }
            }
            unset($unit);
        }
        $this->runs = [];
    }

    /** @return array{html: string, block: int, line: bool} */
    private function line(string $html, float $height, int $block): array
    {
        return ['html' => '<div class="report-cell-line" style="white-space:normal;line-height:'.$height.'px;">'.$html.'</div>', 'block' => $block, 'line' => true];
    }
}
