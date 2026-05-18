<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UnitKerja extends Model
{
    protected $connection = 'mysql';

    protected $table = 'tb_unit_kerja';

    protected $fillable = [
        'direktorat_id',
        'nama_unit',
        'kode_unit',
        'keterangan',
    ];

    public function direktorat()
    {
        return $this->belongsTo(Direktorat::class, 'direktorat_id', 'id');
    }

    public function users()
    {
        return $this->belongsToMany(
            User::class,
            'tb_user_unit_kerja',
            'unit_kerja_id',
            'user_id'
        )->withPivot('status')->withTimestamps();
    }
}
