<?php

namespace App\Http\Requests\Kunjungan;

use Illuminate\Foundation\Http\FormRequest;

class StoreVisitRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->has('destination_unit_ids') && $this->filled('destination_unit_id')) {
            $this->merge(['destination_unit_ids' => [$this->input('destination_unit_id')]]);
        }

        $dates = [];
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $this->input('start_at'))) {
            $dates['start_at'] = $this->input('start_at').' 00:00:00';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $this->input('end_at'))) {
            $dates['end_at'] = $this->input('end_at').' 23:59:59';
        }
        if ($dates !== []) {
            $this->merge($dates);
        }
    }

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'letter_number' => ['nullable', 'string', 'max:100'],
            'destination_unit_ids' => ['required', 'array', 'min:1'],
            'destination_unit_ids.*' => ['integer', 'distinct', 'exists:mysql_kunjungan.units,id'],
            'start_at' => ['required', 'date_format:Y-m-d H:i:s'],
            'end_at' => ['required', 'date_format:Y-m-d H:i:s', 'after:start_at'],
            'pic_unit_kerja_id' => ['required', 'integer', 'exists:mysql.tb_unit_kerja,id'],
            'participant_ids' => ['required', 'array', 'min:1'],
            'participant_ids.*' => ['integer', 'distinct', 'exists:mysql_kunjungan.employees,id'],
            'purpose' => ['required', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Nama agenda wajib diisi.',
            'title.max' => 'Nama agenda maksimal 150 karakter.',
            'letter_number.max' => 'Nomor surat maksimal 100 karakter.',
            'destination_unit_ids.required' => 'Pilih minimal satu lokasi tujuan.',
            'destination_unit_ids.min' => 'Pilih minimal satu lokasi tujuan.',
            'destination_unit_ids.*.exists' => 'Salah satu lokasi tujuan tidak valid.',
            'start_at.required' => 'Tanggal mulai wajib diisi.',
            'start_at.date_format' => 'Format tanggal mulai tidak valid.',
            'end_at.required' => 'Tanggal selesai wajib diisi.',
            'end_at.date_format' => 'Format tanggal selesai tidak valid.',
            'end_at.after' => 'Tanggal selesai harus setelah tanggal mulai.',
            'pic_unit_kerja_id.required' => 'PIC unit kerja wajib dipilih.',
            'pic_unit_kerja_id.exists' => 'PIC unit kerja yang dipilih tidak valid.',
            'participant_ids.required' => 'Pilih minimal satu peserta.',
            'participant_ids.min' => 'Pilih minimal satu peserta.',
            'purpose.required' => 'Keperluan kunjungan wajib diisi.',
        ];
    }
}
