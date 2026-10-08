<?php

namespace App\Exports;

use App\Models\ProdukHukum;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

class ProdukHukumReportExport extends StringValueBinder implements FromArray, WithCustomValueBinder, WithEvents
{
    /**
     * @param  Collection<int, ProdukHukum>  $products
     */
    public function __construct(
        private Collection $products,
        private string $scope,
        private string $printedBy,
        private string $printedAt,
    ) {}

    public function bindValue(Cell $cell, mixed $value): bool
    {
        if ($cell->getRow() >= 7 && in_array($cell->getColumn(), ['A', 'E'], true) && is_numeric($value)) {
            $cell->setValueExplicit((int) $value, DataType::TYPE_NUMERIC);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    /**
     * @return array<int, array<int, int|string>>
     */
    public function array(): array
    {
        $rows = [
            ['REKAP PRODUK HUKUM'],
            ['SIDEWAS PRODUK HUKUM DEWAS'],
            [$this->scope.' | Total: '.$this->products->count().' peraturan'],
            ['Dicetak oleh '.$this->printedBy.' pada '.$this->printedAt],
            [''],
            ['No.', 'Kode Produk Hukum', 'Judul', 'Nomor Peraturan', 'Tahun', 'Jenis Peraturan', 'Bidang Pengaturan', 'Sifat Dokumen', 'Status'],
        ];
        foreach ($this->products as $index => $product) {
            $rows[] = [
                $index + 1,
                $product->kode_produk_hukum,
                $product->judul,
                $product->nomor_peraturan_keputusan ?? '-',
                $product->tahun_peraturan ?? '-',
                $product->jenis_bentuk_peraturan ?? '-',
                $product->bidang_pengaturan ?? '-',
                ucfirst($product->sifat_dokumen),
                match ($product->status_peraturan) {
                    'berlaku' => 'Berlaku',
                    'tidak_berlaku' => 'Tidak Berlaku',
                    default => 'Draf',
                },
            ];
        }

        return $rows;
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event): void {
            $sheet = $event->sheet->getDelegate();
            $lastRow = $sheet->getHighestRow();
            $sheet->setTitle('Rekap Produk Hukum');
            $sheet->setShowGridlines(false);
            $sheet->freezePane('D7');
            $sheet->setAutoFilter('A6:I'.$lastRow);
            $sheet->getStyle('A1:I'.$lastRow)->getFont()->setName('Calibri')->setSize(11);
            $sheet->getStyle('A1:I'.$lastRow)->getAlignment()->setVertical('top')->setWrapText(true);
            foreach (range(1, 4) as $row) {
                $sheet->mergeCells('A'.$row.':I'.$row);
                $sheet->getRowDimension($row)->setRowHeight($row === 1 ? 30 : 23);
            }
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(18)->getColor()->setRGB('2377B9');
            $sheet->getStyle('A2:A4')->getFont()->getColor()->setRGB('475569');
            $sheet->getRowDimension(5)->setRowHeight(12);
            $sheet->getRowDimension(6)->setRowHeight(32);
            $sheet->getStyle('A6:I6')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
            $sheet->getStyle('A6:I6')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('2377B9');
            $sheet->getStyle('A6:I'.$lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('DCE5ED');
            foreach (['A' => 7, 'B' => 23, 'C' => 75, 'D' => 28, 'E' => 10, 'F' => 30, 'G' => 30, 'H' => 18, 'I' => 20] as $column => $width) {
                $sheet->getColumnDimension($column)->setWidth($width);
            }
            for ($row = 7; $row <= $lastRow; $row++) {
                $lines = 1;
                foreach (['B' => 21, 'C' => 68, 'D' => 25, 'F' => 27, 'G' => 27, 'H' => 16, 'I' => 18] as $column => $characters) {
                    $cellLines = 0;
                    foreach (explode("\n", (string) $sheet->getCell($column.$row)->getValue()) as $line) {
                        $cellLines += max(1, (int) ceil(mb_strwidth($line) / $characters));
                    }
                    $lines = max($lines, $cellLines);
                }
                $sheet->getRowDimension($row)->setRowHeight(min(409, $lines * 16 + 12));
                if ($row % 2 === 0) {
                    $sheet->getStyle('A'.$row.':I'.$row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F1F5F9');
                }
            }
            $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
                ->setPaperSize(PageSetup::PAPERSIZE_A4)->setFitToWidth(1)->setFitToHeight(0)
                ->setRowsToRepeatAtTopByStartAndEnd(6, 6)->setPrintArea('A1:I'.$lastRow);
            $sheet->getHeaderFooter()->setOddFooter('&LSIDEWAS PRODUK HUKUM DEWAS&RHalaman &P / &N');
        }];
    }
}
