<?php

namespace App\Models\Concerns;

use App\Enums\SuratStatus;
use App\Models\SuratPutaran;
use App\Services\SuratAwalStatusService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/** @mixin Model */
trait HasSuratPutaran
{
    public function putaran(): HasMany
    {
        return $this->hasMany(SuratPutaran::class, 'record_id')->orderBy('nomor_putaran');
    }

    public function putaranAktif(): BelongsTo
    {
        return $this->belongsTo(SuratPutaran::class, 'putaran_aktif_id');
    }

    public function usesPutaranWorkflow(): bool
    {
        return $this->getAttribute('putaran_aktif_id') !== null;
    }

    public function statusSurat(): ?SuratStatus
    {
        return SuratStatus::tryFrom((string) $this->status);
    }

    public function batasStatusAwal(): ?Carbon
    {
        if ((bool) $this->getAttribute('pernah_terbit') || ! in_array($this->status, ['draft', 'belum_ditindaklanjuti'], true)) {
            return null;
        }

        return (new SuratAwalStatusService)->batasAwal($this->tanggal_surat);
    }
}
