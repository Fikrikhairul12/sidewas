<x-app-layout>
    <div class="space-y-6">
        <div class="rounded-2xl border border-blue-100 bg-white p-6 shadow-sm">
            <p class="text-sm font-semibold uppercase tracking-wide text-sky-700">Administrasi</p>
            <h1 class="mt-2 text-3xl font-bold text-slate-800">Manajemen Direktorat</h1>
            <p class="mt-2 text-sm text-slate-500">Kelola direktorat dan unit kerja. Penugasan user dilakukan melalui Manajemen User.</p>
        </div>

        @if (session('success'))
            <div class="rounded-xl border border-green-200 bg-green-50 px-5 py-4 text-sm text-green-800">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-800">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid gap-6 xl:grid-cols-2">
            <form method="POST" action="{{ route('administrasi.manajemen-direktorat.store') }}" class="rounded-2xl border border-blue-100 bg-white p-6 shadow-sm">
                @csrf
                <h2 class="text-lg font-bold text-slate-800">Tambah Direktorat</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <label class="block text-sm font-medium text-slate-700">Nama direktorat
                        <input name="nama_direktorat" value="{{ old('nama_direktorat') }}" required maxlength="255" class="mt-1 w-full rounded-xl border-slate-300 text-sm" />
                    </label>
                    <label class="block text-sm font-medium text-slate-700">Kode
                        <input name="kode_direktorat" value="{{ old('kode_direktorat') }}" maxlength="50" class="mt-1 w-full rounded-xl border-slate-300 text-sm" />
                    </label>
                </div>
                <label class="mt-4 block text-sm font-medium text-slate-700">Keterangan
                    <textarea name="keterangan" rows="2" class="mt-1 w-full rounded-xl border-slate-300 text-sm">{{ old('keterangan') }}</textarea>
                </label>
                <button class="mt-4 rounded-xl bg-sky-700 px-5 py-3 text-sm font-semibold text-white hover:bg-sky-800">Simpan Direktorat</button>
            </form>

            <form method="POST" action="{{ route('administrasi.manajemen-direktorat.unit.store') }}" class="rounded-2xl border border-blue-100 bg-white p-6 shadow-sm">
                @csrf
                <h2 class="text-lg font-bold text-slate-800">Tambah Unit Kerja</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <label class="block text-sm font-medium text-slate-700">Direktorat
                        <select name="direktorat_id" required class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                            <option value="">Pilih direktorat</option>
                            @foreach ($allDirektorats->where('status', 'active') as $direktorat)
                                <option value="{{ $direktorat->id }}" @selected((int) old('direktorat_id') === $direktorat->id)>{{ $direktorat->nama_direktorat }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-sm font-medium text-slate-700">Nama unit kerja
                        <input name="nama_unit" value="{{ old('nama_unit') }}" required maxlength="255" class="mt-1 w-full rounded-xl border-slate-300 text-sm" />
                    </label>
                    <label class="block text-sm font-medium text-slate-700">Kode
                        <input name="kode_unit" value="{{ old('kode_unit') }}" maxlength="100" class="mt-1 w-full rounded-xl border-slate-300 text-sm" />
                    </label>
                    <label class="block text-sm font-medium text-slate-700">Keterangan
                        <input name="keterangan" value="{{ old('keterangan') }}" class="mt-1 w-full rounded-xl border-slate-300 text-sm" />
                    </label>
                </div>
                <button class="mt-4 rounded-xl bg-sky-700 px-5 py-3 text-sm font-semibold text-white hover:bg-sky-800">Simpan Unit Kerja</button>
            </form>
        </div>

        <form method="GET" action="{{ route('administrasi.manajemen-direktorat.index') }}" class="flex flex-wrap items-end gap-3 rounded-2xl border border-blue-100 bg-white p-5 shadow-sm">
            <label class="min-w-52 flex-1 text-sm font-medium text-slate-700">Cari direktorat atau unit kerja
                <input name="keyword" value="{{ $filters['keyword'] ?? '' }}" class="mt-1 w-full rounded-xl border-slate-300 text-sm" placeholder="Nama atau kode" />
            </label>
            <label class="text-sm font-medium text-slate-700">Status direktorat
                <select name="status" class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                    <option value="">Semua</option>
                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>Aktif</option>
                    <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Nonaktif</option>
                </select>
            </label>
            <button class="rounded-xl bg-sky-700 px-5 py-3 text-sm font-semibold text-white">Cari</button>
            <a href="{{ route('administrasi.manajemen-direktorat.index') }}" class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700">Reset</a>
        </form>

        @forelse ($direktorats as $direktorat)
            <section class="rounded-2xl border border-blue-100 bg-white p-6 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-bold text-slate-800">{{ $direktorat->nama_direktorat }}</h2>
                        <p class="mt-1 text-sm text-slate-500">{{ $direktorat->kode_direktorat ?: 'Tanpa kode' }} · {{ $direktorat->unitKerja->count() }} unit kerja</p>
                        @if ($direktorat->keterangan)<p class="mt-2 text-sm text-slate-600">{{ $direktorat->keterangan }}</p>@endif
                    </div>
                    <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $direktorat->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-slate-100 text-slate-600' }}">{{ $direktorat->status === 'active' ? 'Aktif' : 'Nonaktif' }}</span>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <details class="rounded-xl border border-slate-200 p-3 text-sm">
                        <summary class="cursor-pointer font-semibold text-sky-700">Edit direktorat</summary>
                        <form method="POST" action="{{ route('administrasi.manajemen-direktorat.update', $direktorat) }}" class="mt-3 grid gap-3 sm:grid-cols-2">
                            @csrf @method('PATCH')
                            <label>Nama<input name="nama_direktorat" value="{{ $direktorat->nama_direktorat }}" required class="mt-1 w-full rounded-xl border-slate-300 text-sm" /></label>
                            <label>Kode<input name="kode_direktorat" value="{{ $direktorat->kode_direktorat }}" class="mt-1 w-full rounded-xl border-slate-300 text-sm" /></label>
                            <label class="sm:col-span-2">Keterangan<textarea name="keterangan" rows="2" class="mt-1 w-full rounded-xl border-slate-300 text-sm">{{ $direktorat->keterangan }}</textarea></label>
                            <button class="rounded-xl bg-sky-700 px-4 py-2 font-semibold text-white">Simpan</button>
                        </form>
                    </details>
                    <form method="POST" action="{{ route('administrasi.manajemen-direktorat.status', $direktorat) }}">
                        @csrf @method('PATCH')
                        <input type="hidden" name="status" value="{{ $direktorat->status === 'active' ? 'inactive' : 'active' }}" />
                        <button class="rounded-xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700">{{ $direktorat->status === 'active' ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                    </form>
                    <form method="POST" action="{{ route('administrasi.manajemen-direktorat.destroy', $direktorat) }}" onsubmit="return confirm('Hapus direktorat yang belum pernah digunakan?')">
                        @csrf @method('DELETE')
                        <button class="rounded-xl border border-red-200 px-4 py-3 text-sm font-semibold text-red-700">Hapus</button>
                    </form>
                </div>

                <div class="mt-5 divide-y divide-slate-100 border-t border-slate-100">
                    @forelse ($direktorat->unitKerja as $unit)
                        <div class="py-4">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <p class="font-semibold text-slate-800">{{ $unit->kode_unit ?: '-' }} · {{ $unit->nama_unit }}</p>
                                    <p class="text-xs text-slate-500">{{ $unit->active_users_count }} user aktif · {{ $unit->status === 'active' ? 'Aktif' : 'Nonaktif' }}</p>
                                    @if ($unit->keterangan)<p class="mt-1 text-sm text-slate-500">{{ $unit->keterangan }}</p>@endif
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <form method="POST" action="{{ route('administrasi.manajemen-direktorat.unit.status', $unit) }}">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="status" value="{{ $unit->status === 'active' ? 'inactive' : 'active' }}" />
                                        <button class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700">{{ $unit->status === 'active' ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                    </form>
                                    <form method="POST" action="{{ route('administrasi.manajemen-direktorat.unit.destroy', $unit) }}" onsubmit="return confirm('Hapus unit kerja yang belum pernah digunakan?')">
                                        @csrf @method('DELETE')
                                        <button class="rounded-lg border border-red-200 px-3 py-2 text-xs font-semibold text-red-700">Hapus</button>
                                    </form>
                                </div>
                            </div>
                            <details class="mt-2 text-sm">
                                <summary class="cursor-pointer font-semibold text-sky-700">Edit unit kerja</summary>
                                <form method="POST" action="{{ route('administrasi.manajemen-direktorat.unit.update', $unit) }}" class="mt-3 grid gap-3 sm:grid-cols-2">
                                    @csrf @method('PATCH')
                                    <label>Direktorat
                                        <select name="direktorat_id" required class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                                            @foreach ($allDirektorats->filter(fn ($option) => $option->status === 'active' || $option->id === $unit->direktorat_id) as $option)
                                                <option value="{{ $option->id }}" @selected($option->id === $unit->direktorat_id)>{{ $option->nama_direktorat }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label>Nama<input name="nama_unit" value="{{ $unit->nama_unit }}" required class="mt-1 w-full rounded-xl border-slate-300 text-sm" /></label>
                                    <label>Kode<input name="kode_unit" value="{{ $unit->kode_unit }}" class="mt-1 w-full rounded-xl border-slate-300 text-sm" /></label>
                                    <label>Keterangan<input name="keterangan" value="{{ $unit->keterangan }}" class="mt-1 w-full rounded-xl border-slate-300 text-sm" /></label>
                                    <button class="rounded-xl bg-sky-700 px-4 py-2 font-semibold text-white">Simpan</button>
                                </form>
                            </details>
                        </div>
                    @empty
                        <p class="py-4 text-sm text-slate-500">Belum ada unit kerja.</p>
                    @endforelse
                </div>
            </section>
        @empty
            <div class="rounded-2xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500">Tidak ada direktorat yang sesuai.</div>
        @endforelse
    </div>
</x-app-layout>
