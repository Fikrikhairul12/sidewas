<?php

namespace App\Models\Concerns;

use App\Models\SuratPutaran;
use App\Services\SuratPutaranService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

/** @mixin Model */
trait BelongsToSuratPutaran
{
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

    public function save(array $options = []): bool
    {
        if (! $this->getConnection()->getSchemaBuilder()->hasTable('tb_surat_putaran')) {
            return parent::save($options);
        }

        return $this->getConnection()->transaction(function () use ($options): bool {
            (new SuratPutaranService)->prepareForSave($this);

            return parent::save($options);
        });
    }

    public function delete(): ?bool
    {
        if (! $this->exists || ! $this->getConnection()->getSchemaBuilder()->hasTable('tb_surat_putaran')) {
            return parent::delete();
        }

        return $this->getConnection()->transaction(function (): ?bool {
            (new SuratPutaranService)->prepareForSave($this);

            return parent::delete();
        });
    }
}
