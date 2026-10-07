<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SnpButirContent
{
    public const PREFIX = '<!--snp-rich:v1-->';

    public static function isRich(?string $value): bool
    {
        return str_starts_with($value ?? '', self::PREFIX);
    }

    public static function contentField(string $module): string
    {
        return match ($module) {
            'snp' => 'butir_snp',
            'ragab' => 'keputusan_ragab',
            'rawas' => 'keputusan_rawas',
            'djsn' => 'butir_djsn',
            'eksternal' => 'keputusan_eksternal',
            default => throw new \InvalidArgumentException('Fitur isi butir tidak dikenali.'),
        };
    }

    public function normalize(string $value, int $recordId, string $module = 'snp'): string
    {
        $field = self::contentField($module);
        if (strlen($value) > 500000) {
            throw ValidationException::withMessages([$field => 'Isi butir terlalu panjang (maksimal 500 KB).']);
        }

        if (! self::isRich($value)) {
            return $value;
        }

        $html = $this->html($value);
        $images = $this->images(self::PREFIX.$html);
        if (count($images) > 20) {
            throw ValidationException::withMessages([$field => 'Maksimal 20 gambar dalam satu butir.']);
        }
        foreach ($images as $image) {
            if ($image['module'] !== $module || $image['record_id'] !== $recordId || ! Storage::disk('local')->exists($image['path'])) {
                throw ValidationException::withMessages([$field => 'Gambar tidak tersedia atau berasal dari surat lain. Unggah ulang gambar pada surat ini.']);
            }
        }
        if (preg_replace('/[\s\x{00a0}\x{200b}]+/u', '', $this->plain(self::PREFIX.$html)) === '' && $images === []) {
            throw ValidationException::withMessages([$field => 'Isi butir wajib diisi.']);
        }

        return self::PREFIX.$html;
    }

    public function html(?string $value): string
    {
        if (! self::isRich($value)) {
            return '<p>'.nl2br(e($value ?? ''), false).'</p>';
        }

        $root = $this->document(substr($value, strlen(self::PREFIX)))->documentElement;

        return $this->children($root);
    }

    public function plain(?string $value): string
    {
        if (! self::isRich($value)) {
            return $value ?? '';
        }

        $html = preg_replace('/<br\s*\/?>|<\/(?:p|li|ul|ol)>/i', "\n", $this->html($value));

        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /** @return array{module: string, record_id: int, path: string, url: string}|null */
    public static function imageReference(string $source): ?array
    {
        if (! preg_match('~^/(snp|ragab|rawas|djsn|eksternal)/perekaman/([1-9][0-9]*)/gambar/([a-f0-9-]{36}\.(?:png|jpg))$~D', $source, $matches)) {
            return null;
        }

        return ['module' => $matches[1], 'record_id' => (int) $matches[2], 'path' => $matches[1].'-images/'.$matches[2].'/'.$matches[3], 'url' => $source];
    }

    /** @return list<array{module: string, record_id: int, path: string, url: string, width: int}> */
    public function images(?string $value): array
    {
        $images = [];
        foreach ($this->document($this->html($value))->getElementsByTagName('img') as $image) {
            $reference = self::imageReference($image->getAttribute('src'));
            if ($reference) {
                $images[] = [...$reference, 'width' => (int) $image->getAttribute('data-width')];
            }
        }

        return $images;
    }

    public function pdf(?string $value): string
    {
        $document = $this->document($this->html($value));
        foreach (iterator_to_array($document->getElementsByTagName('img')) as $image) {
            $reference = self::imageReference($image->getAttribute('src'));
            if (! $reference || ! Storage::disk('local')->exists($reference['path'])) {
                $image->parentNode->replaceChild($document->createTextNode('[Gambar tidak tersedia]'), $image);

                continue;
            }
            $bytes = Storage::disk('local')->get($reference['path']);
            $size = getimagesizefromstring($bytes);
            if (! $size) {
                $image->parentNode->replaceChild($document->createTextNode('[Gambar tidak tersedia]'), $image);

                continue;
            }
            $scale = min(1200 * ((int) $image->getAttribute('data-width') / 100) / $size[0], 460 / $size[1]);
            $alignment = $image->parentNode instanceof DOMElement ? $image->parentNode->getAttribute('style') : '';
            $margin = str_contains($alignment, 'text-align:center') ? 'margin-left:auto;margin-right:auto;' : (str_contains($alignment, 'text-align:right') ? 'margin-left:auto;margin-right:0;' : '');
            $image->setAttribute('src', 'data:'.$size['mime'].';base64,'.base64_encode($bytes));
            $image->setAttribute('style', 'display:block;width:'.round($size[0] * $scale).'px;height:'.round($size[1] * $scale).'px;'.$margin);
        }

        return $this->innerHtml($document->documentElement);
    }

    private function document(string $html): DOMDocument
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="utf-8" ?><div>'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return $document;
    }

    private function children(DOMNode $node): string
    {
        $result = '';
        foreach ($node->childNodes as $child) {
            $result .= $this->sanitize($child);
        }

        return $result;
    }

    private function sanitize(DOMNode $node): string
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            return e($node->textContent);
        }
        if (! $node instanceof DOMElement) {
            return '';
        }
        $tag = strtolower($node->tagName);
        if (in_array($tag, ['script', 'style', 'iframe', 'object', 'svg', 'math', 'template'], true)) {
            return '';
        }
        if ($tag === 'img') {
            $reference = self::imageReference($node->getAttribute('src'));
            if (! $reference) {
                return '';
            }
            $width = (int) $node->getAttribute('data-width');
            $width = in_array($width, [25, 50, 75, 100], true) ? $width : 100;

            return '<img src="'.e($reference['url']).'" alt="'.e(mb_substr($node->getAttribute('alt'), 0, 200)).'" data-width="'.$width.'" style="width:'.$width.'%;height:auto;">';
        }
        if ($tag === 'br') {
            return '<br>';
        }
        $tag = ['b' => 'strong', 'i' => 'em', 'div' => 'p'][$tag] ?? $tag;
        if (! in_array($tag, ['p', 'strong', 'em', 'u', 'span', 'ul', 'ol', 'li'], true)) {
            return $this->children($node);
        }
        $styles = [];
        foreach (explode(';', $node->getAttribute('style')) as $declaration) {
            $parts = array_map('trim', explode(':', strtolower($declaration), 2));
            if (count($parts) !== 2) {
                continue;
            }
            [$property, $value] = $parts;
            if ($property === 'font-size' && preg_match('/^(12|14|16|18|20|24)px$/D', $value)) {
                $styles['font-size'] = $value;
            }
            if ($tag === 'p' && $property === 'text-align' && in_array($value, ['left', 'center', 'right', 'justify'], true)) {
                $styles['text-align'] = $value;
            }
        }
        $attributes = '';
        if ($styles) {
            $attributes .= ' style="'.implode(';', array_map(fn (string $key, string $value): string => $key.':'.$value, array_keys($styles), $styles)).'"';
        }
        if ($tag === 'ol' && ctype_digit($node->getAttribute('start'))) {
            $attributes .= ' start="'.min(9999, max(1, (int) $node->getAttribute('start'))).'"';
        }

        return '<'.$tag.$attributes.'>'.$this->children($node).'</'.$tag.'>';
    }

    private function innerHtml(DOMNode $node): string
    {
        $html = '';
        foreach ($node->childNodes as $child) {
            $html .= $node->ownerDocument->saveHTML($child);
        }

        return $html;
    }
}
