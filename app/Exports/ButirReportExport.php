<?php

namespace App\Exports;

use App\Services\SnpButirContent;
use App\Services\SnpButirReportContent;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Shared\Drawing as DrawingDimensions;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

abstract class ButirReportExport extends StringValueBinder implements FromView, WithCustomValueBinder, WithEvents
{
    protected string $module;

    protected Collection $records;

    protected array $selectedFields;

    protected array $fieldLabels;

    public function __construct(iterable $records, array $selectedFields, array $fieldLabels)
    {
        $this->records = collect($records);
        $this->selectedFields = $selectedFields;
        $this->fieldLabels = $fieldLabels;
    }

    public function view(): View
    {
        return view('layouts.'.$this->module.'.report.excel', [
            'records' => $this->records,
            'selectedFields' => $this->selectedFields,
            'fieldLabels' => $this->fieldLabels,
        ]);
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event): void {
            $sheet = $event->sheet->getDelegate();
            $sheet->freezePane('A2');
            $sheet->getStyle($sheet->calculateWorksheetDimension())->getAlignment()->setWrapText(true)->setVertical('top');
            foreach (array_keys($this->selectedFields) as $index) {
                $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index + 1))->setWidth(28);
            }
            $contentField = $this->module === 'djsn' ? 'isi_butir' : 'keputusan';
            $index = array_search($contentField, $this->selectedFields, true);
            if ($index === false) {
                return;
            }
            $sheet->setShowGridlines(false);
            $column = Coordinate::stringFromColumnIndex($index + 1);
            $sheet->getColumnDimension($column)->setWidth(90);
            $columnWidth = DrawingDimensions::cellDimensionToPixels(90, $sheet->getParent()->getDefaultStyle()->getFont());
            $content = app(SnpButirReportContent::class);
            $butirs = collect($this->records)->flatMap(fn ($record) => $record->{'butir'.ucfirst($this->module)})->values();
            $rowCounts = [];
            $starts = [];
            $baseCounts = [];
            $nextRow = 2;
            foreach ($butirs as $offset => $butir) {
                $starts[$offset] = $nextRow;
                $baseCounts[$offset] = $this->module === 'djsn' ? 1 + $butir->tindakLanjuts->count() : max(1, $butir->tindakLanjuts->count());
                $nextRow += $baseCounts[$offset];
            }
            $constantFields = $this->module === 'djsn'
                ? ['surat', 'id_butir', 'pic_utama', 'pic_pendukung', 'komite']
                : ['surat', 'tgl_agenda', 'id_butir', 'hasil_reviu', 'status'];

            for ($index = $butirs->count() - 1; $index >= 0; $index--) {
                $butir = $butirs[$index];
                $row = $starts[$index];
                $chunks = $content->excelChunks($butir->{SnpButirContent::contentField($this->module)}, 600);
                $chunkRows = array_map(fn (array $chunk): int => $chunk['type'] === 'image' ? max(1, (int) ceil(($chunk['height'] + 20) / 500)) : 1, $chunks);
                $rowCounts[$index] = max($baseCounts[$index], array_sum($chunkRows));
                $extraRows = $rowCounts[$index] - $baseCounts[$index];
                if ($extraRows > 0) {
                    $sheet->insertNewRowBefore($row + $baseCounts[$index], $extraRows);
                }
                foreach ($this->selectedFields as $fieldIndex => $field) {
                    if (in_array($field, $constantFields, true) && $rowCounts[$index] > 1) {
                        $fieldColumn = Coordinate::stringFromColumnIndex($fieldIndex + 1);
                        $sheet->mergeCells($fieldColumn.$row.':'.$fieldColumn.($row + $rowCounts[$index] - 1));
                    }
                }
                for ($clearRow = $row; $clearRow < $row + $rowCounts[$index]; $clearRow++) {
                    $sheet->setCellValueExplicit($column.$clearRow, '', DataType::TYPE_STRING);
                    $sheet->getRowDimension($clearRow)->setRowHeight(30);
                }
                $contentRow = $row;
                foreach ($chunks as $offset => $chunk) {
                    $cell = $column.$contentRow;
                    $value = $chunk['text'] ?? '';
                    if (! empty($chunk['runs'])) {
                        $value = new RichText;
                        foreach ($chunk['runs'] as $run) {
                            $value->createTextRun($run['text'])->getFont()
                                ->setName($sheet->getParent()->getDefaultStyle()->getFont()->getName())
                                ->setSize(round($run['size'] * 0.75, 2))
                                ->setBold($run['bold'])
                                ->setItalic($run['italic'])
                                ->setUnderline($run['underline'] ? Font::UNDERLINE_SINGLE : Font::UNDERLINE_NONE);
                        }
                    }
                    $sheet->setCellValueExplicit($cell, $value, DataType::TYPE_STRING);
                    $sheet->getStyle($cell)->getAlignment()
                        ->setHorizontal($chunk['alignment'] ?? 'left')
                        ->setIndent((int) (($chunk['indent'] ?? 0) / 12));
                    if ($chunk['type'] === 'image') {
                        if ($chunkRows[$offset] > 1) {
                            $sheet->mergeCells($column.$contentRow.':'.$column.($contentRow + $chunkRows[$offset] - 1));
                        }
                        $drawing = new Drawing;
                        $drawing->setName('Gambar '.$butir->{'id_butir_'.$this->module}.' '.($offset + 1));
                        $drawing->setPath(Storage::disk('local')->path($chunk['image']['path']));
                        $drawing->setWidth($chunk['width']);
                        $drawing->setCoordinates($column.$contentRow);
                        $imageOffset = match ($chunk['alignment'] ?? 'left') {
                            'center' => (int) round(($columnWidth - $drawing->getWidth()) / 2),
                            'right' => $columnWidth - $drawing->getWidth() - 8,
                            default => 8,
                        };
                        $drawing->setOffsetX(max(8, $imageOffset))->setOffsetY(8);
                        $drawing->setEditAs('oneCell');
                        $drawing->setWorksheet($sheet);
                        for ($imageRow = $contentRow; $imageRow < $contentRow + $chunkRows[$offset]; $imageRow++) {
                            $sheet->getRowDimension($imageRow)->setRowHeight(($drawing->getHeight() + 20) * 0.75 / $chunkRows[$offset]);
                        }
                    } else {
                        $sheet->getRowDimension($contentRow)->setRowHeight(max(18, ($chunk['height'] + 8) * 0.75));
                    }
                    $contentRow += $chunkRows[$offset];
                }
            }
            $sheet->getStyle($sheet->calculateWorksheetDimension())->getAlignment()->setWrapText(true)->setVertical('top');
            $sheet->getStyle($sheet->calculateWorksheetDimension())->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            for ($row = 2; $row <= $sheet->getHighestRow(); $row++) {
                $height = $sheet->getRowDimension($row)->getRowHeight();
                foreach ($this->selectedFields as $fieldIndex => $field) {
                    if ($field === $contentField) {
                        continue;
                    }
                    $cell = $sheet->getCell(Coordinate::stringFromColumnIndex($fieldIndex + 1).$row);
                    $value = (string) $cell->getValue();
                    if ($value === '') {
                        continue;
                    }
                    $lines = array_sum(array_map(fn (string $line): int => max(1, (int) ceil(mb_strlen($line) / 26)), explode("\n", $value)));
                    $requiredHeight = $lines * 15 + 6;
                    $range = $cell->getMergeRange();
                    if ($range) {
                        [$start, $end] = Coordinate::rangeBoundaries($range);
                        $availableHeight = 0;
                        for ($mergedRow = $start[1]; $mergedRow <= $end[1]; $mergedRow++) {
                            $availableHeight += max(0, $sheet->getRowDimension($mergedRow)->getRowHeight());
                        }
                        $requiredHeight = $height + max(0, $requiredHeight - $availableHeight);
                    }
                    $height = max($height, min(409, $requiredHeight));
                }
                $sheet->getRowDimension($row)->setRowHeight($height);
            }
            $firstRow = 2;
            $lastColumn = Coordinate::stringFromColumnIndex(count($this->selectedFields));
            foreach ($butirs as $index => $butir) {
                $lastRow = $firstRow + $rowCounts[$index] - 1;
                if ($lastRow > $firstRow) {
                    $sheet->getStyle('A'.$firstRow.':'.$lastColumn.$lastRow)->applyFromArray([
                        'borders' => ['horizontal' => ['borderStyle' => Border::BORDER_NONE]],
                    ]);
                }
                $firstRow = $lastRow + 1;
            }
        }];
    }
}
