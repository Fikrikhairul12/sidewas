<?php

namespace App\Models;

use App\Models\Concerns\TracksUser;
use Illuminate\Database\Eloquent\Model;
use App\Models\SnpButirPic;
use App\Models\SnpTindakLanjut;
use App\Models\SnpTanggapan;
use App\Models\SnpReview;
use App\Models\SnpRecord;
use App\Models\User;

class SnpButir extends Model
{
    use TracksUser;

    protected $connection = 'mysql_snp';
    protected $table = 'tb_butir_snp';

    protected $fillable = [
        'id_butir_snp',
        'id_snp',
        'butir_snp',
        'created_by',
        'updated_by',
    ];

    protected static function booted(): void
    {
        static::creating(function ($butir) {
            if (empty($butir->id_butir_snp)) {
                $butir->id_butir_snp = static::generateIdButirSnp($butir->id_snp);
            }
        });
    }

    public static function generateIdButirSnp(string $idSnp): string
    {
        $lastButir = static::where('id_snp', $idSnp)->orderByDesc('id')->first();
        $nextNumber = $lastButir
            ? ((int) substr($lastButir->id_butir_snp, strrpos($lastButir->id_butir_snp, '.') + 1)) + 1
            : 1;

        return $idSnp . '.' . str_pad($nextNumber, 2, '0', STR_PAD_LEFT);
    }

    public function record()
    {
        return $this->belongsTo(SnpRecord::class, 'id_snp', 'id_snp');
    }

    public function butirPics()
    {
        return $this->hasMany(SnpButirPic::class, 'id_butir_snp', 'id_butir_snp');
    }

    public function tanggapan()
    {
        return $this->hasMany(SnpTanggapan::class, 'id_butir_snp', 'id_butir_snp');
    }

    public function tindakLanjuts()
    {
        return $this->hasMany(SnpTindakLanjut::class, 'id_butir_snp', 'id_butir_snp');
    }

    public function reviews()
    {
        return $this->hasMany(SnpReview::class, 'id_butir_snp', 'id_butir_snp');
    }

    public function kompilasis()
    {
        return $this->hasMany(SnpKompilasi::class, 'id_butir_snp', 'id_butir_snp');
    }

    public function kompilasiTanggapans()
    {
        return $this->hasMany(SnpKompilasi::class, 'id_butir_snp', 'id_butir_snp')
            ->where('tahap_kompilasi', 'tanggapan')
            ->orderBy('putaran_tl');
    }

    public function kompilasiTindakLanjuts()
    {
        return $this->hasMany(SnpKompilasi::class, 'id_butir_snp', 'id_butir_snp')
            ->where('tahap_kompilasi', 'tindak_lanjut')
            ->orderBy('putaran_tl');
    }

    public function kompilasiTanggapan()
    {
        return $this->hasOne(SnpKompilasi::class, 'id_butir_snp', 'id_butir_snp')
            ->where('tahap_kompilasi', 'tanggapan')
            ->latestOfMany('id');
    }

    public function kompilasiTindakLanjut()
    {
        return $this->hasOne(SnpKompilasi::class, 'id_butir_snp', 'id_butir_snp')
            ->where('tahap_kompilasi', 'tindak_lanjut')
            ->latestOfMany('id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by', 'id');
    }

    // public function kompilasis()
    // {
    //     return $this->hasMany(SnpKompilasi::class, 'id_butir_snp', 'id_butir_snp');
    // }
}
