<?php

namespace App\Services\Identity;

use App\Models\Kunjungan\Employee;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SidewasEmployeeSynchronizer
{
    public function syncAll(): int
    {
        $users = User::query()->orderBy('id')->get();
        $unitAssignments = $this->unitAssignments();
        $roleAssignments = $this->roleAssignments();

        foreach ($users as $user) {
            $this->syncUser(
                $user,
                $unitAssignments->get($user->id, collect()),
                $roleAssignments->get($user->id, collect()),
            );
        }

        Employee::query()
            ->whereNotNull('sidewas_user_id')
            ->when($users->isNotEmpty(), fn ($query) => $query->whereNotIn('sidewas_user_id', $users->modelKeys()))
            ->update(['is_active' => false]);

        return $users->count();
    }

    public function syncUser(User $user, ?Collection $units = null, ?Collection $roles = null): Employee
    {
        $units ??= $this->unitAssignments()->get($user->id, collect());
        $roles ??= $this->roleAssignments()->get($user->id, collect());
        $employee = Employee::query()->where('sidewas_user_id', $user->id)
            ->orWhere(function ($query) use ($user): void {
                $query->whereNull('sidewas_user_id')->where('email', $user->email);
            })->first();

        $employee ??= new Employee;
        $employee->fill([
            'sidewas_user_id' => $user->id,
            'employee_number' => $employee->employee_number ?: sprintf('SIDEWAS-%06d', $user->id),
            'name' => $user->name,
            'email' => $user->email,
            'organizational_unit' => $units->pluck('nama_unit')->filter()->unique()->implode(', ') ?: null,
            'directorate' => $units->pluck('nama_direktorat')->filter()->unique()->implode(', ') ?: null,
            'sidewas_roles' => $roles->pluck('name')->filter()->unique()->values()->all(),
            'is_active' => $user->isActiveForKunjungan(),
        ])->save();

        return $employee->refresh();
    }

    private function unitAssignments(): Collection
    {
        if (! Schema::hasTable('tb_user_unit_kerja')) {
            return collect();
        }

        return DB::table('tb_user_unit_kerja')
            ->join('tb_unit_kerja', 'tb_unit_kerja.id', '=', 'tb_user_unit_kerja.unit_kerja_id')
            ->leftJoin('tb_direktorat', 'tb_direktorat.id', '=', 'tb_unit_kerja.direktorat_id')
            ->where('tb_user_unit_kerja.status', 'active')
            ->get(['tb_user_unit_kerja.user_id', 'tb_unit_kerja.nama_unit', 'tb_direktorat.nama_direktorat'])
            ->groupBy('user_id');
    }

    private function roleAssignments(): Collection
    {
        if (! Schema::hasTable('tb_user_role_type')) {
            return collect();
        }

        return DB::table('tb_user_role_type')
            ->join('tb_role_type', 'tb_role_type.id', '=', 'tb_user_role_type.role_type_id')
            ->where('tb_user_role_type.status', 'active')
            ->get(['tb_user_role_type.user_id', 'tb_role_type.name'])
            ->groupBy('user_id');
    }
}
