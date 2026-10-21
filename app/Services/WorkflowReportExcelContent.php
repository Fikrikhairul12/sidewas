<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Shared\Drawing as DrawingDimensions;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class WorkflowReportExcelContent
{
    public function __construct(private SnpButirContent $content, private SnpButirReportContent $report) {}

    /** @param list<array{label: string, content: ?string}> $sections */
    public function combine(array $sections): string
    {
        if (! array_filter($sections, fn (array $section): bool => SnpButirContent::isRich($section['content']))) {
            return implode("\n\n", array_map(fn (array $section): string => $section['label']."\n".(filled($section['content']) ? $section['content'] : '-'), $sections)) ?: '-';
        }
        $html = '';
        foreach ($sections as $section) {
            $html .= '<p><strong>'.e($section['label']).'</strong></p>'.$this->content->html(filled($section['content']) ? $section['content'] : '-');
        }

        return SnpButirContent::PREFIX.$html;
    }

    /** @param list<string> $selectedFields */
    public function apply(Worksheet $sheet, array $selectedFields): void
    {
        $tasks = [];
        foreach ($selectedFields as $index => $field) {
            if (! in_array($field, ['kompilasi_tanggapan', 'kompilasi_tindak_lanjut', 'hasil_reviu'], true)) {
                continue;
            }
            $column = Coordinate::stringFromColumnIndex($index + 1);
            for ($row = 2; $row <= $sheet->getHighestRow(); $row++) {
                $value = (string) $sheet->getCell($column.$row)->getValue();
                if (SnpButirContent::isRich($value)) {
                    $width = $sheet->getColumnDimension($column)->getWidth();
                    $pixels = DrawingDimensions::cellDimensionToPixels($width > 0 ? $width : 28, $sheet->getParent()->getDefaultStyle()->getFont());
                    $tasks[$row][$column] = ['chunks' => $this->report->excelChunks($value, max(32, $pixels - 16)), 'width' => $pixels];
                }
            }
        }
        krsort($tasks);
        foreach ($tasks as $row => $columns) {
            $count = 1;
            $originalHeight = max(18, $sheet->getRowDimension($row)->getRowHeight());
            foreach ($columns as $column => &$data) {
                $data['rows'] = array_map(fn (array $chunk): int => $chunk['type'] === 'image' ? max(1, (int) ceil(($chunk['height'] + 20) / 500)) : 1, $data['chunks']);
                $count = max($count, array_sum($data['rows']));
                if ($range = $sheet->getCell($column.$row)->getMergeRange()) {
                    $sheet->unmergeCells($range);
                }
            }
            unset($data);
            if ($count > 1) {
                $sheet->insertNewRowBefore($row + 1, $count - 1);
                foreach ($selectedFields as $index => $field) {
                    $column = Coordinate::stringFromColumnIndex($index + 1);
                    if (! isset($columns[$column]) && ! $sheet->getCell($column.$row)->getMergeRange()) {
                        $sheet->mergeCells($column.$row.':'.$column.($row + $count - 1));
                    }
                }
            }
            for ($offset = 0; $offset < $count; $offset++) {
                $sheet->getRowDimension($row + $offset)->setRowHeight($offset === 0 ? $originalHeight : 18);
            }
            foreach ($columns as $column => $data) {
                $targetRow = $row;
                foreach ($data['chunks'] as $index => $chunk) {
                    $cell = $column.$targetRow;
                    $value = $chunk['text'] ?? '';
                    if (! empty($chunk['runs'])) {
                        $value = new RichText;
                        foreach ($chunk['runs'] as $run) {
                            $value->createTextRun($run['text'])->getFont()
                                ->setName($sheet->getParent()->getDefaultStyle()->getFont()->getName())
                                ->setSize(round($run['size'] * 0.75, 2))->setBold($run['bold'])->setItalic($run['italic'])
                                ->setUnderline($run['underline'] ? Font::UNDERLINE_SINGLE : Font::UNDERLINE_NONE);
                        }
                    }
                    $sheet->setCellValueExplicit($cell, $value, DataType::TYPE_STRING);
                    $sheet->getStyle($cell)->getAlignment()->setWrapText(true)->setVertical('top')->setHorizontal($chunk['alignment'] ?? 'left')->setIndent((int) (($chunk['indent'] ?? 0) / 12));
                    $height = ($chunk['height'] + 8) * 0.75;
                    if ($chunk['type'] === 'image') {
                        $imageRows = $data['rows'][$index];
                        if ($imageRows > 1) {
                            $sheet->mergeCells($cell.':'.$column.($targetRow + $imageRows - 1));
                        }
                        $drawing = new Drawing;
                        $drawing->setName('Gambar hasil '.$cell.'-'.($index + 1));
                        $drawing->setPath(Storage::disk('local')->path($chunk['image']['path']));
                        $drawing->setWidth($chunk['width']);
                        $drawing->setCoordinates($cell);
                        $offset = match ($chunk['alignment'] ?? 'left') {
                            'center' => (int) round(($data['width'] - $drawing->getWidth()) / 2),
                            'right' => $data['width'] - $drawing->getWidth() - 8,
                            default => 8,
                        };
                        $drawing->setOffsetX(max(8, $offset))->setOffsetY(8)->setEditAs('oneCell')->setWorksheet($sheet);
                        $height = ($drawing->getHeight() + 20) * 0.75 / $imageRows;
                    }
                    for ($part = 0; $part < $data['rows'][$index]; $part++) {
                        $dimension = $sheet->getRowDimension($targetRow + $part);
                        $dimension->setRowHeight(max($dimension->getRowHeight(), $height));
                    }
                    $targetRow += $data['rows'][$index];
                }
            }
        }
    }
}
