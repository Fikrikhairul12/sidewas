<?php

namespace App\Models;

use App\Models\Concerns\TracksUser;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class RagabRecord extends Model
{
    use TracksUser;

    protected $connection = 'mysql_ragab';

    protected $table = 'tb_record';

    protected $fillable = [
        'id_ragab',
        'nomor_surat',
        'tanggal_surat',
        'perihal_surat',
        'dokumen',
        'dokumen_memo',
        'jth_tempo',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'tanggal_surat' => 'date',
        'jth_tempo' => 'date',
    ];

    protected static function booted(): void
    {
        static::creating(function ($record) {
            if (empty($record->id_ragab)) {
                $record->id_ragab = static::generateIdRagab($record->nomor_surat);
            }

            if (!empty($record->tanggal_surat) && empty($record->jth_tempo)) {
                $record->jth_tempo = Carbon::parse($record->tanggal_surat)->addDays(30);
            }

            if (empty($record->status)) {
                $record->status = 'draft';
            }
        });
    }

    public static function generateIdRagab(string $nomorSurat): string
    {
        return trim($nomorSurat) . '-RAGAB';
    }

    public function butirRagab()
    {
        return $this->hasMany(RagabButir::class, 'id_ragab', 'id_ragab');
    }

    public function isEveryButirSelesaiTuntas(): bool
    {
        $this->loadMissing('butirRagab');

        if ($this->butirRagab->isEmpty()) {
            return false;
        }

        return $this->butirRagab->every(function (RagabButir $butir): bool {
            return $butir->statusTindakLanjut() === 'selesai_tuntas';
        });
    }

    public function isButirAdditionLocked(): bool
    {
        $this->loadMissing('butirRagab');

        if ($this->butirRagab->count() !== 1) {
            return false;
        }

        return $this->status === 'tuntas'
            || $this->butirRagab->first()?->statusTindakLanjut() === 'selesai_tuntas';
    }

    public function syncStatusFromButir(?int $updatedBy = null): void
    {
        $this->loadMissing('butirRagab');

        $status = match (true) {
            $this->butirRagab->isEmpty() => 'draft',
            $this->isEveryButirSelesaiTuntas() => 'tuntas',
            default => 'dalam_proses',
        };

        $attributes = ['status' => $status];

        if ($updatedBy !== null) {
            $attributes['updated_by'] = $updatedBy;
        }

        $this->update($attributes);
    }

    public function syncStatusFromTindakLanjut(?int $updatedBy = null): void
    {
        $this->syncStatusFromButir($updatedBy);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by', 'id');
    }
}
