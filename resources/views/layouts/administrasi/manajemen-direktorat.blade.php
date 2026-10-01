<x-app-layout>
    <div x-data="{
        openUnits: {},
        editDirektorat: @js([
            'action' => old('_form') === 'edit-direktorat' ? old('_edit_action', '') : '',
            'nama_direktorat' => old('_form') === 'edit-direktorat' ? old('nama_direktorat', '') : '',
            'keterangan' => old('_form') === 'edit-direktorat' ? old('keterangan', '') : '',
        ]),
        editUnit: @js([
            'action' => old('_form') === 'edit-unit' ? old('_edit_action', '') : '',
            'direktorat_id' => old('_form') === 'edit-unit' ? old('direktorat_id', '') : '',
            'nama_unit' => old('_form') === 'edit-unit' ? old('nama_unit', '') : '',
            'kode_unit' => old('_form') === 'edit-unit' ? old('kode_unit', '') : '',
            'keterangan' => old('_form') === 'edit-unit' ? old('keterangan', '') : '',
        ]),
    }" class="space-y-6">
        <div class="rounded-2xl border border-blue-100 bg-white p-6 shadow-sm">
            <p class="text-sm font-semibold uppercase tracking-wide text-sky-700">Administrasi</p>
            <div class="mt-2 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-slate-800">Manajemen Direktorat</h1>
                    <p class="mt-2 text-sm text-slate-500">Kelola direktorat dan unit kerja. Penugasan user dilakukan melalui Manajemen User.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" @click="$dispatch('open-modal', 'create-direktorat')"
                        class="rounded-xl px-4 py-3 text-sm font-semibold shadow-sm transition hover:opacity-90"
                        style="background-color: #2377b9; border: 1px solid #2377b9; color: #fff;">Tambah Direktorat</button>
                    <button type="button" @click="$dispatch('open-modal', 'create-unit')"
                        class="rounded-xl border border-sky-700 bg-white px-4 py-3 text-sm font-semibold text-sky-700 hover:bg-sky-50">Tambah Unit Kerja</button>
                </div>
            </div>
        </div>

        @if (session('success'))
            <div class="rounded-xl border border-green-200 bg-green-50 px-5 py-4 text-sm text-green-800">{{ session('success') }}</div>
        @endif
        @if ($errors->any() && ! in_array(old('_form'), ['create-direktorat', 'create-unit', 'edit-direktorat', 'edit-unit'], true))
            <div class="rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-800" role="alert">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="GET" action="{{ route('administrasi.manajemen-direktorat.index') }}" class="flex flex-wrap items-end gap-3 rounded-2xl border border-blue-100 bg-white p-5 shadow-sm">
            <label class="min-w-52 flex-1 text-sm font-medium text-slate-700">Cari direktorat atau unit kerja
                <input name="keyword" value="{{ $filters['keyword'] ?? '' }}" class="mt-1 w-full rounded-xl border-slate-300 text-sm" placeholder="Nama direktorat, unit kerja, atau kode unit" />
            </label>
            <label class="text-sm font-medium text-slate-700">Status direktorat
                <select name="status" class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                    <option value="">Semua</option>
                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>Aktif</option>
                    <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Nonaktif</option>
                </select>
            </label>
            <button class="rounded-xl px-5 py-3 text-sm font-semibold shadow-sm transition hover:opacity-90"
                style="background-color: #2377b9; border: 1px solid #2377b9; color: #fff;">Cari</button>
            <a href="{{ route('administrasi.manajemen-direktorat.index') }}" class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700">Reset</a>
        </form>

        <div class="rounded-2xl border border-blue-100 bg-white shadow-sm" style="height: auto; max-height: none; overflow: visible;">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">
                    <tr>
                        <th scope="col" class="px-5 py-4">Nama Direktorat</th>
                        <th scope="col" class="px-5 py-4">Total Unit Kerja</th>
                        <th scope="col" class="px-5 py-4">Status</th>
                        <th scope="col" class="px-5 py-4 text-right">Aksi</th>
                    </tr>
                </thead>
                @forelse ($direktorats as $direktorat)
                    @php
                        $editDirektoratPayload = [
                            'action' => route('administrasi.manajemen-direktorat.update', $direktorat),
                            'nama_direktorat' => $direktorat->nama_direktorat,
                            'keterangan' => $direktorat->keterangan ?? '',
                        ];
                    @endphp
                    <tbody class="divide-y divide-slate-100">
                        <tr class="hover:bg-slate-50/70">
                            <td class="px-5 py-4 font-semibold text-slate-800">{{ $direktorat->nama_direktorat }}</td>
                            <td class="px-5 py-4 text-slate-700">{{ $direktorat->unitKerja->count() }}</td>
                            <td class="px-5 py-4">
                                <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $direktorat->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-slate-100 text-slate-600' }}">
                                    {{ $direktorat->status === 'active' ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex flex-wrap items-center justify-end gap-2">
                                    <button type="button" @click="editDirektorat = @js($editDirektoratPayload); $dispatch('open-modal', 'edit-direktorat')"
                                        class="rounded-lg border border-slate-300 px-3 py-2 font-semibold text-slate-700 hover:bg-slate-50">Edit</button>
                                    <form method="POST" action="{{ route('administrasi.manajemen-direktorat.status', $direktorat) }}">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="status" value="{{ $direktorat->status === 'active' ? 'inactive' : 'active' }}" />
                                        <button class="rounded-lg border border-slate-300 px-3 py-2 font-semibold text-slate-700 hover:bg-slate-50">{{ $direktorat->status === 'active' ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                    </form>
                                    <form method="POST" action="{{ route('administrasi.manajemen-direktorat.destroy', $direktorat) }}" onsubmit="return confirm('Hapus direktorat yang belum pernah digunakan?')">
                                        @csrf @method('DELETE')
                                        <button class="rounded-lg border border-red-200 px-3 py-2 font-semibold text-red-700 hover:bg-red-50">Hapus</button>
                                    </form>
                                    <button type="button" @click="openUnits[{{ $direktorat->id }}] = ! openUnits[{{ $direktorat->id }}]"
                                        :aria-expanded="Boolean(openUnits[{{ $direktorat->id }}]).toString()" aria-controls="unit-kerja-{{ $direktorat->id }}"
                                        class="inline-flex items-center gap-2 rounded-lg border border-sky-200 px-3 py-2 font-semibold text-sky-700 hover:bg-sky-50">
                                        <span x-text="openUnits[{{ $direktorat->id }}] ? 'Sembunyikan Unit Kerja' : 'Lihat Unit Kerja'">Lihat Unit Kerja</span>
                                        <svg class="h-4 w-4"
                                            :style="{ transform: openUnits[{{ $direktorat->id }}] ? 'rotate(180deg)' : 'rotate(0deg)' }"
                                            viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                            <path d="m5 7.5 5 5 5-5" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr id="unit-kerja-{{ $direktorat->id }}" x-show="openUnits[{{ $direktorat->id }}]" style="display: none;">
                            <td colspan="4" class="bg-slate-50 px-5 py-5">
                                <div class="rounded-xl border border-slate-200 bg-white">
                                    <table class="min-w-full divide-y divide-slate-100 text-sm">
                                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">
                                            <tr>
                                                <th scope="col" class="px-4 py-3">Unit Kerja</th>
                                                <th scope="col" class="px-4 py-3">User Aktif</th>
                                                <th scope="col" class="px-4 py-3">Status</th>
                                                <th scope="col" class="px-4 py-3 text-right">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100">
                                            @forelse ($direktorat->unitKerja as $unit)
                                                @php
                                                    $editUnitPayload = [
                                                        'action' => route('administrasi.manajemen-direktorat.unit.update', $unit),
                                                        'direktorat_id' => $unit->direktorat_id,
                                                        'nama_unit' => $unit->nama_unit,
                                                        'kode_unit' => $unit->kode_unit ?? '',
                                                        'keterangan' => $unit->keterangan ?? '',
                                                    ];
                                                @endphp
                                                <tr>
                                                    <td class="px-4 py-3 font-medium text-slate-800">{{ $unit->kode_unit ? $unit->kode_unit . ' - ' : '' }}{{ $unit->nama_unit }}</td>
                                                    <td class="px-4 py-3 text-slate-700">{{ $unit->active_users_count }}</td>
                                                    <td class="px-4 py-3 text-slate-700">{{ $unit->status === 'active' ? 'Aktif' : 'Nonaktif' }}</td>
                                                    <td class="px-4 py-3">
                                                        <div class="flex flex-wrap justify-end gap-2">
                                                            <button type="button" @click="editUnit = @js($editUnitPayload); $dispatch('open-modal', 'edit-unit')"
                                                                class="rounded-lg border border-slate-300 px-3 py-2 font-semibold text-slate-700">Edit</button>
                                                            <form method="POST" action="{{ route('administrasi.manajemen-direktorat.unit.status', $unit) }}">
                                                                @csrf @method('PATCH')
                                                                <input type="hidden" name="status" value="{{ $unit->status === 'active' ? 'inactive' : 'active' }}" />
                                                                <button class="rounded-lg border border-slate-300 px-3 py-2 font-semibold text-slate-700">{{ $unit->status === 'active' ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                                            </form>
                                                            <form method="POST" action="{{ route('administrasi.manajemen-direktorat.unit.destroy', $unit) }}" onsubmit="return confirm('Hapus unit kerja yang belum pernah digunakan?')">
                                                                @csrf @method('DELETE')
                                                                <button class="rounded-lg border border-red-200 px-3 py-2 font-semibold text-red-700">Hapus</button>
                                                            </form>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="4" class="px-4 py-5 text-center text-slate-500">Belum ada unit kerja.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                @empty
                    <tbody><tr><td colspan="4" class="px-5 py-8 text-center text-slate-500">Tidak ada direktorat yang sesuai.</td></tr></tbody>
                @endforelse
            </table>
        </div>

        <x-modal name="create-direktorat" :show="$errors->any() && old('_form') === 'create-direktorat'" maxWidth="lg" focusable>
            <form method="POST" action="{{ route('administrasi.manajemen-direktorat.store') }}" class="p-6">
                @csrf
                <input type="hidden" name="_form" value="create-direktorat" />
                <h2 class="text-xl font-bold text-slate-800">Tambah Direktorat</h2>
                @if ($errors->any() && old('_form') === 'create-direktorat')
                    <p class="mt-3 text-sm text-red-700" role="alert">{{ $errors->first() }}</p>
                @endif
                <label class="mt-5 block text-sm font-medium text-slate-700">Nama direktorat
                    <input name="nama_direktorat" value="{{ old('nama_direktorat') }}" required maxlength="255" class="mt-1 w-full rounded-xl border-slate-300 text-sm" />
                </label>
                <label class="mt-4 block text-sm font-medium text-slate-700">Keterangan
                    <textarea name="keterangan" rows="3" class="mt-1 w-full rounded-xl border-slate-300 text-sm">{{ old('keterangan') }}</textarea>
                </label>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" x-on:click="$dispatch('close-modal', 'create-direktorat')" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Batal</button>
                    <button class="rounded-xl px-4 py-2 text-sm font-semibold shadow-sm transition hover:opacity-90"
                        style="background-color: #2377b9; border: 1px solid #2377b9; color: #fff;">Simpan Direktorat</button>
                </div>
            </form>
        </x-modal>

        <x-modal name="create-unit" :show="$errors->any() && old('_form') === 'create-unit'" maxWidth="lg" focusable>
            <form method="POST" action="{{ route('administrasi.manajemen-direktorat.unit.store') }}" class="p-6">
                @csrf
                <input type="hidden" name="_form" value="create-unit" />
                <h2 class="text-xl font-bold text-slate-800">Tambah Unit Kerja</h2>
                @if ($errors->any() && old('_form') === 'create-unit')
                    <p class="mt-3 text-sm text-red-700" role="alert">{{ $errors->first() }}</p>
                @endif
                <label class="mt-5 block text-sm font-medium text-slate-700">Direktorat
                    <select name="direktorat_id" required class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                        <option value="">Pilih direktorat</option>
                        @foreach ($allDirektorats->where('status', 'active') as $option)
                            <option value="{{ $option->id }}" @selected((int) old('direktorat_id') === $option->id)>{{ $option->nama_direktorat }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="mt-4 block text-sm font-medium text-slate-700">Nama unit kerja
                    <input name="nama_unit" value="{{ old('nama_unit') }}" required maxlength="255" class="mt-1 w-full rounded-xl border-slate-300 text-sm" />
                </label>
                <label class="mt-4 block text-sm font-medium text-slate-700">Kode unit kerja
                    <input name="kode_unit" value="{{ old('kode_unit') }}" maxlength="100" class="mt-1 w-full rounded-xl border-slate-300 text-sm" />
                </label>
                <label class="mt-4 block text-sm font-medium text-slate-700">Keterangan
                    <textarea name="keterangan" rows="2" class="mt-1 w-full rounded-xl border-slate-300 text-sm">{{ old('keterangan') }}</textarea>
                </label>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" x-on:click="$dispatch('close-modal', 'create-unit')" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Batal</button>
                    <button class="rounded-xl px-4 py-2 text-sm font-semibold shadow-sm transition hover:opacity-90"
                        style="background-color: #2377b9; border: 1px solid #2377b9; color: #fff;">Simpan Unit Kerja</button>
                </div>
            </form>
        </x-modal>

        <x-modal name="edit-direktorat" :show="$errors->any() && old('_form') === 'edit-direktorat'" maxWidth="lg" focusable>
            <form method="POST" :action="editDirektorat.action" class="p-6">
                @csrf @method('PATCH')
                <input type="hidden" name="_form" value="edit-direktorat" />
                <input type="hidden" name="_edit_action" :value="editDirektorat.action" />
                <h2 class="text-xl font-bold text-slate-800">Edit Direktorat</h2>
                @if ($errors->any() && old('_form') === 'edit-direktorat')
                    <p class="mt-3 text-sm text-red-700" role="alert">{{ $errors->first() }}</p>
                @endif
                <label class="mt-5 block text-sm font-medium text-slate-700">Nama direktorat
                    <input name="nama_direktorat" x-model="editDirektorat.nama_direktorat" required maxlength="255" class="mt-1 w-full rounded-xl border-slate-300 text-sm" />
                </label>
                <label class="mt-4 block text-sm font-medium text-slate-700">Keterangan
                    <textarea name="keterangan" x-model="editDirektorat.keterangan" rows="3" class="mt-1 w-full rounded-xl border-slate-300 text-sm"></textarea>
                </label>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" x-on:click="$dispatch('close-modal', 'edit-direktorat')" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Batal</button>
                    <button class="rounded-xl px-4 py-2 text-sm font-semibold shadow-sm transition hover:opacity-90"
                        style="background-color: #2377b9; border: 1px solid #2377b9; color: #fff;">Simpan Perubahan</button>
                </div>
            </form>
        </x-modal>

        <x-modal name="edit-unit" :show="$errors->any() && old('_form') === 'edit-unit'" maxWidth="lg" focusable>
            <form method="POST" :action="editUnit.action" class="p-6">
                @csrf @method('PATCH')
                <input type="hidden" name="_form" value="edit-unit" />
                <input type="hidden" name="_edit_action" :value="editUnit.action" />
                <h2 class="text-xl font-bold text-slate-800">Edit Unit Kerja</h2>
                @if ($errors->any() && old('_form') === 'edit-unit')
                    <p class="mt-3 text-sm text-red-700" role="alert">{{ $errors->first() }}</p>
                @endif
                <label class="mt-5 block text-sm font-medium text-slate-700">Direktorat
                    <select name="direktorat_id" x-model="editUnit.direktorat_id" required class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                        @foreach ($allDirektorats as $option)
                            <option value="{{ $option->id }}">{{ $option->nama_direktorat }}{{ $option->status === 'active' ? '' : ' (Nonaktif)' }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="mt-4 block text-sm font-medium text-slate-700">Nama unit kerja
                    <input name="nama_unit" x-model="editUnit.nama_unit" required maxlength="255" class="mt-1 w-full rounded-xl border-slate-300 text-sm" />
                </label>
                <label class="mt-4 block text-sm font-medium text-slate-700">Kode unit kerja
                    <input name="kode_unit" x-model="editUnit.kode_unit" maxlength="100" class="mt-1 w-full rounded-xl border-slate-300 text-sm" />
                </label>
                <label class="mt-4 block text-sm font-medium text-slate-700">Keterangan
                    <textarea name="keterangan" x-model="editUnit.keterangan" rows="2" class="mt-1 w-full rounded-xl border-slate-300 text-sm"></textarea>
                </label>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" x-on:click="$dispatch('close-modal', 'edit-unit')" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Batal</button>
                    <button class="rounded-xl px-4 py-2 text-sm font-semibold shadow-sm transition hover:opacity-90"
                        style="background-color: #2377b9; border: 1px solid #2377b9; color: #fff;">Simpan Perubahan</button>
                </div>
            </form>
        </x-modal>
    </div>
</x-app-layout>
