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
                $count = static::where('id_ragab', $butir->id_ragab)->count() + 1;

                $butir->id_butir_ragab = $butir->id_ragab . '.' . str_pad($count, 2, '0', STR_PAD_LEFT);
            }
        });
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
