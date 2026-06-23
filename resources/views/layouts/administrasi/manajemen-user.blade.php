<x-app-layout>
    @php
        $authUser = \App\Models\User::find(auth()->id());

        $direktoratOptions = $direktorats
            ->map(
                fn($direktorat) => [
                    'id' => $direktorat->id,
                    'label' => trim(($direktorat->kode_direktorat ? $direktorat->kode_direktorat . ' - ' : '') . $direktorat->nama_direktorat),
                    'unit_kerja' => $direktorat->unitKerja
                        ->map(
                            fn($unit) => [
                                'value' => 'unit:' . $unit->id,
                                'label' => trim(($unit->kode_unit ?? '-') . ' - ' . $unit->nama_unit),
                            ],
                        )
                        ->values(),
                ],
            )
            ->values();

        $komiteOptions = $komites
            ->map(
                fn($komite) => [
                    'value' => 'komite:' . $komite->id,
                    'label' => trim(($komite->kode_komite ?? '-') . ' - ' . $komite->nama_komite),
                ],
            )
            ->values();

        $filterAssignmentOptions = $unitKerjas
            ->map(
                fn($unit) => [
                    'value' => 'unit:' . $unit->id,
                    'label' => trim(($unit->kode_unit ?? '-') . ' - ' . $unit->nama_unit),
                    'group' => 'Unit Kerja',
                ],
            )
            ->merge(
                $komites->map(
                    fn($komite) => [
                        'value' => 'komite:' . $komite->id,
                        'label' => trim(($komite->kode_komite ?? '-') . ' - ' . $komite->nama_komite),
                        'group' => 'Komite',
                    ],
                ),
            )
            ->values();

        $formatRoleLabel = function ($roleType) {
            $roleName = $roleType?->role?->name;
            $typeName = $roleType?->type?->name;

            if ($roleName === 'super_admin') {
                return 'Super Admin';
            }

            $roleLabel =
                [
                    'admin' => 'Admin',
                    'moderator' => 'Moderator',
                    'pic' => 'PIC',
                    'viewer' => 'Viewer',
                ][$roleName] ?? ucwords(str_replace('_', ' ', (string) $roleName));

            return trim($roleLabel . ' ' . ($typeName ?: ''));
        };

        $roleColor = function ($roleType) {
            return match ($roleType?->role?->name) {
                'super_admin' => '#2377b9',
                'admin' => '#6bb17e',
                'moderator' => '#c8e079',
                'pic' => '#f59e0b',
                'viewer' => '#64748b',
                default => '#64748b',
            };
        };

        $roleTextColor = function ($roleType) {
            return $roleType?->role?->name === 'moderator' ? 'text-slate-800' : 'text-white';
        };

        $formatDirektoratLabel = function ($user) {
            $unitDirektorats = $user->unitKerja
                ->where('pivot.status', 'active')
                ->map(fn($unit) => $unit->direktorat?->nama_direktorat)
                ->filter()
                ->values()
                ->toBase();

            $komiteDirektorats = $user->komite->where('pivot.status', 'active')->isNotEmpty()
                ? collect(['Dewan Pengawas'])
                : collect();

            return $unitDirektorats->merge($komiteDirektorats)->unique()->values();
        };

        $formatUnitLabel = function ($user) {
            $units = $user->unitKerja
                ->where('pivot.status', 'active')
                ->map(fn($unit) => trim(($unit->kode_unit ?? '-') . ' - ' . ($unit->nama_unit ?? '-')))
                ->filter()
                ->values()
                ->toBase();

            $komites = $user->komite
                ->where('pivot.status', 'active')
                ->map(fn($komite) => trim(($komite->kode_komite ?? '-') . ' - ' . ($komite->nama_komite ?? '-')))
                ->filter()
                ->values()
                ->toBase();

            return $units->merge($komites)->values();
        };
    @endphp

    <div x-data="{
        openCreateModal: @js($authUser?->isSuperAdmin() && $errors->any()),
        openEditModal: false,
        editUser: null,
        selectedDirektoratId: @js(old('direktorat_id', '')),
        assignment: @js(old('assignment', '')),
        editDirektoratId: '',
        editAssignment: '',
        editRoleTypeIds: [],
        direktorats: @js($direktoratOptions),
        komites: @js($komiteOptions),

        get selectedDirektorat() {
            return this.direktorats.find((direktorat) => String(direktorat.id) === String(this.selectedDirektoratId));
        },

        get assignmentOptions() {
            const unitKerja = this.selectedDirektorat?.unit_kerja ?? [];
            return [
                ...unitKerja.map((unit) => ({ ...unit, group: 'Unit Kerja' })),
                ...this.komites.map((komite) => ({ ...komite, group: 'Komite' })),
            ];
        },

        get selectedEditDirektorat() {
            return this.direktorats.find((direktorat) => String(direktorat.id) === String(this.editDirektoratId));
        },

        get editAssignmentOptions() {
            const unitKerja = this.selectedEditDirektorat?.unit_kerja ?? [];
            return [
                ...unitKerja.map((unit) => ({ ...unit, group: 'Unit Kerja' })),
                ...this.komites.map((komite) => ({ ...komite, group: 'Komite' })),
            ];
        },

        resetAssignment() {
            this.assignment = '';
        },

        resetEditAssignment() {
            this.editAssignment = '';
        },

        openEditModalFor(user) {
            this.editUser = user;
            this.editDirektoratId = user.direktorat_id ?? '';
            this.editAssignment = user.assignment ?? '';
            this.editRoleTypeIds = (user.role_type_ids ?? []).map((id) => String(id));
            this.openEditModal = true;
        },
    }" class="space-y-6">
        <div class="rounded-2xl border border-blue-100 bg-white p-6 shadow-sm">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide" style="color: #2377b9;">
                    Administrasi
                </p>

                <h1 class="mt-2 text-3xl font-bold text-slate-800">
                    Manajemen User
                </h1>

                <p class="mt-2 text-sm text-slate-500">
                    Halaman ini digunakan untuk memonitoring user yang menggunakan website SIDEWAS.
                </p>
            </div>
        </div>

        <div class="rounded-2xl border border-blue-100 bg-white p-6 shadow-sm">
            <form method="GET" action="{{ route('administrasi.manajemen-user.index') }}">
                <div class="grid gap-4 lg:grid-cols-4">
                    <div>
                        <label class="mb-2 block text-sm font-semibold text-slate-700">
                            Keyword
                        </label>
                        <input type="text" name="keyword" value="{{ $filters['keyword'] ?? '' }}"
                            class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            placeholder="Cari nama atau email">
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-semibold text-slate-700">
                            Role
                        </label>
                        <select name="role_type_id"
                            class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Semua Role</option>
                            @foreach ($roleTypes as $roleType)
                                <option value="{{ $roleType->id }}" @selected((int) ($filters['role_type_id'] ?? 0) === $roleType->id)>
                                    {{ $formatRoleLabel($roleType) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-semibold text-slate-700">
                            Direktorat
                        </label>
                        <select name="direktorat_id"
                            class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Semua Direktorat</option>
                            @foreach ($direktorats as $direktorat)
                                <option value="{{ $direktorat->id }}" @selected((int) ($filters['direktorat_id'] ?? 0) === $direktorat->id)>
                                    {{ trim(($direktorat->kode_direktorat ? $direktorat->kode_direktorat . ' - ' : '') . $direktorat->nama_direktorat) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-semibold text-slate-700">
                            Unit/Komite
                        </label>
                        <select name="assignment"
                            class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Semua Unit/Komite</option>
                            @foreach ($filterAssignmentOptions as $option)
                                <option value="{{ $option['value'] }}" @selected(($filters['assignment'] ?? '') === $option['value'])>
                                    {{ $option['group'] }} - {{ $option['label'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-slate-500">
                        Isi salah satu filter untuk mencari user tertentu.
                    </p>

                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('administrasi.manajemen-user.index') }}"
                            class="rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                            Reset
                        </a>

                        <button type="submit"
                            class="rounded-xl px-5 py-3 text-sm font-bold text-white shadow-sm hover:opacity-90"
                            style="background-color: #2377b9;">
                            Terapkan Filter
                        </button>
                    </div>
                </div>
            </form>
        </div>

        @if (session('success'))
            <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-700">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
                <p>Data user belum bisa disimpan. Periksa kembali input yang diisi.</p>
            </div>
        @endif

        <div class="overflow-hidden rounded-2xl border border-blue-100 bg-white shadow-sm">
            <div class="flex flex-col gap-4 border-b border-blue-50 px-6 py-5 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 class="text-lg font-bold text-slate-800">
                        Daftar User
                    </h2>

                    <p class="mt-1 text-sm text-slate-500">
                        User diurutkan dari yang paling awal registrasi.
                    </p>
                </div>

                @if ($authUser?->isSuperAdmin())
                    <button type="button" @click="openCreateModal = true"
                        class="inline-flex items-center justify-center rounded-xl px-5 py-3 text-sm font-bold text-white shadow-sm hover:opacity-90"
                        style="background-color: #2377b9;">
                        Tambah User
                    </button>
                @endif
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-600">
                                No
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-600">
                                Email
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-600">
                                Role
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-600">
                                Direktorat
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-600">
                                Unit/Komite
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-600">
                                Aktivitas
                            </th>
                            <th class="px-6 py-4 text-center text-xs font-bold uppercase tracking-wider text-slate-600">
                                Aksi
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse ($users as $user)
                            @php
                                $userRoleTypes = $user->roleTypes->where('pivot.status', 'active')->values();
                                $direktoratLabels = $formatDirektoratLabel($user);
                                $unitLabels = $formatUnitLabel($user);
                                $latestLog = $user->latestLog;
                                $activeUnit = $user->unitKerja->where('pivot.status', 'active')->first();
                                $activeKomite = $user->komite->where('pivot.status', 'active')->first();
                                $editUserPayload = [
                                    'id' => $user->id,
                                    'name' => $user->name,
                                    'email' => $user->email,
                                    'role_type_ids' => $userRoleTypes->pluck('id')->values()->all(),
                                    'direktorat_id' => $activeUnit?->direktorat_id ? (string) $activeUnit->direktorat_id : '',
                                    'assignment' => $activeUnit
                                        ? 'unit:' . $activeUnit->id
                                        : ($activeKomite
                                            ? 'komite:' . $activeKomite->id
                                            : ''),
                                ];
                            @endphp

                            <tr class="hover:bg-blue-50/40">
                                <td class="px-6 py-5 align-top text-sm font-semibold text-slate-700">
                                    {{ $users->firstItem() + $loop->index }}
                                </td>

                                <td class="px-6 py-5 align-top">
                                    <p class="text-sm font-bold text-slate-800">
                                        {{ $user->email }}
                                    </p>
                                    <p class="mt-1 text-xs text-slate-500">
                                        {{ $user->name }}
                                    </p>
                                </td>

                                <td class="px-6 py-5 align-top">
                                    <div class="flex max-w-xs flex-wrap gap-2">
                                        @forelse ($userRoleTypes as $roleType)
                                            <span
                                                class="rounded-full px-3 py-1 text-xs font-bold {{ $roleTextColor($roleType) }}"
                                                style="background-color: {{ $roleColor($roleType) }};">
                                                {{ $formatRoleLabel($roleType) }}
                                            </span>
                                        @empty
                                            <span class="text-sm text-slate-400">-</span>
                                        @endforelse
                                    </div>
                                </td>

                                <td class="px-6 py-5 align-top">
                                    <div class="max-w-xs space-y-1">
                                        @forelse ($direktoratLabels as $direktoratLabel)
                                            <p class="text-sm text-slate-700">
                                                {{ $direktoratLabel }}
                                            </p>
                                        @empty
                                            <p class="text-sm text-slate-400">-</p>
                                        @endforelse
                                    </div>
                                </td>

                                <td class="px-6 py-5 align-top">
                                    <div class="max-w-sm space-y-1">
                                        @forelse ($unitLabels as $unitLabel)
                                            <p class="text-sm text-slate-700">
                                                {{ $unitLabel }}
                                            </p>
                                        @empty
                                            <p class="text-sm text-slate-400">-</p>
                                        @endforelse
                                    </div>
                                </td>

                                <td class="px-6 py-5 align-top">
                                    @if ($latestLog)
                                        <p class="text-sm font-semibold text-slate-800">
                                            {{ $latestLog->description ?: ucwords(str_replace('_', ' ', $latestLog->action)) }}
                                        </p>
                                        <p class="mt-1 text-xs text-slate-500">
                                            {{ $latestLog->created_at?->format('d/m/Y H:i') ?? '-' }}
                                        </p>
                                    @else
                                        <p class="text-sm text-slate-400">-</p>
                                    @endif
                                </td>

                                <td class="px-6 py-5 align-top">
                                    <div class="flex flex-wrap justify-center gap-2">
                                        @if ((int) $authUser->id !== (int) $user->id)
                                            <button type="button"
                                                @click="openEditModalFor(@js($editUserPayload))"
                                                class="rounded-lg px-4 py-2 text-xs font-bold text-white shadow-sm hover:opacity-90"
                                                style="background-color: #2377b9;">
                                                Edit
                                            </button>

                                            <form method="POST"
                                                action="{{ route('administrasi.manajemen-user.destroy', $user->id) }}"
                                                onsubmit="return confirm('Hapus user {{ $user->email }}? Jika bukan Super Admin, penghapusan akan masuk ke pengajuan.')">
                                                @csrf
                                                @method('DELETE')

                                                <button type="submit"
                                                    class="rounded-lg bg-red-500 px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-red-600">
                                                    Hapus
                                                </button>
                                            </form>
                                        @else
                                            <span class="rounded-lg bg-slate-100 px-4 py-2 text-xs font-bold text-slate-500">
                                                Akun Aktif
                                            </span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center">
                                    <p class="text-sm font-semibold text-slate-600">
                                        Belum ada user terdaftar.
                                    </p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div
                class="flex flex-col gap-3 border-t border-slate-100 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-slate-500">
                    Menampilkan
                    <span class="font-semibold text-slate-700">{{ $users->firstItem() ?? 0 }}</span>
                    -
                    <span class="font-semibold text-slate-700">{{ $users->lastItem() ?? 0 }}</span>
                    dari
                    <span class="font-semibold text-slate-700">{{ $users->total() }}</span>
                    entri
                </p>

                @include('layouts.partials.compact-pagination', ['paginator' => $users])
            </div>
        </div>

        @if ($authUser?->isSuperAdmin())
            <div x-show="openCreateModal" x-transition.opacity
                class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/60 px-4 py-8"
                style="display: none;">
            <div @click.outside="openCreateModal = false" x-transition
                class="w-full max-w-4xl overflow-hidden rounded-2xl bg-white shadow-2xl">
                <div class="flex items-start justify-between border-b border-slate-100 px-6 py-5">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-wide" style="color: #2377b9;">
                            Form User
                        </p>

                        <h2 class="mt-1 text-2xl font-bold text-slate-800">
                            Tambah User
                        </h2>

                        <p class="mt-1 text-sm text-slate-500">
                            Pilih direktorat untuk memunculkan unit kerja. Komite tersedia di semua direktorat.
                        </p>
                    </div>

                    <button type="button" @click="openCreateModal = false"
                        class="rounded-xl p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form method="POST" action="{{ route('administrasi.manajemen-user.store') }}" class="px-6 py-6">
                    @csrf

                    <div class="grid gap-5 lg:grid-cols-2">
                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-700">
                                Nama
                            </label>
                            <input type="text" name="name" value="{{ old('name') }}" required
                                class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                placeholder="Nama user">
                            @error('name')
                                <p class="mt-1 text-xs font-semibold text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-700">
                                Email
                            </label>
                            <input type="email" name="email" value="{{ old('email') }}" required
                                class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                placeholder="nama@bpjsketenagakerjaan.go.id">
                            @error('email')
                                <p class="mt-1 text-xs font-semibold text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-700">
                                Password
                            </label>
                            <input type="password" name="password" required
                                class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                placeholder="Password awal user">
                            @error('password')
                                <p class="mt-1 text-xs font-semibold text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-700">
                                Konfirmasi Password
                            </label>
                            <input type="password" name="password_confirmation" required
                                class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                placeholder="Ulangi password">
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-700">
                                Role
                            </label>
                            <select name="role_type_id" required
                                class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Pilih Role</option>
                                @foreach ($roleTypes as $roleType)
                                    <option value="{{ $roleType->id }}" @selected((int) old('role_type_id') === $roleType->id)>
                                        {{ $formatRoleLabel($roleType) }}
                                    </option>
                                @endforeach
                            </select>
                            @error('role_type_id')
                                <p class="mt-1 text-xs font-semibold text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-700">
                                Direktorat
                            </label>
                            <select name="direktorat_id" x-model="selectedDirektoratId" @change="resetAssignment()"
                                class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Pilih Direktorat</option>
                                <template x-for="direktorat in direktorats" :key="direktorat.id">
                                    <option :value="direktorat.id" x-text="direktorat.label"></option>
                                </template>
                            </select>
                            @error('direktorat_id')
                                <p class="mt-1 text-xs font-semibold text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="lg:col-span-2">
                            <label class="mb-2 block text-sm font-semibold text-slate-700">
                                Unit Kerja / Komite
                            </label>
                            <select name="assignment" x-model="assignment"
                                class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Pilih Unit Kerja / Komite</option>
                                <template x-for="option in assignmentOptions" :key="option.value">
                                    <option :value="option.value" x-text="`${option.group} - ${option.label}`"></option>
                                </template>
                            </select>
                            @error('assignment')
                                <p class="mt-1 text-xs font-semibold text-red-600">{{ $message }}</p>
                            @enderror
                            <p class="mt-1 text-xs text-slate-500">
                                User hanya dipasang ke satu unit kerja atau satu komite.
                            </p>
                        </div>
                    </div>

                    <div class="mt-8 flex justify-end gap-3 border-t border-slate-100 pt-5">
                        <button type="button" @click="openCreateModal = false"
                            class="rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                            Batal
                        </button>

                        <button type="submit"
                            class="rounded-xl px-5 py-3 text-sm font-semibold text-white shadow-sm hover:opacity-90"
                            style="background-color: #2377b9;">
                            Simpan User
                        </button>
                    </div>
                </form>
            </div>
            </div>
        @endif

        <div x-show="openEditModal" x-transition.opacity
            class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/60 px-4 py-8"
            style="display: none;">
            <div @click.outside="openEditModal = false" x-transition
                class="w-full max-w-4xl overflow-hidden rounded-2xl bg-white shadow-2xl">
                <div class="flex items-start justify-between border-b border-slate-100 px-6 py-5">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-wide" style="color: #2377b9;">
                            Form User
                        </p>

                        <h2 class="mt-1 text-2xl font-bold text-slate-800">
                            Edit User
                        </h2>

                        <p class="mt-1 text-sm text-slate-500">
                            <span x-text="editUser?.email ?? '-'"></span>
                        </p>
                    </div>

                    <button type="button" @click="openEditModal = false"
                        class="rounded-xl p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form method="POST" :action="`/administrasi/manajemen-user/${editUser?.id}`" class="px-6 py-6">
                    @csrf
                    @method('PATCH')

                    <div class="grid gap-5 lg:grid-cols-2">
                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-700">
                                Nama
                            </label>
                            <input type="text" name="name" :value="editUser?.name ?? ''"
                                @if (! $authUser?->isSuperAdmin()) readonly @endif
                                class="w-full rounded-xl border-slate-300 text-sm font-semibold shadow-sm focus:border-blue-500 focus:ring-blue-500 @if (! $authUser?->isSuperAdmin()) bg-slate-50 text-slate-700 @endif">
                            @error('name')
                                <p class="mt-1 text-xs font-semibold text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-700">
                                Email
                            </label>
                            <input type="email" name="email" :value="editUser?.email ?? ''"
                                @if (! $authUser?->isSuperAdmin()) readonly @endif
                                class="w-full rounded-xl border-slate-300 text-sm font-semibold shadow-sm focus:border-blue-500 focus:ring-blue-500 @if (! $authUser?->isSuperAdmin()) bg-slate-50 text-slate-700 @endif">
                            @error('email')
                                <p class="mt-1 text-xs font-semibold text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="lg:col-span-2">
                            <label class="mb-2 block text-sm font-semibold text-slate-700">
                                Role Akses
                            </label>
                            <div class="grid gap-3 rounded-xl border border-slate-200 p-4 md:grid-cols-2">
                                @foreach ($roleTypes as $roleType)
                                    <label class="flex items-start gap-3 rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
                                        <input type="checkbox" name="role_type_ids[]"
                                            value="{{ $roleType->id }}" x-model="editRoleTypeIds"
                                            class="mt-1 rounded border-slate-300 text-blue-600 shadow-sm focus:ring-blue-500">
                                        <span>
                                            <span class="inline-flex rounded-full px-3 py-1 text-xs font-bold {{ $roleTextColor($roleType) }}"
                                                style="background-color: {{ $roleColor($roleType) }};">
                                                {{ $formatRoleLabel($roleType) }}
                                            </span>
                                            <span class="mt-1 block text-xs text-slate-500">
                                                {{ $roleType->keterangan ?? 'Role akses user.' }}
                                            </span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                            @error('role_type_ids')
                                <p class="mt-1 text-xs font-semibold text-red-600">{{ $message }}</p>
                            @enderror
                            <p class="mt-2 text-xs text-slate-500">
                                Super Admin dapat mengubah semua role. Admin modul hanya dapat mengubah role modulnya dengan level lebih rendah.
                            </p>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-700">
                                Direktorat
                            </label>
                            <select name="direktorat_id" x-model="editDirektoratId" @change="resetEditAssignment()"
                                class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Pilih Direktorat</option>
                                <template x-for="direktorat in direktorats" :key="`edit-${direktorat.id}`">
                                    <option :value="direktorat.id" x-text="direktorat.label"></option>
                                </template>
                            </select>
                            @error('direktorat_id')
                                <p class="mt-1 text-xs font-semibold text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-700">
                                Unit Kerja / Komite
                            </label>
                            <select name="assignment" x-model="editAssignment"
                                class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Pilih Unit Kerja / Komite</option>
                                <template x-for="option in editAssignmentOptions" :key="`edit-${option.value}`">
                                    <option :value="option.value" x-text="`${option.group} - ${option.label}`"></option>
                                </template>
                            </select>
                            @error('assignment')
                                <p class="mt-1 text-xs font-semibold text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="mt-8 flex justify-end gap-3 border-t border-slate-100 pt-5">
                        <button type="button" @click="openEditModal = false"
                            class="rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                            Batal
                        </button>

                        <button type="submit"
                            class="rounded-xl px-5 py-3 text-sm font-semibold text-white shadow-sm hover:opacity-90"
                            style="background-color: #2377b9;">
                            Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
