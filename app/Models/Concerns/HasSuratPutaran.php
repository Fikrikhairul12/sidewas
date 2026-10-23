<?php

namespace App\Models\Concerns;

use App\Enums\SuratStatus;
use App\Enums\TahapProses;
use App\Models\SuratPutaran;
use App\Models\SuratTahap;
use App\Services\IsianUnitTahapService;
use App\Services\SuratAwalStatusService;
use App\Services\SuratTanggalService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use LogicException;

/** @mixin Model */
trait HasSuratPutaran
{
    public function tahapanProses(): HasMany
    {
        return $this->hasMany(SuratTahap::class, 'record_id')->orderBy('id');
    }

    public function tahapAktif(): BelongsTo
    {
        return $this->belongsTo(SuratTahap::class, 'tahap_aktif_id');
    }

    public function save(array $options = []): bool
    {
        if ($this->getAttribute('tahap_aktif_id') !== null
            && (! $this->exists || ! $this->tahapAktif()->where('record_id', $this->getKey())->exists())) {
            throw new InvalidArgumentException('Tahap aktif harus berasal dari surat yang sama.');
        }
        if (! $this->exists || ! $this->getConnection()->getSchemaBuilder()->hasColumn($this->getTable(), 'closed_at')) {
            return parent::save($options);
        }

        return $this->getConnection()->transaction(function () use ($options): bool {
            $stored = $this->newQuery()->lockForUpdate()->findOrFail($this->getKey());
            if ($stored->closed_at !== null) {
                foreach (['status', 'putaran_aktif_id', 'tahap_aktif_id', 'closed_at', 'closed_by', 'closed_review_id', 'closed_putaran_id'] as $column) {
                    if ($this->isDirty($column) && (string) $stored->getRawOriginal($column) !== (string) ($this->getAttributes()[$column] ?? null)) {
                        throw new LogicException('Keputusan dan identitas penutupan surat tidak boleh diubah.');
                    }
                }
            }

            return parent::save($options);
        });
    }

    public function delete(): ?bool
    {
        return $this->getConnection()->transaction(function (): ?bool {
            $stored = $this->newQuery()->lockForUpdate()->find($this->getKey());
            if ($stored?->getAttribute('closed_at') !== null) {
                throw new LogicException('Surat yang sudah ditutup harus tetap tersimpan sebagai riwayat.');
            }
            $stored?->assertCanDeleteWorkflow();

            return parent::delete();
        });
    }

    public function isWorkflowClosed(): bool
    {
        return $this->getAttribute('closed_at') !== null || ($this->statusSurat()?->isClosed() ?? false);
    }

    public function assertCanDeleteWorkflow(): void
    {
        $db = $this->getConnection();
        if (! $db->getSchemaBuilder()->hasColumn('tb_kompilasi', 'salinan_konteks')) {
            return;
        }
        $module = SuratTahap::moduleFor($this->getConnectionName());
        $butirs = $db->table('tb_butir_'.$module)->where('id_'.$module, $this->{'id_'.$module})->select('id_butir_'.$module);
        abort_if($db->table('tb_kompilasi')->whereIn('id_butir_'.$module, $butirs)->whereNotNull('salinan_konteks')->lockForUpdate()->first(['id']) !== null,
            422, 'Surat yang sudah memiliki salinan kompilasi resmi harus tetap tersimpan sebagai riwayat.');
    }

    public function lockForWorkflowMutation(): static
    {
        $record = $this->newQuery()->lockForUpdate()->findOrFail($this->getKey());
        abort_if($record->closed_at !== null, 422, 'Surat sudah Close. Perekaman dan penugasan unit harus tetap tersimpan sebagai bahan riwayat.');

        return $record;
    }

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
        if (IsianUnitTahapService::enabled()) {
            $stage = $this->tahapanProses->firstWhere('tahap_proses', TahapProses::TanggapanAwal);

            return app(SuratTanggalService::class)->deadline($stage);
        }
        if ((bool) $this->getAttribute('pernah_terbit') || ! in_array($this->status, ['draft', 'belum_ditindaklanjuti'], true)) {
            return null;
        }

        return (new SuratAwalStatusService)->batasAwal($this->tanggal_surat);
    }
}
