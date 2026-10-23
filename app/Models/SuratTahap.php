<?php

namespace App\Models;

use App\Enums\TahapProses;
use Database\Factories\SuratTahapFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;
use LogicException;

class SuratTahap extends Model
{
    /** @use HasFactory<SuratTahapFactory> */
    use HasFactory;

    protected $table = 'tb_surat_tahap';

    protected $fillable = [
        'record_id', 'putaran_asal_id', 'kunci_asal', 'tahap_proses', 'nomor_putaran',
        'asal_pemetaan', 'status_pemetaan', 'bukti_pemetaan', 'penugasan_unit',
        'mulai_at', 'jatuh_tempo', 'bukti_tanggal', 'tanggal_ditetapkan_at',
        'tanggal_ditetapkan_by', 'archived_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'tahap_proses' => TahapProses::class, 'nomor_putaran' => 'integer',
            'bukti_pemetaan' => 'array', 'penugasan_unit' => 'array', 'bukti_tanggal' => 'array',
            'mulai_at' => 'datetime', 'jatuh_tempo' => 'date',
            'tanggal_ditetapkan_at' => 'datetime', 'archived_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $tahap): void {
            self::moduleFor($tahap->getConnectionName());
            $stored = $tahap->exists ? $tahap->newQuery()->lockForUpdate()->findOrFail($tahap->id) : null;
            if ($stored?->archived_at !== null && $tahap->isDirty()) {
                throw new LogicException('Tahap yang sudah diarsipkan tidak boleh diubah atau dibuka kembali.');
            }
            if ($tahap->tahap_proses === TahapProses::TanggapanAwal && $tahap->nomor_putaran !== 0
                || $tahap->tahap_proses === TahapProses::TindakLanjut && $tahap->nomor_putaran !== null && $tahap->nomor_putaran < 1) {
                throw new InvalidArgumentException('Tanggapan Awal memakai nomor 0; Tindak Lanjut memakai nomor positif atau menunggu rekonsiliasi.');
            }
            if ($tahap->putaran_asal_id !== null
                && ! $tahap->putaranAsal()->where('record_id', $tahap->record_id)->exists()) {
                throw new InvalidArgumentException('Putaran asal dan tahap harus berasal dari surat yang sama.');
            }
            if ($tahap->exists && ($tahap->isDirty(['record_id', 'putaran_asal_id', 'kunci_asal', 'tahap_proses', 'asal_pemetaan', 'bukti_pemetaan'])
                || $tahap->getRawOriginal('nomor_putaran') !== null && $tahap->isDirty('nomor_putaran'))) {
                throw new LogicException('Identitas tahap dan jejak pemetaan sumber tidak boleh diubah.');
            }
            if ($stored?->penugasan_unit !== null && $tahap->isDirty('penugasan_unit')) {
                throw new LogicException('Penugasan unit yang sudah dicatat pada tahap tidak boleh ditimpa.');
            }
        });
        static::deleting(function (): void {
            throw new LogicException('Tahap proses harus tetap tersimpan sebagai riwayat.');
        });
    }

    public static function moduleFor(?string $connection): string
    {
        return match ($connection) {
            'mysql_snp' => 'snp', 'mysql_djsn' => 'djsn',
            default => throw new InvalidArgumentException('Tahap proses hanya tersedia pada koneksi SNP atau DJSN.'),
        };
    }

    public function surat(): BelongsTo
    {
        return $this->belongsTo($this->relatedClass('Record'), 'record_id');
    }

    public function putaranAsal(): BelongsTo
    {
        return $this->belongsTo(SuratPutaran::class, 'putaran_asal_id');
    }

    public function tanggapanSumber(): HasMany
    {
        return $this->hasMany($this->relatedClass('Tanggapan'), 'tahap_id');
    }

    public function tindakLanjutSumber(): HasMany
    {
        return $this->hasMany($this->relatedClass('TindakLanjut'), 'tahap_id');
    }

    public function kompilasi(): HasMany
    {
        return $this->hasMany($this->relatedClass('Kompilasi'), 'tahap_id');
    }

    public function reviu(): HasMany
    {
        return $this->hasMany($this->relatedClass('Review'), 'tahap_id');
    }

    public function bahan(): HasMany
    {
        return $this->hasMany(KompilasiBahan::class, 'tahap_id');
    }

    public function rekonsiliasi(): HasMany
    {
        return $this->hasMany(SuratRekonsiliasi::class, 'tahap_id');
    }

    /** @return class-string<Model> */
    public function relatedClass(string $suffix): string
    {
        return __NAMESPACE__.'\\'.ucfirst(self::moduleFor($this->getConnectionName())).$suffix;
    }
}
