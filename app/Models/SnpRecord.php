<?php

namespace App\Models;

use App\Models\Concerns\TracksUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class SnpRecord extends Model
{
    use TracksUser;

    protected $connection = 'mysql_snp';
    protected $table = 'tb_record';

    protected $fillable = [
        'id_snp',
        'cluster_id',
        'sub_cluster_id',
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
            if (empty($record->id_snp)) {
                $record->id_snp = static::generateIdSnp($record->nomor_surat);
            }

            if (!empty($record->tanggal_surat) && empty($record->jth_tempo)) {
                $record->jth_tempo = Carbon::parse($record->tanggal_surat)->addDays(30);
            }

            if (empty($record->status)) {
                $record->status = 'draft';
            }
        });
    }

    public static function generateIdSnp(string $nomorSurat): string
    {
        $nomorSurat = trim($nomorSurat);

        return $nomorSurat . '-SNP';
    }

    public function cluster()
    {
        return $this->belongsTo(SnpCluster::class, 'cluster_id', 'id');
    }

    public function subCluster()
    {
        return $this->belongsTo(SnpSubCluster::class, 'sub_cluster_id', 'id');
    }

    public function butirSnp()
    {
        return $this->hasMany(SnpButir::class, 'id_snp', 'id_snp');
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
