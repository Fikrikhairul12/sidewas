<?php

namespace App\Http\Requests\Kunjungan;

use Illuminate\Foundation\Http\FormRequest;

class RejectVisitReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reviewReport', [
            $this->route('visit'),
            $this->route('report'),
        ]) ?? false;
    }

    public function rules(): array
    {
        return ['notes' => ['required', 'string', 'min:5', 'max:2000']];
    }

    public function messages(): array
    {
        return [
            'notes.required' => 'Alasan penolakan laporan wajib diisi.',
            'notes.min' => 'Alasan penolakan laporan minimal 5 karakter.',
        ];
    }
}
