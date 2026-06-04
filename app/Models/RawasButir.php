<?php

namespace App\Models;

use App\Models\Concerns\TracksUser;
use Illuminate\Database\Eloquent\Model;

class RawasButir extends Model
{
    use TracksUser;

    protected $connection = 'mysql_rawas';

    protected $table = 'tb_butir_rawas';

    protected $fillable = [
        'id_butir_rawas',
        'id_rawas',
        'butir_rawas',
        'created_by',
        'updated_by',
    ];

    protected static function booted(): void
    {
        static::creating(function ($butir) {
            if (empty($butir->id_butir_rawas)) {
                $butir->id_butir_rawas = static::generateIdButirRawas($butir->id_rawas);
            }
        });
    }

    public static function generateIdButirRawas(string $idRawas): string
    {
        $lastButir = static::where('id_rawas', $idRawas)->orderByDesc('id')->first();
        $nextNumber = $lastButir
            ? ((int) substr($lastButir->id_butir_rawas, strrpos($lastButir->id_butir_rawas, '.') + 1)) + 1
            : 1;

        return $idRawas . '.' . str_pad($nextNumber, 2, '0', STR_PAD_LEFT);
    }

    public function record()
    {
        return $this->belongsTo(RawasRecord::class, 'id_rawas', 'id_rawas');
    }

    public function butirPics()
    {
        return $this->hasMany(RawasButirPic::class, 'id_butir_rawas', 'id_butir_rawas');
    }

    public function tindakLanjuts()
    {
        return $this->hasMany(RawasTindakLanjut::class, 'id_butir_rawas', 'id_butir_rawas');
    }

    public function reviews()
    {
        return $this->hasMany(RawasReview::class, 'id_butir_rawas', 'id_butir_rawas');
    }
}
