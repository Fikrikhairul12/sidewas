<?php

namespace App\Http\Requests\Kunjungan;

use Illuminate\Foundation\Http\FormRequest;

class RejectVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('approve', $this->route('visit')) ?? false;
    }

    public function rules(): array
    {
        return ['notes' => ['required', 'string', 'min:5', 'max:2000']];
    }

    public function messages(): array
    {
        return [
            'notes.required' => 'Alasan penolakan wajib diisi.',
            'notes.min' => 'Alasan penolakan minimal 5 karakter.',
        ];
    }
}
