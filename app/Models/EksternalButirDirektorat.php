<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EksternalButirDirektorat extends Model
{
    protected $connection = 'mysql_eksternal';

    protected $table = 'tb_butir_direktorat';

    protected $fillable = [
        'id_butir_eksternal',
        'direktorat_id',
    ];

    public function butir()
    {
        return $this->belongsTo(EksternalButir::class, 'id_butir_eksternal', 'id_butir_eksternal');
    }

    public function direktorat()
    {
        return $this->belongsTo(Direktorat::class, 'direktorat_id', 'id');
    }
}
