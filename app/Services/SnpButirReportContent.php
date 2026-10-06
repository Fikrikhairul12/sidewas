<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use Dompdf\Dompdf;
use Dompdf\FontMetrics;
use Illuminate\Support\Facades\Storage;

class SnpButirReportContent
{
    /** @var list<array<string, mixed>> */
    private array $blocks = [];

    /** @var list<array<string, mixed>> */
    private array $runs = [];

    private string $alignment = 'left';

    private int $indent = 0;

    private string $prefix = '';

    private ?FontMetrics $fonts = null;

    public function __construct(private SnpButirContent $content) {}

    /** @return list<string> */
    public function pdfChunks(?string $value, float $width): array
    {
        $chunks = [];
        $html = '';
        $height = 0;
        foreach ($this->contentBlocks($value) as $block) {
            if ($block['type'] === 'image') {
                if ($html !== '') {
                    $chunks[] = $html;
                    $html = '';
                    $height = 0;
                }
                $image = $this->imageSize($block, $width);
                if ($image === null) {
                    $chunks[] = '<div>[Gambar tidak tersedia]</div>';
                } else {
                    $margin = match ($block['alignment']) {
                        'center' => 'margin-left:auto;margin-right:auto;',
                        'right' => 'margin-left:auto;margin-right:0;',
                        default => '',
                    };
                    foreach ($this->pdfImageParts($block['image']['path'], $image['width']) as $part) {
                        $chunks[] = '<img alt="'.e($block['alt']).'" src="data:'.$part['mime'].';base64,'.base64_encode($part['bytes']).'" style="display:block;width:'.$image['width'].'px;max-width:100%;height:auto;'.$margin.'">';
                    }
                }

                continue;
            }
            foreach ($this->lines($block, max(32, $width - $block['indent'])) as $line) {
                if ($height + $line['height'] > 240 && $html !== '') {
                    $chunks[] = $html;
                    $html = '';
                    $height = 0;
                }
                $html .= '<div class="snp-content-line" style="text-align:'.$block['alignment'].';padding-left:'.$block['indent'].'px;line-height:'.$line['height'].'px;">'.($line['html'] ?: '&nbsp;').'</div>'."\n";
                $height += $line['height'];
            }
            $html .= '<div style="height:4px;"></div>';
            $height += 4;
        }
        if ($html !== '') {
            $chunks[] = $html;
        }

        return $chunks ?: ['<div>-</div>'];
    }

    /** @return list<array{type: string, text?: string, height: float, image?: array<string, mixed>, width?: int}> */
    public function excelChunks(?string $value, float $width): array
    {
        $chunks = [];
        $text = [];
        $height = 0;
        foreach ($this->contentBlocks($value) as $block) {
            if ($block['type'] === 'image') {
                if ($text !== []) {
                    $chunks[] = ['type' => 'text', 'text' => implode("\n", $text), 'height' => $height];
                    $text = [];
                    $height = 0;
                }
                $image = $this->imageSize($block, $width);
                $chunks[] = $image === null
                    ? ['type' => 'text', 'text' => '[Gambar tidak tersedia]', 'height' => 24.0]
                    : ['type' => 'image', 'image' => $block['image'], 'width' => $image['width'], 'height' => (float) $image['height']];

                continue;
            }
            foreach ($this->lines($block, $width, false) as $line) {
                if ($height + $line['height'] > 360 && $text !== []) {
                    $chunks[] = ['type' => 'text', 'text' => implode("\n", $text), 'height' => $height];
                    $text = [];
                    $height = 0;
                }
                $text[] = $line['text'];
                $height += $line['height'];
            }
            $text[] = '';
            $height += 20;
        }
        if ($text !== []) {
            $chunks[] = ['type' => 'text', 'text' => implode("\n", $text), 'height' => $height];
        }

        return $chunks ?: [['type' => 'text', 'text' => '', 'height' => 24.0]];
    }

    /** @return list<array<string, mixed>> */
    private function contentBlocks(?string $value): array
    {
        $this->blocks = $this->runs = [];
        $this->prefix = '';
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="utf-8" ?><div>'.$this->content->html($value).'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $this->walk($document->documentElement, ['bold' => false, 'italic' => false, 'underline' => false, 'size' => 8.0], 'left', 0);
        $this->flush();

        return $this->blocks;
    }

    /** @param array{bold: bool, italic: bool, underline: bool, size: float} $style */
    private function walk(DOMNode $node, array $style, string $alignment, int $indent): void
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            $this->append(preg_replace('/\s+/u', ' ', $node->textContent), $style, $alignment, $indent);

            return;
        }
        if (! $node instanceof DOMElement) {
            return;
        }
        $tag = strtolower($node->tagName);
        $css = $node->getAttribute('style');
        if (preg_match('/font-size:(\d+)px/', $css, $match)) {
            $style['size'] = (float) $match[1];
        }
        if (preg_match('/text-align:(left|center|right|justify)/', $css, $match)) {
            $alignment = $match[1];
        }
        foreach (['strong' => 'bold', 'em' => 'italic', 'u' => 'underline'] as $element => $property) {
            if ($tag === $element) {
                $style[$property] = true;
            }
        }
        if ($tag === 'br') {
            $this->append("\n", $style, $alignment, $indent);

            return;
        }
        if ($tag === 'img') {
            $this->flush();
            $this->blocks[] = ['type' => 'image', 'image' => SnpButirContent::imageReference($node->getAttribute('src')), 'percent' => (int) $node->getAttribute('data-width'), 'alt' => $node->getAttribute('alt'), 'alignment' => $alignment];

            return;
        }
        if (in_array($tag, ['ul', 'ol'], true)) {
            $this->flush();
            $number = max(1, (int) $node->getAttribute('start'));
            foreach ($node->childNodes as $child) {
                if ($child instanceof DOMElement && $child->tagName === 'li') {
                    $this->prefix = $tag === 'ol' ? $number++.'. ' : '• ';
                    $this->walk($child, $style, $alignment, min(36, $indent + 12));
                    $this->prefix = '';
                }
            }

            return;
        }
        if (in_array($tag, ['p', 'li'], true)) {
            $this->flush();
        }
        foreach ($node->childNodes as $child) {
            $this->walk($child, $style, $alignment, $indent);
        }
        if (in_array($tag, ['p', 'li'], true)) {
            $this->flush();
        }
    }

    /** @param array{bold: bool, italic: bool, underline: bool, size: float} $style */
    private function append(string $text, array $style, string $alignment, int $indent): void
    {
        if ($text === '' || ($this->runs === [] && trim($text) === '' && $text !== "\n")) {
            return;
        }
        if ($this->runs === []) {
            $this->alignment = $alignment;
            $this->indent = $indent;
        }
        if ($this->prefix !== '') {
            $text = $this->prefix.$text;
            $this->prefix = '';
        }
        $this->runs[] = ['text' => $text, ...$style];
    }

    private function flush(): void
    {
        if ($this->runs !== []) {
            $this->blocks[] = ['type' => 'text', 'runs' => $this->runs, 'alignment' => $this->alignment, 'indent' => $this->indent];
            $this->runs = [];
        }
    }

    /**
     * @param  array<string, mixed>  $block
     * @return list<array{html: string, text: string, height: float}>
     */
    private function lines(array $block, float $width, bool $rich = true): array
    {
        $this->fonts ??= (new Dompdf)->getFontMetrics();
        $lines = [];
        $line = ['html' => '', 'text' => '', 'height' => $rich ? 10.8 : 20.0];
        $used = 0;
        foreach ($block['runs'] as $run) {
            if (! $rich) {
                $run = array_replace($run, ['bold' => false, 'italic' => false, 'underline' => false, 'size' => 14.667]);
            }
            $font = $this->fonts->getFont('Helvetica', $run['bold'] ? ($run['italic'] ? 'bold_italic' : 'bold') : ($run['italic'] ? 'italic' : 'normal'));
            $measure = fn (string $text): float => $this->fonts->getTextWidth($text, $font, $run['size'] * 0.75) / 0.75;
            foreach (preg_split('/(\n|[^\S\n]+)/u', $run['text'], -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) as $token) {
                if ($token === "\n" || ($used + $measure($token) > $width && $line['text'] !== '')) {
                    $lines[] = $line;
                    $line = ['html' => '', 'text' => '', 'height' => $rich ? 10.8 : 20.0];
                    $used = 0;
                }
                if ($token === "\n" || (trim($token) === '' && $used === 0)) {
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
                    $line['text'] .= $part;
                    $line['html'] .= '<span style="font-size:'.$run['size'].'px;font-weight:'.($run['bold'] ? 'bold' : 'normal').';font-style:'.($run['italic'] ? 'italic' : 'normal').';text-decoration:'.($run['underline'] ? 'underline' : 'none').';">'.e($part).'</span>';
                    $line['height'] = max($line['height'], $run['size'] * 1.35);
                    $used += $measure($part);
                    if ($token !== '') {
                        $lines[] = $line;
                        $line = ['html' => '', 'text' => '', 'height' => $rich ? 10.8 : 20.0];
                        $used = 0;
                    }
                }
            }
        }
        if ($line['text'] !== '' || $lines === []) {
            $lines[] = $line;
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $block
     * @return array{width: int, height: int, mime: string}|null
     */
    private function imageSize(array $block, float $width): ?array
    {
        if (! $block['image'] || ! Storage::disk('local')->exists($block['image']['path'])) {
            return null;
        }
        $size = getimagesizefromstring(Storage::disk('local')->get($block['image']['path']));
        if (! $size) {
            return null;
        }
        $scale = min(1, ($width * $block['percent'] / 100) / $size[0]);

        return ['width' => max(1, (int) floor($size[0] * $scale)), 'height' => max(1, (int) floor($size[1] * $scale)), 'mime' => $size['mime']];
    }

    /** @return list<array{mime: string, bytes: string}> */
    private function pdfImageParts(string $path, int $width): array
    {
        $bytes = Storage::disk('local')->get($path);
        $size = getimagesizefromstring($bytes);
        $partHeight = max(1, (int) floor(560 * $size[0] / $width));
        if ($size[1] <= $partHeight) {
            return [['mime' => $size['mime'], 'bytes' => $bytes]];
        }
        $source = imagecreatefromstring($bytes);
        $parts = [];
        try {
            for ($top = 0; $top < $size[1]; $top += $partHeight) {
                $part = imagecrop($source, ['x' => 0, 'y' => $top, 'width' => $size[0], 'height' => min($partHeight, $size[1] - $top)]);
                imagesavealpha($part, true);
                ob_start();
                imagepng($part);
                $parts[] = ['mime' => 'image/png', 'bytes' => ob_get_clean()];
                imagedestroy($part);
            }
        } finally {
            imagedestroy($source);
        }

        return $parts;
    }
}
