<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Type extends Model
{
    protected $table = 'tb_type';

    protected $fillable = [
        'code',
        'name',
        'database_connection',
        'database_name',
        'keterangan',
    ];

    public function roleTypes()
    {
        return $this->hasMany(RoleType::class, 'type_id', 'id');
    }
}
