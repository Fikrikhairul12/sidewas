<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $table = 'tb_role';

    protected $fillable = [
        'name',
        'display_name',
        'level',
        'is_universal',
        'keterangan',
    ];

    protected $casts = [
        'is_universal' => 'boolean',
    ];

    public function roleTypes()
    {
        return $this->hasMany(RoleType::class, 'role_id', 'id');
    }
}
