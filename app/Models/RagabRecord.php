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

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by', 'id');
    }
}
