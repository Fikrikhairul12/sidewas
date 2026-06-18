<?php

namespace App\Models;

use App\Models\Concerns\TracksUser;
use App\Models\DjsnButirPic;
use App\Models\DjsnRecord;
use App\Models\DjsnReview;
use App\Models\DjsnTanggapan;
use App\Models\DjsnTindakLanjut;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class DjsnButir extends Model
{
    use TracksUser;

    protected $connection = 'mysql_djsn';
    protected $table = 'tb_butir_djsn';

    protected $fillable = [
        'id_butir_djsn',
        'id_djsn',
        'butir_djsn',
        'cluster_id',
        'sub_cluster_id',
        'created_by',
        'updated_by',
    ];

    protected static function booted(): void
    {
        static::creating(function ($butir) {
            if (empty($butir->id_butir_djsn)) {
                $butir->id_butir_djsn = static::generateIdButirDjsn($butir->id_djsn);
            }
        });
    }

    public static function generateIdButirDjsn(string $idDjsn): string
    {
        $lastButir = static::where('id_djsn', $idDjsn)->orderByDesc('id')->first();
        $nextNumber = $lastButir
            ? ((int) substr($lastButir->id_butir_djsn, strrpos($lastButir->id_butir_djsn, '.') + 1)) + 1
            : 1;

        return $idDjsn . '.' . str_pad($nextNumber, 2, '0', STR_PAD_LEFT);
    }

    public function record()
    {
        return $this->belongsTo(DjsnRecord::class, 'id_djsn', 'id_djsn');
    }

    public function cluster()
    {
        return $this->belongsTo(DjsnCluster::class, 'cluster_id', 'id');
    }

    public function subCluster()
    {
        return $this->belongsTo(DjsnSubCluster::class, 'sub_cluster_id', 'id');
    }

    public function butirPics()
    {
        return $this->hasMany(DjsnButirPic::class, 'id_butir_djsn', 'id_butir_djsn');
    }

    public function tanggapan()
    {
        return $this->hasOne(DjsnTanggapan::class, 'id_butir_djsn', 'id_butir_djsn');
    }

    public function tindakLanjuts()
    {
        return $this->hasMany(DjsnTindakLanjut::class, 'id_butir_djsn', 'id_butir_djsn');
    }

    public function reviews()
    {
        return $this->hasMany(DjsnReview::class, 'id_butir_djsn', 'id_butir_djsn');
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
