<?php

namespace App\Http\Controllers\Administrasi;

use App\Http\Controllers\Controller;
use App\Models\DeleteRequest;
use App\Models\Direktorat;
use App\Models\Komite;
use App\Models\LogActivity;
use App\Models\RoleType;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class ManajemenUserController extends Controller
{
    public function index(Request $request)
    {
        $authUser = User::find(Auth::id());

        if (! $authUser?->canAccessManajemenUser()) {
            abort(403, 'Anda tidak memiliki akses ke halaman manajemen user.');
        }

        $filters = $request->validate([
            'keyword' => ['nullable', 'string', 'max:255'],
            'role_type_id' => ['nullable', 'integer', 'exists:tb_role_type,id'],
            'direktorat_id' => ['nullable', 'integer', 'exists:tb_direktorat,id'],
            'assignment' => ['nullable', 'string'],
        ]);

        $users = User::query()
            ->with([
                'roleTypes.role',
                'roleTypes.type',
                'unitKerja.direktorat',
                'komite',
                'latestLog',
            ])
            ->when($filters['keyword'] ?? null, function ($query, string $keyword) {
                $query->where(function ($query) use ($keyword) {
                    $query->where('name', 'like', "%{$keyword}%")
                        ->orWhere('email', 'like', "%{$keyword}%");
                });
            })
            ->when($filters['role_type_id'] ?? null, function ($query, int $roleTypeId) {
                $query->whereHas('roleTypes', function ($query) use ($roleTypeId) {
                    $query->where('tb_role_type.id', $roleTypeId)
                        ->where('tb_user_role_type.status', 'active');
                });
            })
            ->when($filters['direktorat_id'] ?? null, function ($query, int $direktoratId) {
                $query->whereHas('unitKerja', function ($query) use ($direktoratId) {
                    $query->where('tb_unit_kerja.direktorat_id', $direktoratId)
                        ->where('tb_user_unit_kerja.status', 'active');
                });
            })
            ->when($filters['assignment'] ?? null, function ($query, string $assignment) {
                [$type, $id] = array_pad(explode(':', $assignment, 2), 2, null);
                $id = (int) $id;

                if ($type === 'unit') {
                    $query->whereHas('unitKerja', function ($query) use ($id) {
                        $query->where('tb_unit_kerja.id', $id)
                            ->where('tb_user_unit_kerja.status', 'active');
                    });
                }

                if ($type === 'komite') {
                    $query->whereHas('komite', function ($query) use ($id) {
                        $query->where('tb_komite.id', $id)
                            ->where('tb_user_komite.status', 'active');
                    });
                }
            })
            ->oldest('created_at')
            ->paginate(10)
            ->withQueryString();

        $roleTypes = RoleType::with(['role', 'type'])
            ->orderBy('id')
            ->get();

        $unitKerjas = UnitKerja::with('direktorat')
            ->orderBy('kode_unit')
            ->orderBy('nama_unit')
            ->get();

        $direktorats = Direktorat::with([
            'unitKerja' => fn ($query) => $query
                ->orderBy('kode_unit')
                ->orderBy('nama_unit'),
        ])
            ->orderBy('nama_direktorat')
            ->get();

        $komites = Komite::orderBy('kode_komite')
            ->orderBy('nama_komite')
            ->get();

        return view('layouts.administrasi.manajemen-user', compact(
            'users',
            'roleTypes',
            'unitKerjas',
            'direktorats',
            'komites',
            'filters'
        ));
    }

    public function store(Request $request)
    {
        $authUser = User::find(Auth::id());

        if (! $authUser?->canAccessManajemenUser()) {
            abort(403, 'Anda tidak memiliki akses untuk menambah user.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                'unique:users,email',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! User::isAllowedEmailDomain((string) $value)) {
                        $fail('Email harus menggunakan domain @bpjsketenagakerjaan.go.id.');
                    }
                },
            ],
            'password' => ['required', 'confirmed', Password::defaults()],
            'role_type_id' => ['required', 'integer', 'exists:tb_role_type,id'],
            'direktorat_id' => ['nullable', 'integer', 'exists:tb_direktorat,id'],
            'assignment' => ['nullable', 'string'],
        ]);

        $assignment = $this->resolveAssignment(
            $validated['assignment'] ?? null,
            $validated['direktorat_id'] ?? null
        );

        DB::transaction(function () use ($validated, $authUser, $assignment) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'status' => 'active',
                'email_verified_at' => now(),
            ]);

            $user->roleTypes()->attach($validated['role_type_id'], ['status' => 'active']);

            if ($assignment['type'] === 'unit') {
                $user->unitKerja()->attach($assignment['id'], ['status' => 'active']);
            }

            if ($assignment['type'] === 'komite') {
                $user->komite()->attach($assignment['id'], ['status' => 'active']);
            }

            LogActivity::create([
                'user_id' => $authUser->id,
                'type_code' => 'administrasi',
                'database_name' => 'sidewas',
                'table_name' => 'users',
                'record_key' => (string) $user->id,
                'action' => 'create_user',
                'description' => 'Super Admin menambahkan user baru melalui Manajemen User.',
                'new_values' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'role_type_id' => $validated['role_type_id'],
                    'direktorat_id' => $validated['direktorat_id'] ?? null,
                    'assignment' => $assignment,
                ],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        });

        return redirect()
            ->route('administrasi.manajemen-user.index')
            ->with('success', 'User berhasil ditambahkan.');
    }

    public function update(Request $request, User $user)
    {
        $authUser = User::with(['roleTypes.role', 'roleTypes.type'])->find(Auth::id());

        if (! $authUser || (! $authUser->canAccessManajemenUser() && ! $this->hasAnyAdminRole($authUser))) {
            abort(403, 'Anda tidak memiliki akses untuk mengedit user.');
        }

        if ((int) $authUser->id === (int) $user->id) {
            return back()->with('error', 'Anda tidak dapat mengedit akun yang sedang digunakan.');
        }

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => [
                'nullable',
                'email',
                'max:255',
                'unique:users,email,' . $user->id,
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value && ! User::isAllowedEmailDomain((string) $value)) {
                        $fail('Email harus menggunakan domain @bpjsketenagakerjaan.go.id.');
                    }
                },
            ],
            'role_type_ids' => ['required', 'array', 'min:1'],
            'role_type_ids.*' => ['integer', 'exists:tb_role_type,id'],
            'direktorat_id' => ['nullable', 'integer', 'exists:tb_direktorat,id'],
            'assignment' => ['nullable', 'string'],
        ]);

        $requestedRoleTypeIds = collect($validated['role_type_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $approvalTypeCode = $this->authorizeRoleChanges($authUser, $user, $requestedRoleTypeIds);

        $assignment = $this->resolveAssignment(
            $validated['assignment'] ?? null,
            $validated['direktorat_id'] ?? null
        );

        $payload = [
            'name' => $authUser->isSuperAdmin() ? ($validated['name'] ?? $user->name) : $user->name,
            'email' => $authUser->isSuperAdmin() ? ($validated['email'] ?? $user->email) : $user->email,
            'role_type_ids' => $requestedRoleTypeIds->all(),
            'direktorat_id' => $validated['direktorat_id'] ?? null,
            'assignment' => $assignment,
        ];

        if (! $authUser->isSuperAdmin()) {
            $this->createUpdateUserRequest($authUser, $user, $payload, $approvalTypeCode);

            return redirect()
                ->route('administrasi.manajemen-user.index')
                ->with('success', 'Perubahan user berhasil diajukan ke proses pengajuan.');
        }

        $this->applyUserUpdate($authUser, $user, $payload);

        return redirect()
            ->route('administrasi.manajemen-user.index')
            ->with('success', 'User berhasil diperbarui.');
    }

    public function destroy(User $user)
    {
        $authUser = User::with(['roleTypes.role', 'roleTypes.type'])->find(Auth::id());

        if (! $authUser || (! $authUser->canAccessManajemenUser() && ! $this->hasAnyAdminRole($authUser))) {
            abort(403, 'Anda tidak memiliki akses untuk menghapus user.');
        }

        if ((int) $authUser->id === (int) $user->id) {
            return back()->with('error', 'Anda tidak dapat menghapus akun yang sedang digunakan.');
        }

        $approvalTypeCode = $this->authorizeTargetUserAccess($authUser, $user, 'menghapus');

        if (! $authUser->isSuperAdmin()) {
            $this->createDeleteUserRequest($authUser, $user, $approvalTypeCode);

            return redirect()
                ->route('administrasi.manajemen-user.index')
                ->with('success', 'Penghapusan user berhasil diajukan ke proses pengajuan.');
        }

        $this->applyUserDelete($authUser, $user);

        return redirect()
            ->route('administrasi.manajemen-user.index')
            ->with('success', 'User berhasil dihapus.');
    }

    /**
     * @return array{type: 'unit'|'komite'|null, id: int|null}
     */
    private function resolveAssignment(?string $assignment, ?int $direktoratId): array
    {
        if (blank($assignment)) {
            return ['type' => null, 'id' => null];
        }

        [$type, $id] = array_pad(explode(':', $assignment, 2), 2, null);
        $id = (int) $id;

        if ($type === 'unit') {
            $unitKerja = UnitKerja::find($id);

            if (! $unitKerja) {
                throw ValidationException::withMessages([
                    'assignment' => 'Unit kerja tidak valid.',
                ]);
            }

            if ($direktoratId && (int) $unitKerja->direktorat_id !== $direktoratId) {
                throw ValidationException::withMessages([
                    'assignment' => 'Unit kerja tidak sesuai dengan direktorat yang dipilih.',
                ]);
            }

            return ['type' => 'unit', 'id' => $unitKerja->id];
        }

        if ($type === 'komite') {
            $komite = Komite::find($id);

            if (! $komite) {
                throw ValidationException::withMessages([
                    'assignment' => 'Komite tidak valid.',
                ]);
            }

            return ['type' => 'komite', 'id' => $komite->id];
        }

        throw ValidationException::withMessages([
            'assignment' => 'Unit kerja atau komite tidak valid.',
        ]);
    }

    private function hasAnyAdminRole(User $user): bool
    {
        return $user->roleTypes
            ->where('pivot.status', 'active')
            ->contains(fn ($roleType) => in_array($roleType->role?->name, ['admin', 'moderator'], true));
    }

    private function authorizeRoleChanges(User $authUser, User $targetUser, Collection $requestedRoleTypeIds): ?string
    {
        $currentRoleTypeIds = $targetUser->roleTypes()
            ->wherePivot('status', 'active')
            ->pluck('tb_role_type.id')
            ->map(fn ($id) => (int) $id)
            ->values();

        $manageableTypeCodes = collect();

        if (! $authUser->isSuperAdmin()) {
            $manageableTypeCodes = $this->manageableTypeCodesForTarget($authUser, $targetUser);

            if ($manageableTypeCodes->isEmpty()) {
                throw ValidationException::withMessages([
                    'role_type_ids' => 'Anda hanya dapat mengedit user dengan role di bawah level akses Anda pada modul yang sama.',
                ]);
            }
        }

        $changedRoleTypeIds = $requestedRoleTypeIds
            ->diff($currentRoleTypeIds)
            ->merge($currentRoleTypeIds->diff($requestedRoleTypeIds))
            ->unique()
            ->values();

        if ($changedRoleTypeIds->isEmpty()) {
            return $manageableTypeCodes->first() ?? null;
        }

        $changedRoleTypes = RoleType::with(['role', 'type'])
            ->whereIn('id', $changedRoleTypeIds)
            ->get();

        $changedTypeCodes = $changedRoleTypes
            ->pluck('type.code')
            ->filter()
            ->unique()
            ->values();

        if (! $authUser->isSuperAdmin() && $changedTypeCodes->count() > 1) {
            throw ValidationException::withMessages([
                'role_type_ids' => 'Admin atau Moderator hanya dapat mengajukan perubahan role untuk satu modul dalam satu pengajuan.',
            ]);
        }

        foreach ($changedRoleTypes as $roleType) {
            if (! $this->canManageRoleType($authUser, $roleType)) {
                throw ValidationException::withMessages([
                    'role_type_ids' => 'Anda tidak memiliki akses untuk menambah atau mengurangi role ' . $this->roleTypeLabel($roleType) . '.',
                ]);
            }
        }

        return $changedTypeCodes->first() ?? ($manageableTypeCodes->first() ?? null);
    }

    private function authorizeTargetUserAccess(User $authUser, User $targetUser, string $actionLabel): ?string
    {
        if ($authUser->isSuperAdmin()) {
            return null;
        }

        $manageableTypeCodes = $this->manageableTypeCodesForTarget($authUser, $targetUser);

        if ($manageableTypeCodes->isEmpty()) {
            throw ValidationException::withMessages([
                'user' => 'Anda hanya dapat ' . $actionLabel . ' user dengan role di bawah level akses Anda pada modul yang sama.',
            ]);
        }

        if ($manageableTypeCodes->count() > 1) {
            throw ValidationException::withMessages([
                'user' => 'Admin atau Moderator hanya dapat mengajukan penghapusan user untuk satu modul dalam satu pengajuan.',
            ]);
        }

        $targetTypeCodes = $targetUser->roleTypes()
            ->wherePivot('status', 'active')
            ->with('type')
            ->get()
            ->pluck('type.code')
            ->filter()
            ->unique()
            ->values();

        if ($targetTypeCodes->diff($manageableTypeCodes)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'user' => 'User ini memiliki akses modul lain, sehingga hanya Super Admin yang dapat menghapusnya.',
            ]);
        }

        return $manageableTypeCodes->first();
    }

    private function canManageRoleType(User $authUser, RoleType $roleType): bool
    {
        if ($authUser->isSuperAdmin()) {
            return true;
        }

        $targetTypeCode = $roleType->type?->code;
        $targetLevel = (int) ($roleType->role?->level ?? 0);

        if (! $targetTypeCode) {
            return false;
        }

        return $authUser->roleTypes
            ->where('pivot.status', 'active')
            ->contains(function ($authRoleType) use ($targetTypeCode, $targetLevel) {
                return in_array($authRoleType->role?->name, ['admin', 'moderator'], true)
                    && $authRoleType->type?->code === $targetTypeCode
                    && (int) ($authRoleType->role?->level ?? 0) > $targetLevel;
            });
    }

    private function manageableTypeCodesForTarget(User $authUser, User $targetUser): Collection
    {
        $targetRoleTypes = $targetUser->roleTypes()
            ->wherePivot('status', 'active')
            ->with(['role', 'type'])
            ->get();

        return $authUser->roleTypes
            ->where('pivot.status', 'active')
            ->filter(fn ($roleType) => in_array($roleType->role?->name, ['admin', 'moderator'], true))
            ->filter(function ($managerRoleType) use ($targetRoleTypes) {
                $managerTypeCode = $managerRoleType->type?->code;
                $managerLevel = (int) ($managerRoleType->role?->level ?? 0);

                if (! $managerTypeCode) {
                    return false;
                }

                $targetRoleTypesForModule = $targetRoleTypes
                    ->filter(fn ($targetRoleType) => $targetRoleType->type?->code === $managerTypeCode);

                return $targetRoleTypesForModule->isNotEmpty()
                    && $targetRoleTypesForModule
                        ->every(fn ($targetRoleType) => $managerLevel > (int) ($targetRoleType->role?->level ?? 0));
            })
            ->pluck('type.code')
            ->filter()
            ->unique()
            ->values();
    }

    /**
     * @param  array{name: string, email: string, role_type_ids: array<int>, direktorat_id: int|null, assignment: array{type: 'unit'|'komite'|null, id: int|null}}  $payload
     */
    private function createUpdateUserRequest(User $authUser, User $targetUser, array $payload, ?string $typeCode): void
    {
        if (! $typeCode) {
            throw ValidationException::withMessages([
                'role_type_ids' => 'Modul pengajuan tidak dapat ditentukan.',
            ]);
        }

        DeleteRequest::create([
            'type_code' => $typeCode,
            'database_name' => 'sidewas',
            'table_name' => 'users',
            'record_key' => (string) $targetUser->id,
            'record_label' => $targetUser->email,
            'reason' => json_encode([
                'action' => 'update_user',
                'payload' => $payload,
            ]),
            'requested_by' => $authUser->id,
            'status' => $this->requestStatusFor($authUser, $typeCode),
            'requested_at' => now(),
        ]);
    }

    private function createDeleteUserRequest(User $authUser, User $targetUser, ?string $typeCode): void
    {
        if (! $typeCode) {
            throw ValidationException::withMessages([
                'user' => 'Modul pengajuan tidak dapat ditentukan.',
            ]);
        }

        DeleteRequest::create([
            'type_code' => $typeCode,
            'database_name' => 'sidewas',
            'table_name' => 'users',
            'record_key' => (string) $targetUser->id,
            'record_label' => $targetUser->email,
            'reason' => json_encode([
                'action' => 'delete_user',
                'payload' => [
                    'id' => $targetUser->id,
                    'name' => $targetUser->name,
                    'email' => $targetUser->email,
                ],
            ]),
            'requested_by' => $authUser->id,
            'status' => $this->requestStatusFor($authUser, $typeCode),
            'requested_at' => now(),
        ]);
    }

    private function requestStatusFor(User $authUser, string $typeCode): string
    {
        $isAdminForType = $authUser->roleTypes
            ->where('pivot.status', 'active')
            ->where('type.code', $typeCode)
            ->contains(fn ($roleType) => $roleType->role?->name === 'admin');

        return $isAdminForType
            ? 'pending_super_admin_approval'
            : 'pending_admin_verification';
    }

    /**
     * @param  array{name: string, email: string, role_type_ids: array<int>, direktorat_id: int|null, assignment: array{type: 'unit'|'komite'|null, id: int|null}}  $payload
     */
    private function applyUserUpdate(User $authUser, User $user, array $payload): void
    {
        DB::transaction(function () use ($user, $authUser, $payload) {
            $oldValues = [
                'name' => $user->name,
                'email' => $user->email,
                'role_type_ids' => $user->roleTypes()
                    ->wherePivot('status', 'active')
                    ->pluck('tb_role_type.id')
                    ->map(fn ($id) => (int) $id)
                    ->values()
                    ->all(),
                'unit_kerja_ids' => $user->unitKerja()
                    ->wherePivot('status', 'active')
                    ->pluck('tb_unit_kerja.id')
                    ->map(fn ($id) => (int) $id)
                    ->values()
                    ->all(),
                'komite_ids' => $user->komite()
                    ->wherePivot('status', 'active')
                    ->pluck('tb_komite.id')
                    ->map(fn ($id) => (int) $id)
                    ->values()
                    ->all(),
            ];

            $user->update([
                'name' => $payload['name'],
                'email' => $payload['email'],
            ]);

            $user->roleTypes()->sync(
                collect($payload['role_type_ids'])
                    ->mapWithKeys(fn ($id) => [(int) $id => ['status' => 'active']])
                    ->all()
            );

            $user->unitKerja()->sync([]);
            $user->komite()->sync([]);

            if ($payload['assignment']['type'] === 'unit') {
                $user->unitKerja()->attach($payload['assignment']['id'], ['status' => 'active']);
            }

            if ($payload['assignment']['type'] === 'komite') {
                $user->komite()->attach($payload['assignment']['id'], ['status' => 'active']);
            }

            LogActivity::create([
                'user_id' => $authUser->id,
                'type_code' => 'administrasi',
                'database_name' => 'sidewas',
                'table_name' => 'users',
                'record_key' => (string) $user->id,
                'action' => 'update_user',
                'description' => 'User mengubah akses user melalui Manajemen User.',
                'old_values' => $oldValues,
                'new_values' => $payload,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        });
    }

    private function applyUserDelete(User $authUser, User $user): void
    {
        DB::transaction(function () use ($authUser, $user) {
            $oldValues = [
                'user' => $user->load(['roleTypes', 'unitKerja', 'komite'])->toArray(),
            ];

            $recordKey = (string) $user->id;

            $user->roleTypes()->sync([]);
            $user->unitKerja()->sync([]);
            $user->komite()->sync([]);
            $user->delete();

            LogActivity::create([
                'user_id' => $authUser->id,
                'type_code' => 'administrasi',
                'database_name' => 'sidewas',
                'table_name' => 'users',
                'record_key' => $recordKey,
                'action' => 'delete_user',
                'description' => 'User menghapus akun melalui Manajemen User.',
                'old_values' => $oldValues,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        });
    }

    private function roleTypeLabel(RoleType $roleType): string
    {
        if ($roleType->role?->name === 'super_admin') {
            return 'Super Admin';
        }

        $roleLabel = match ($roleType->role?->name) {
            'admin' => 'Admin',
            'moderator' => 'Moderator',
            'pic' => 'PIC',
            'viewer' => 'Viewer',
            default => ucwords(str_replace('_', ' ', (string) $roleType->role?->name)),
        };

        return trim($roleLabel . ' ' . ($roleType->type?->name ?? ''));
    }
}
