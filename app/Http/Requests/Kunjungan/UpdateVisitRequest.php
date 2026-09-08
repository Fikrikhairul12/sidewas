<?php

namespace App\Http\Requests\Kunjungan;

class UpdateVisitRequest extends StoreVisitRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('visit')) ?? false;
    }
}
