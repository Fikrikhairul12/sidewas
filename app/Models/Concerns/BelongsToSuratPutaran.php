<?php

namespace App\Models\Concerns;

use App\Models\SuratPutaran;
use App\Models\SuratTahap;
use App\Services\IsianUnitTahapService;
use App\Services\SuratPutaranService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

/** @mixin Model */
trait BelongsToSuratPutaran
{
    public function tahapProses(): BelongsTo
    {
        return $this->belongsTo(SuratTahap::class, 'tahap_id');
    }

    public function scopeUntukTahap(Builder $query, SuratTahap $tahap): Builder
    {
        if ($this->getConnectionName() !== $tahap->getConnectionName()) {
            throw new InvalidArgumentException('Tahap SNP dan DJSN tidak boleh dicampur.');
        }

        return $query->where($this->qualifyColumn('tahap_id'), $tahap->id);
    }

    public function putaran(): BelongsTo
    {
        return $this->belongsTo(SuratPutaran::class, 'putaran_id');
    }

    public function scopeUntukPutaran(Builder $query, SuratPutaran $putaran): Builder
    {
        if ($this->getConnectionName() !== $putaran->getConnectionName()) {
            throw new InvalidArgumentException('Putaran SNP dan DJSN tidak boleh dicampur.');
        }

        return $query->where($this->qualifyColumn('putaran_id'), $putaran->id);
    }

    public function scopePadaPutaranAktif(Builder $query): Builder
    {
        $table = $this->getTable();

        return $query->whereHas('butir.record', function (Builder $record) use ($table): void {
            $record->where(function (Builder $current) use ($table): void {
                $current->whereColumn('tb_record.putaran_aktif_id', $table.'.putaran_id')
                    ->orWhere(function (Builder $legacy) use ($table): void {
                        $legacy->whereNull('tb_record.putaran_aktif_id')
                            ->where(function (Builder $round) use ($table): void {
                                $round->whereNull($table.'.putaran_id')
                                    ->orWhereExists(function ($header) use ($table): void {
                                        $header->selectRaw('1')->from('tb_surat_putaran')
                                            ->whereColumn('tb_surat_putaran.id', $table.'.putaran_id')
                                            ->whereColumn('tb_surat_putaran.record_id', 'tb_record.id')
                                            ->where('nomor_putaran', 0);
                                    });
                            });
                    });
            });
        });
    }

    public function save(array $options = []): bool
    {
        if (! $this->getConnection()->getSchemaBuilder()->hasTable('tb_surat_putaran')) {
            return parent::save($options);
        }

        return $this->getConnection()->transaction(function () use ($options): bool {
            (new SuratPutaranService)->prepareForSave($this);

            $saved = parent::save($options);
            if ($saved && IsianUnitTahapService::enabled() && IsianUnitTahapService::isInput($this)) {
                app(IsianUnitTahapService::class)->register($this);
            }

            return $saved;
        });
    }

    public function delete(): ?bool
    {
        if (IsianUnitTahapService::enabled() && IsianUnitTahapService::isInput($this)) {
            throw new \LogicException('Riwayat isian unit tidak boleh dihapus. Pengubahan dilakukan melalui Detail.');
        }
        if (! $this->exists || ! $this->getConnection()->getSchemaBuilder()->hasTable('tb_surat_putaran')) {
            return parent::delete();
        }

        return $this->getConnection()->transaction(function (): ?bool {
            (new SuratPutaranService)->prepareForSave($this);

            return parent::delete();
        });
    }
}
