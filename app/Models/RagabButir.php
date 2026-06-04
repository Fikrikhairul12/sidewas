<?php

namespace App\Models;

use App\Models\Concerns\TracksUser;
use Illuminate\Database\Eloquent\Model;

class RagabButir extends Model
{
    use TracksUser;

    protected $connection = 'mysql_ragab';

    protected $table = 'tb_butir_ragab';

    protected $fillable = [
        'id_butir_ragab',
        'id_ragab',
        'butir_ragab',
        'created_by',
        'updated_by',
    ];

    protected static function booted(): void
    {
        static::creating(function ($butir) {
            if (empty($butir->id_butir_ragab)) {
                $butir->id_butir_ragab = static::generateIdButirRagab($butir->id_ragab);
            }
        });
    }

    public static function generateIdButirRagab(string $idRagab): string
    {
        $lastButir = static::where('id_ragab', $idRagab)->orderByDesc('id')->first();
        $nextNumber = $lastButir
            ? ((int) substr($lastButir->id_butir_ragab, strrpos($lastButir->id_butir_ragab, '.') + 1)) + 1
            : 1;

        return $idRagab . '.' . str_pad($nextNumber, 2, '0', STR_PAD_LEFT);
    }

    public function record()
    {
        return $this->belongsTo(RagabRecord::class, 'id_ragab', 'id_ragab');
    }

    public function butirPics()
    {
        return $this->hasMany(RagabButirPic::class, 'id_butir_ragab', 'id_butir_ragab');
    }

    public function tindakLanjuts()
    {
        return $this->hasMany(RagabTindakLanjut::class, 'id_butir_ragab', 'id_butir_ragab');
    }

    public function reviews()
    {
        return $this->hasMany(RagabReview::class, 'id_butir_ragab', 'id_butir_ragab');
    }
}
