<?php

namespace App\Exports;

use App\Services\SnpButirContent;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;
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
            $column = Coordinate::stringFromColumnIndex($index + 1);
            $sheet->getColumnDimension($column)->setWidth(80);
            $idIndex = array_search('id_butir', $this->selectedFields, true);
            $content = app(SnpButirContent::class);
            $butirs = collect($this->records)->flatMap(fn ($record) => $record->butirSnp)->values();

            for ($index = $butirs->count() - 1; $index >= 0; $index--) {
                $butir = $butirs[$index];
                $row = $index + 2;
                $text = $content->plain($butir->butir_snp);
                $lines = $this->textLines($text);
                $chunks = array_map(fn (array $lines): string => implode("\n", $lines), array_chunk($lines, 18));
                $images = $content->images($butir->butir_snp);
                $extraRows = count($chunks) + count($images) - 1;
                if ($extraRows > 0) {
                    $sheet->insertNewRowBefore($row + 1, $extraRows);
                }
                foreach ($chunks as $offset => $chunk) {
                    $sheet->setCellValueExplicit($column.($row + $offset), $chunk, DataType::TYPE_STRING);
                    $sheet->getRowDimension($row + $offset)->setRowHeight(max(45, (substr_count($chunk, "\n") + 2) * 15));
                }
                foreach ($images as $imageIndex => $image) {
                    $imageRow = $row + count($chunks) + $imageIndex;
                    $sheet->setCellValueExplicit($column.$imageRow, 'Gambar '.($imageIndex + 1).' — '.$butir->id_butir_snp, DataType::TYPE_STRING);
                    if (! Storage::disk('local')->exists($image['path'])) {
                        $sheet->setCellValueExplicit($column.$imageRow, '[Gambar tidak tersedia] '.$butir->id_butir_snp, DataType::TYPE_STRING);

                        continue;
                    }
                    $drawing = new Drawing;
                    $drawing->setName('Gambar '.$butir->id_butir_snp.' '.($imageIndex + 1));
                    $drawing->setPath(Storage::disk('local')->path($image['path']));
                    $scale = min((520 * $image['width'] / 100) / $drawing->getWidth(), 280 / $drawing->getHeight());
                    $drawing->setWidth((int) round($drawing->getWidth() * $scale));
                    $drawing->setCoordinates($column.$imageRow);
                    $drawing->setOffsetX(8)->setOffsetY(25);
                    $drawing->setWorksheet($sheet);
                    $sheet->getRowDimension($imageRow)->setRowHeight(($drawing->getHeight() + 45) * 0.75);
                }
                if ($idIndex !== false) {
                    $idColumn = Coordinate::stringFromColumnIndex($idIndex + 1);
                    for ($offset = 1; $offset <= $extraRows; $offset++) {
                        $sheet->setCellValueExplicit($idColumn.($row + $offset), $butir->id_butir_snp, DataType::TYPE_STRING);
                    }
                }
            }
            $sheet->getStyle($sheet->calculateWorksheetDimension())->getAlignment()->setWrapText(true)->setVertical('top');
        }];
    }

    /** @return list<string> */
    private function textLines(string $text): array
    {
        $lines = [];
        foreach (preg_split('/\r\n|\r|\n/', $text) as $paragraph) {
            while (mb_strlen($paragraph) > 80) {
                $boundary = mb_strrpos(mb_substr($paragraph, 0, 80), ' ');
                $length = $boundary === false || $boundary === 0 ? 80 : $boundary + 1;
                $lines[] = mb_substr($paragraph, 0, $length);
                $paragraph = mb_substr($paragraph, $length);
            }
            $lines[] = $paragraph;
        }

        return $lines;
    }
}
