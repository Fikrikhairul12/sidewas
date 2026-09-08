<?php

namespace App\Models\Kunjungan;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Employee extends Model
{
    protected $connection = 'mysql_kunjungan';

    protected $fillable = [
        'sidewas_user_id', 'employee_number', 'name', 'email', 'position', 'unit_id',
        'organizational_unit', 'directorate', 'sidewas_roles', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sidewas_roles' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function visits(): BelongsToMany
    {
        return $this->belongsToMany(Visit::class, 'visit_participants')->withTimestamps();
    }

    public function scopeEligibleForVisits(Builder $query): Builder
    {
        $eligibleUserIds = User::query()->get()
            ->filter(fn (User $user) => $user->canCreateKunjungan())
            ->pluck('id');

        return $query->where('is_active', true)
            ->whereIn('sidewas_user_id', $eligibleUserIds);
    }

    public function getRoleLabelAttribute(): string
    {
        $roles = collect($this->sidewas_roles)
            ->map(fn (string $role) => str($role)->replace('_', ' ')->title()->toString());

        return $roles->implode(', ');
    }
}
