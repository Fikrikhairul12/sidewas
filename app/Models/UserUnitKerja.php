<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserUnitKerja extends Model
{
    protected $table = 'tb_user_unit_kerja';

    protected $fillable = [
        'user_id',
        'unit_kerja_id',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function unitKerja()
    {
        return $this->belongsTo(UnitKerja::class, 'unit_kerja_id', 'id');
    }
}
