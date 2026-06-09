<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RagabButirDirektorat extends Model
{
    protected $connection = 'mysql_ragab';

    protected $table = 'tb_butir_direktorat';

    protected $fillable = [
        'id_butir_ragab',
        'direktorat_id',
    ];

    public function butir()
    {
        return $this->belongsTo(RagabButir::class, 'id_butir_ragab', 'id_butir_ragab');
    }

    public function direktorat()
    {
        return $this->belongsTo(Direktorat::class, 'direktorat_id', 'id');
    }
}
