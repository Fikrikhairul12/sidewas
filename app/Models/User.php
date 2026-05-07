<?php

namespace App\Models;

use App\Models\Concerns\TracksUser;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'google_id',
        'avatar',
        'provider',
        'status',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function userRoleTypes()
    {
        return $this->hasMany(UserRoleType::class, 'user_id', 'id');
    }

    public function roleTypes()
    {
        return $this->belongsToMany(
            RoleType::class,
            'tb_user_role_type',
            'user_id',
            'role_type_id'
        )->withPivot('status')->withTimestamps();
    }

    public function unitKerja()
    {
        return $this->belongsToMany(
            UnitKerja::class,
            'tb_user_unit_kerja',
            'user_id',
            'unit_kerja_id'
        )->withPivot('status')->withTimestamps();
    }

    public function komite()
    {
        return $this->belongsToMany(
            Komite::class,
            'tb_user_komite',
            'user_id',
            'komite_id'
        )->withPivot('status')->withTimestamps();
    }

    public function logs()
    {
        return $this->hasMany(LogActivity::class, 'user_id', 'id');
    }

    public function isSuperAdmin(): bool
    {
        return $this->roleTypes()
            ->wherePivot('status', 'active')
            ->whereHas('role', function ($query) {
                $query->where('name', 'super_admin')
                    ->where('is_universal', true);
            })
            ->exists();
    }

    public function canAccessType(string $typeCode): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->roleTypes()
            ->wherePivot('status', 'active')
            ->whereHas('type', function ($query) use ($typeCode) {
                $query->where('code', $typeCode);
            })
            ->exists();
    }
}
