<?php

namespace App\Exports;

use App\Services\SnpButirReportContent;
use Illuminate\Contracts\View\View;
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

class SnpReportExport extends StringValueBinder implements FromView, WithCustomValueBinder, WithEvents
{
    protected $records;

    protected array $selectedFields;

    protected array $fieldLabels;

    protected array $tanggapanUnitKerjaIds;

    protected array $tindakLanjutUnitKerjaIds;

    public function __construct(
        $records,
        array $selectedFields,
        array $fieldLabels,
        array $tanggapanUnitKerjaIds = [],
        array $tindakLanjutUnitKerjaIds = []
    ) {
        $this->records = $records;
        $this->selectedFields = $selectedFields;
        $this->fieldLabels = $fieldLabels;
        $this->tanggapanUnitKerjaIds = $tanggapanUnitKerjaIds;
        $this->tindakLanjutUnitKerjaIds = $tindakLanjutUnitKerjaIds;
    }

    public function view(): View
    {
        return view('layouts.snp.report.excel', [
            'records' => $this->records,
            'selectedFields' => $this->selectedFields,
            'fieldLabels' => $this->fieldLabels,
            'tanggapanUnitKerjaIds' => $this->tanggapanUnitKerjaIds,
            'tindakLanjutUnitKerjaIds' => $this->tindakLanjutUnitKerjaIds,
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
            $index = array_search('isi_butir', $this->selectedFields, true);
            if ($index === false) {
                return;
            }
            $sheet->setShowGridlines(false);
            $column = Coordinate::stringFromColumnIndex($index + 1);
            $sheet->getColumnDimension($column)->setWidth(90);
            $columnWidth = DrawingDimensions::cellDimensionToPixels(90, $sheet->getParent()->getDefaultStyle()->getFont());
            $content = app(SnpButirReportContent::class);
            $butirs = collect($this->records)->flatMap(fn ($record) => $record->butirSnp)->values();
            $rowCounts = [];

            for ($index = $butirs->count() - 1; $index >= 0; $index--) {
                $butir = $butirs[$index];
                $row = $index + 2;
                $chunks = $content->excelChunks($butir->butir_snp, 600);
                $chunkRows = array_map(fn (array $chunk): int => $chunk['type'] === 'image' ? max(1, (int) ceil(($chunk['height'] + 20) / 500)) : 1, $chunks);
                $extraRows = array_sum($chunkRows) - 1;
                $rowCounts[$index] = $extraRows + 1;
                if ($extraRows > 0) {
                    $sheet->insertNewRowBefore($row + 1, $extraRows);
                    foreach ($this->selectedFields as $fieldIndex => $field) {
                        if ($field !== 'isi_butir') {
                            $fieldColumn = Coordinate::stringFromColumnIndex($fieldIndex + 1);
                            $sheet->mergeCells($fieldColumn.$row.':'.$fieldColumn.($row + $extraRows));
                        }
                    }
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
                        $drawing->setName('Gambar '.$butir->id_butir_snp.' '.($offset + 1));
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
