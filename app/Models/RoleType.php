<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Role;
use App\Models\Type;

class RoleType extends Model
{
    protected $table = 'tb_role_type';

    protected $fillable = [
        'role_id',
        'type_id',
        'name',
        'keterangan',
    ];

    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id', 'id');
    }

    public function type()
    {
        return $this->belongsTo(Type::class, 'type_id', 'id');
    }

    public function userRoleTypes()
    {
        return $this->hasMany(UserRoleType::class, 'role_type_id', 'id');
    }
}
