<?php

namespace App\Services;

use App\Models\DjsnRecord;
use App\Models\SnpRecord;
use App\Models\SuratPutaran;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

class SuratPutaranService
{
    /**
     * Infrastructure only. Workflow controllers will call this when the new flow is activated.
     */
    public function buka(SnpRecord|DjsnRecord $surat, ?int $actorId = null): SuratPutaran
    {
        $connection = $surat->getConnectionName();

        return DB::connection($connection)->transaction(function () use ($surat, $connection, $actorId): SuratPutaran {
            $record = $surat->newQuery()->lockForUpdate()->findOrFail($surat->getKey());
            $active = $record->putaranAktif;

            if ($active !== null && $active->archived_at === null) {
                throw new LogicException('Putaran aktif harus diarsipkan sebelum membuka putaran berikutnya.');
            }

            $legacy = $this->legacyFor($connection, $record->getRawOriginal());

            if ($legacy->archived_at === null) {
                $legacy->update(['archived_at' => now(), 'archived_by' => $actorId]);
            }

            $number = ((int) $record->putaran()->max('nomor_putaran')) + 1;
            $putaran = $record->putaran()->create([
                'nomor_putaran' => $number,
                'opened_at' => now(),
                'created_by' => $actorId,
            ]);
            $record->forceFill(['putaran_aktif_id' => $putaran->id])->save();
            $surat->refresh();

            return $putaran;
        });
    }

    public function arsipkan(SuratPutaran $putaran, ?int $actorId = null): SuratPutaran
    {
        return $putaran->getConnection()->transaction(function () use ($putaran, $actorId): SuratPutaran {
            $record = $putaran->surat()->lockForUpdate()->firstOrFail();
            $current = $putaran->fresh();

            if ($current->isLegacy() || (int) $record->putaran_aktif_id !== (int) $current->id || $current->archived_at !== null) {
                throw new LogicException('Hanya putaran aktif yang dapat diarsipkan.');
            }

            $current->update(['archived_at' => now(), 'archived_by' => $actorId]);

            return $current;
        });
    }

    /** @param array<string, mixed> $record */
    public function legacyFor(string $connection, array $record): SuratPutaran
    {
        $attributes = (new SuratPutaranBackfill)->legacyAttributes($record);
        DB::connection($connection)->table('tb_surat_putaran')->insertOrIgnore($attributes);

        return SuratPutaran::on($connection)->where('record_id', $record['id'])->where('nomor_putaran', 0)->lockForUpdate()->firstOrFail();
    }

    public function prepareForSave(Model $model): void
    {
        $connection = $model->getConnectionName();
        $module = match ($connection) {
            'mysql_snp' => 'snp',
            'mysql_djsn' => 'djsn',
            default => throw new InvalidArgumentException('Model putaran harus memakai koneksi SNP atau DJSN.'),
        };
        $db = $model->getConnection();
        $butirColumn = 'id_butir_'.$module;
        $butir = $db->table('tb_butir_'.$module)->where($butirColumn, $model->getAttribute($butirColumn))->first();

        if ($butir === null) {
            throw new InvalidArgumentException('Butir pemilik riwayat tidak ditemukan.');
        }

        $recordColumn = 'id_'.$module;
        $record = $db->table('tb_record')->where($recordColumn, $butir->{$recordColumn})->lockForUpdate()->first();

        if ($record === null) {
            throw new InvalidArgumentException('Surat pemilik riwayat tidak ditemukan.');
        }

        if ($model->exists && $model->isDirty(['putaran_id', $butirColumn])) {
            throw new LogicException('Identitas riwayat tidak boleh dipindah ke butir, unit, atau putaran lain.');
        }

        if ($model->getAttribute('putaran_id') === null) {
            if ($model->exists && $record->putaran_aktif_id !== null) {
                throw new LogicException('Riwayat tanpa putaran harus diperiksa sebelum digunakan pada alur baru.');
            }

            $model->setAttribute('putaran_id', $record->putaran_aktif_id ?? $this->legacyFor($connection, (array) $record)->id);
        }

        $putaran = SuratPutaran::on($connection)->lockForUpdate()->findOrFail($model->getAttribute('putaran_id'));

        if ((int) $putaran->record_id !== (int) $record->id) {
            throw new InvalidArgumentException('Putaran dan butir harus berasal dari surat yang sama.');
        }

        if ($putaran->archived_at !== null || (! $putaran->isLegacy() && (int) $record->putaran_aktif_id !== (int) $putaran->id)) {
            throw new LogicException('Riwayat putaran yang sudah diarsipkan tidak boleh diubah.');
        }

        if ($model->getTable() === 'tb_tanggapan') {
            $this->prepareResponseUnit($model, $putaran, $module);

            if ($model->exists && $model->isDirty(['butir_pic_id', 'unit_kerja_id'])) {
                throw new LogicException('Identitas unit tanggapan yang tersimpan tidak boleh diganti.');
            }

            if ($model->getAttribute('unit_kerja_id') !== null) {
                $this->assertNewEntryIsUnique($model, $butirColumn);
            }
        }

        if (! $putaran->isLegacy()) {
            if ($model->exists && $model->isDirty(['butir_pic_id', 'unit_kerja_id', 'kompilasi_id', 'id_tanggapan', 'id_tindak_lanjut', 'tahap_kompilasi', 'tahap_review', 'putaran_tl'])) {
                throw new LogicException('Identitas pengisi dan sumber reviu pada putaran ini tidak boleh diganti.');
            }

            $this->assertRoundLinks($model, $putaran, $butirColumn);
            if ($model->getTable() !== 'tb_tanggapan') {
                $this->assertNewEntryIsUnique($model, $butirColumn);
            }
        }
    }

    private function prepareResponseUnit(Model $model, SuratPutaran $putaran, string $module): void
    {
        $db = $model->getConnection();
        $butirColumn = 'id_butir_'.$module;

        if ($model->getAttribute('butir_pic_id') !== null) {
            $pic = $db->table('tb_butir_pic')->where('id', $model->getAttribute('butir_pic_id'))
                ->where($butirColumn, $model->getAttribute($butirColumn))->first();

            if ($pic !== null && $pic->unit_kerja_id !== null) {
                if ($model->getAttribute('unit_kerja_id') !== null && (int) $model->getAttribute('unit_kerja_id') !== (int) $pic->unit_kerja_id) {
                    throw new InvalidArgumentException('Unit pengisi tidak sesuai dengan PIC butir.');
                }

                $model->setAttribute('unit_kerja_id', $pic->unit_kerja_id);
            } elseif (! $model->exists || ! $putaran->isLegacy()) {
                throw new InvalidArgumentException('PIC unit pengisi tidak ditemukan pada butir ini.');
            }
        }

        if (! $model->exists || ! $putaran->isLegacy()) {
            $unitId = $model->getAttribute('unit_kerja_id');

            if ($unitId === null || ! $db->table('tb_butir_pic')->where($butirColumn, $model->getAttribute($butirColumn))
                ->where('unit_kerja_id', $unitId)->whereIn('jenis_pic', ['utama', 'pendukung'])->exists()) {
                throw new InvalidArgumentException('Tanggapan baru harus memiliki unit pengisi yang ditugaskan pada butir.');
            }
        }
    }

    private function assertRoundLinks(Model $model, SuratPutaran $putaran, string $butirColumn): void
    {
        if ($model->getTable() !== 'tb_review') {
            return;
        }

        foreach (['kompilasi_id' => 'tb_kompilasi', 'id_tanggapan' => 'tb_tanggapan', 'id_tindak_lanjut' => 'tb_tindak_lanjut'] as $column => $table) {
            if ($model->getAttribute($column) !== null && ! $model->getConnection()->table($table)
                ->where('id', $model->getAttribute($column))->where('putaran_id', $putaran->id)
                ->where($butirColumn, $model->getAttribute($butirColumn))->exists()) {
                throw new InvalidArgumentException('Sumber reviu harus berasal dari butir dan putaran yang sama.');
            }
        }
    }

    private function assertNewEntryIsUnique(Model $model, string $butirColumn): void
    {
        if ($model->exists || ! in_array($model->getTable(), ['tb_tanggapan', 'tb_kompilasi', 'tb_review'], true)) {
            return;
        }

        $query = $model->newQuery()->where('putaran_id', $model->getAttribute('putaran_id'))
            ->where($butirColumn, $model->getAttribute($butirColumn));

        if ($model->getTable() === 'tb_tanggapan') {
            $query->where('unit_kerja_id', $model->getAttribute('unit_kerja_id'));
        }

        if ($query->lockForUpdate()->first(['id']) !== null) {
            throw new LogicException('Data butir dan unit pada putaran ini sudah ada; perbarui data yang tersimpan.');
        }
    }
}
