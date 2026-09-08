<?php

namespace App\Http\Requests\Kunjungan;

use Illuminate\Foundation\Http\FormRequest;

class UploadVisitReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('uploadReport', $this->route('visit')) ?? false;
    }

    public function rules(): array
    {
        return ['report' => ['required', 'file', 'mimes:pdf', 'mimetypes:application/pdf,application/x-pdf', 'max:10240']];
    }

    public function messages(): array
    {
        return [
            'report.required' => 'Pilih file laporan PDF.',
            'report.mimes' => 'Laporan harus berupa file PDF.',
            'report.mimetypes' => 'Isi file tidak dikenali sebagai PDF yang valid.',
            'report.max' => 'Ukuran laporan maksimal 10 MB.',
        ];
    }
}
