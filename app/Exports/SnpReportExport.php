<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

class SnpReportExport implements FromView
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
}
