<?php

namespace App\Models;

use Database\Factories\SuratTanggalRiwayatFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SuratTanggalRiwayat extends Model
{
    /** @use HasFactory<SuratTanggalRiwayatFactory> */
    use HasFactory;

    protected $table = 'tb_surat_tanggal_riwayat';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['tanggal_lama' => 'date', 'tanggal_baru' => 'date', 'diputuskan_at' => 'datetime', 'bukti' => 'array'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $entry): void {
            SuratTahap::moduleFor($entry->getConnectionName());
            if (! SuratTahap::on($entry->getConnectionName())->whereKey($entry->tahap_id)->where('record_id', $entry->record_id)->exists()
                || trim((string) $entry->alasan) === '' || $entry->diputuskan_at === null || ! is_array($entry->bukti)) {
                throw new \InvalidArgumentException('Riwayat tanggal memerlukan tahap surat yang benar, waktu, alasan, dan bukti.');
            }
        });
        static::updating(function (): void {
            throw new \LogicException('Riwayat penetapan dan keputusan tanggal tidak boleh ditimpa.');
        });
        static::deleting(function (): void {
            throw new \LogicException('Riwayat tanggal harus tetap tersimpan.');
        });
    }
}
