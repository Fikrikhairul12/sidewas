<?php

namespace App\Services\Identity;

use App\Models\Kunjungan\Employee;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class CurrentEmployeeResolver
{
    public function __construct(private SidewasEmployeeSynchronizer $synchronizer) {}

    public function find(User $user): ?Employee
    {
        if (! $user->canCreateKunjungan()) {
            return null;
        }

        $employee = $this->synchronizer->syncUser($user);

        return $employee->is_active ? $employee : null;
    }

    public function resolve(User $user): Employee
    {
        return $this->find($user) ?? throw new AuthorizationException(
            'Akun Anda belum dipetakan ke data pegawai aktif.'
        );
    }
}
