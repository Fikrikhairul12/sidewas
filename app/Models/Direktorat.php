<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Direktorat extends Model
{
    protected $table = 'tb_direktorat';

    protected $fillable = [
        'nama_direktorat',
        'kode_direktorat',
        'keterangan',
    ];

    public function unitKerja()
    {
        return $this->hasMany(UnitKerja::class, 'direktorat_id', 'id');
    }
}
