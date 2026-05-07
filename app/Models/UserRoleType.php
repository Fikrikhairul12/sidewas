<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserRoleType extends Model
{
    protected $table = 'tb_user_role_type';

    protected $fillable = [
        'user_id',
        'role_type_id',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function roleType()
    {
        return $this->belongsTo(RoleType::class, 'role_type_id', 'id');
    }
}
