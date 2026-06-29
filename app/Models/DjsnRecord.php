<?php

namespace App\Models;

use App\Models\Concerns\TracksUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class DjsnRecord extends Model
{
    use TracksUser;

    protected $connection = 'mysql_djsn';
    protected $table = 'tb_record';

    protected $fillable = [
        'id_djsn',
        'nomor_surat',
        'tanggal_surat',
        'perihal_surat',
        'dokumen',
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
            if (empty($record->id_djsn)) {
                $record->id_djsn = static::generateIdDjsn($record->nomor_surat);
            }

            if (!empty($record->tanggal_surat) && empty($record->jth_tempo)) {
                $record->jth_tempo = Carbon::parse($record->tanggal_surat)->addDays(30);
            }

            if (empty($record->status)) {
                $record->status = 'draft';
            }
        });
    }

    public static function generateIdDjsn(string $nomorSurat): string
    {
        $nomorSurat = trim($nomorSurat);

        return $nomorSurat . '-DJSN';
    }

    public function butirDjsn()
    {
        return $this->hasMany(DjsnButir::class, 'id_djsn', 'id_djsn');
    }

    public function isEveryButirSelesaiTuntas(): bool
    {
        $this->loadMissing('butirDjsn');

        if ($this->butirDjsn->isEmpty()) {
            return false;
        }

        return $this->butirDjsn->every(function (DjsnButir $butir): bool {
            return $butir->statusTindakLanjut() === 'selesai_tuntas';
        });
    }

    public function isButirAdditionLocked(): bool
    {
        $this->loadMissing('butirDjsn');

        if ($this->butirDjsn->count() !== 1) {
            return false;
        }

        return $this->status === 'tuntas'
            || $this->butirDjsn->first()?->statusTindakLanjut() === 'selesai_tuntas';
    }

    public function syncStatusFromButir(?int $updatedBy = null): void
    {
        $this->loadMissing('butirDjsn');

        $status = match (true) {
            $this->butirDjsn->isEmpty() => 'draft',
            $this->isEveryButirSelesaiTuntas() => 'tuntas',
            default => 'dalam_proses',
        };

        $attributes = ['status' => $status];

        if ($updatedBy !== null) {
            $attributes['updated_by'] = $updatedBy;
        }

        $this->update($attributes);
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
