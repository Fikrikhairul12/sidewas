<?php

namespace App\Models;

use Database\Factories\SuratRekonsiliasiFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;
use LogicException;

class SuratRekonsiliasi extends Model
{
    /** @use HasFactory<SuratRekonsiliasiFactory> */
    use HasFactory;

    protected $table = 'tb_surat_rekonsiliasi';

    protected $fillable = [
        'record_id', 'tahap_id', 'tabel_sumber', 'sumber_id', 'kode', 'alasan', 'bukti',
        'status', 'diselesaikan_by', 'diselesaikan_at', 'catatan_penyelesaian',
    ];

    protected function casts(): array
    {
        return ['bukti' => 'array', 'diselesaikan_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $item): void {
            SuratTahap::moduleFor($item->getConnectionName());
            if (! in_array($item->status, ['terbuka', 'selesai'], true)
                || $item->status === 'selesai' && ($item->diselesaikan_by === null || $item->diselesaikan_at === null || trim((string) $item->catatan_penyelesaian) === '')) {
                throw new InvalidArgumentException('Penyelesaian rekonsiliasi harus memiliki pengguna, waktu, dan catatan pemeriksaan bukti.');
            }
            if ($item->tahap_id !== null && ! $item->tahap()->where('record_id', $item->record_id)->exists()) {
                throw new InvalidArgumentException('Rekonsiliasi dan tahap harus berasal dari surat yang sama.');
            }
            if ($item->exists && $item->isDirty(['record_id', 'tabel_sumber', 'sumber_id', 'kode', 'alasan', 'bukti'])) {
                throw new LogicException('Jejak awal rekonsiliasi tidak boleh ditimpa.');
            }
            if ($item->getRawOriginal('status') === 'selesai' && $item->isDirty()) {
                throw new LogicException('Keputusan rekonsiliasi yang sudah diselesaikan harus tetap tersimpan.');
            }
        });
        static::deleting(function (): void {
            throw new LogicException('Jejak rekonsiliasi harus tetap tersimpan.');
        });
    }

    public function tahap(): BelongsTo
    {
        return $this->belongsTo(SuratTahap::class, 'tahap_id');
    }

    public function surat(): BelongsTo
    {
        $module = SuratTahap::moduleFor($this->getConnectionName());

        return $this->belongsTo(__NAMESPACE__.'\\'.ucfirst($module).'Record', 'record_id');
    }
}
