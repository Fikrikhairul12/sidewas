<?php

namespace App\Models;

use App\Models\Concerns\TracksUser;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class RawasRecord extends Model
{
    use TracksUser;

    protected $connection = 'mysql_rawas';

    protected $table = 'tb_record';

    protected $fillable = [
        'id_rawas',
        'cluster_id',
        'sub_cluster_id',
        'nomor_surat',
        'tanggal_surat',
        'perihal_surat',
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
            if (empty($record->id_rawas)) {
                $record->id_rawas = static::generateIdRawas($record->nomor_surat);
            }

            if (!empty($record->tanggal_surat) && empty($record->jth_tempo)) {
                $record->jth_tempo = Carbon::parse($record->tanggal_surat)->addDays(30);
            }

            if (empty($record->status)) {
                $record->status = 'draft';
            }
        });
    }

    public static function generateIdRawas(string $nomorSurat): string
    {
        $nomorSurat = trim($nomorSurat);

        return $nomorSurat . '-RAWAS';
    }

    public function cluster()
    {
        return $this->belongsTo(RawasCluster::class, 'cluster_id', 'id');
    }

    public function subCluster()
    {
        return $this->belongsTo(RawasSubCluster::class, 'sub_cluster_id', 'id');
    }

    public function butirRawas()
    {
        return $this->hasMany(RawasButir::class, 'id_rawas', 'id_rawas');
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
