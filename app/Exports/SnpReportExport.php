<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

class SnpReportExport implements FromView
{
    protected $records;
    protected array $selectedFields;
    protected array $fieldLabels;

    public function __construct($records, array $selectedFields, array $fieldLabels)
    {
        $this->records = $records;
        $this->selectedFields = $selectedFields;
        $this->fieldLabels = $fieldLabels;
    }

    public function view(): View
    {
        return view('layouts.snp.report.excel', [
            'records' => $this->records,
            'selectedFields' => $this->selectedFields,
            'fieldLabels' => $this->fieldLabels,
        ]);
    }
}
