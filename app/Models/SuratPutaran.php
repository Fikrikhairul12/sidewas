<?php

namespace App\Models;

use Database\Factories\SuratPutaranFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class SuratPutaran extends Model
{
    /** @use HasFactory<SuratPutaranFactory> */
    use HasFactory;

    protected $table = 'tb_surat_putaran';

    protected $fillable = [
        'record_id', 'nomor_putaran', 'nomor_surat_lama', 'status_lama',
        'opened_at', 'archived_at', 'created_by', 'archived_by',
    ];

    protected function casts(): array
    {
        return [
            'nomor_putaran' => 'integer',
            'opened_at' => 'datetime',
            'archived_at' => 'datetime',
            'penugasan_unit' => 'array',
            'penugasan_dicatat_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $putaran): void {
            if ($putaran->getRawOriginal('penugasan_unit') !== null && $putaran->isDirty(['penugasan_unit', 'penugasan_dicatat_at'])) {
                throw new LogicException('Penugasan unit yang sudah dicatat untuk riwayat putaran tidak boleh diubah.');
            }
            if ($putaran->surat()->first()?->getAttribute('closed_at') !== null) {
                throw new LogicException('Putaran terakhir surat yang sudah ditutup tidak boleh diubah.');
            }
            if ($putaran->isDirty(['record_id', 'nomor_putaran']) || $putaran->getRawOriginal('archived_at') !== null) {
                throw new LogicException('Identitas atau arsip putaran tidak boleh diubah.');
            }
        });

        static::deleting(function (self $putaran): void {
            if ($putaran->tanggapan()->exists() || $putaran->kompilasi()->exists()
                || $putaran->reviu()->exists() || $putaran->tindakLanjutLama()->exists()) {
                throw new LogicException('Putaran yang memiliki riwayat tidak boleh dihapus.');
            }
        });
    }

    public function isLegacy(): bool
    {
        return $this->nomor_putaran === 0;
    }

    public function surat(): BelongsTo
    {
        return $this->belongsTo($this->relatedClass('Record'), 'record_id');
    }

    public function tanggapan(): HasMany
    {
        return $this->hasMany($this->relatedClass('Tanggapan'), 'putaran_id');
    }

    public function kompilasi(): HasMany
    {
        return $this->hasMany($this->relatedClass('Kompilasi'), 'putaran_id');
    }

    public function reviu(): HasMany
    {
        return $this->hasMany($this->relatedClass('Review'), 'putaran_id');
    }

    public function tindakLanjutLama(): HasMany
    {
        return $this->hasMany($this->relatedClass('TindakLanjut'), 'putaran_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function archiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    /** @return class-string<Model> */
    private function relatedClass(string $suffix): string
    {
        $prefix = match ($this->getConnectionName()) {
            'mysql_snp' => 'Snp',
            'mysql_djsn' => 'Djsn',
            default => throw new LogicException('Putaran hanya tersedia pada koneksi SNP atau DJSN.'),
        };

        return __NAMESPACE__.'\\'.$prefix.$suffix;
    }
}
