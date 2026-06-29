<x-app-layout>
    @php
        $avatar = $user->avatar;
        $avatarUrl = null;

        if ($avatar) {
            $avatarUrl = preg_match('/^(https?:)?\/\//', $avatar)
                ? $avatar
                : asset(str_starts_with($avatar, 'storage/') ? $avatar : 'storage/' . ltrim($avatar, '/'));
        }

        $statusLabel = $user->status ? ucwords(str_replace('_', ' ', $user->status)) : '-';
        $providerLabel = $user->provider ? ucwords($user->provider) : 'Email';
        $verifiedLabel = $user->email_verified_at
            ? $user->email_verified_at->format('d/m/Y H:i')
            : 'Belum terverifikasi';
    @endphp

    <div class="space-y-8">
        <section class="rounded-md border border-blue-100 bg-white p-8 shadow-sm">
            <p class="text-sm font-semibold uppercase tracking-wide text-sidewas-blue">Profil</p>
            <div class="mt-3 flex flex-col gap-5 md:flex-row md:items-center md:justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-slate-900">Profil Pengguna</h1>
                    <p class="mt-2 max-w-3xl text-base text-slate-500">
                        Kelola informasi akun yang sedang digunakan. Nama dan password dapat diubah, informasi lain
                        ditampilkan sebagai data akun.
                    </p>
                </div>

                <div class="flex items-center gap-4 rounded-md border border-slate-200 bg-slate-50 px-4 py-3">
                    @if ($avatarUrl)
                        <img src="{{ $avatarUrl }}" alt="Avatar {{ $user->name }}"
                            class="h-14 w-14 rounded-full border border-blue-100 object-cover shadow-sm"
                            referrerpolicy="no-referrer">
                    @else
                        <div class="flex h-14 w-14 items-center justify-center rounded-full bg-sidewas-blue text-xl font-bold text-white">
                            {{ strtoupper(substr($user->name ?: $user->email, 0, 1)) }}
                        </div>
                    @endif
                    <div class="min-w-0">
                        <p class="truncate text-lg font-bold text-slate-900">{{ $user->name }}</p>
                        <p class="truncate text-sm text-slate-500">{{ $user->email }}</p>
                    </div>
                </div>
            </div>
        </section>

        @if (session('status') === 'profile-updated')
            <div class="rounded-md border border-green-200 bg-green-50 px-5 py-4 text-sm font-semibold text-green-700">
                Nama profil berhasil diperbarui.
            </div>
        @endif

        @if (session('status') === 'password-updated')
            <div class="rounded-md border border-green-200 bg-green-50 px-5 py-4 text-sm font-semibold text-green-700">
                Password berhasil diperbarui.
            </div>
        @endif

        <section class="grid gap-8 xl:grid-cols-[minmax(0,1fr)_380px]">
            <div class="space-y-8">
                <div class="rounded-md border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-100 px-8 py-6">
                        <p class="text-sm font-semibold uppercase tracking-wide text-sidewas-blue">Data Utama</p>
                        <h2 class="mt-1 text-2xl font-bold text-slate-900">Ubah Nama</h2>
                    </div>

                    <form method="POST" action="{{ route('profile.update') }}" class="space-y-6 px-8 py-6">
                        @csrf
                        @method('PATCH')

                        <div>
                            <label for="name" class="mb-2 block text-sm font-semibold text-slate-700">Nama</label>
                            <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}"
                                required autocomplete="name"
                                class="w-full rounded-md border border-slate-300 px-4 py-3 text-slate-900 shadow-sm focus:border-sidewas-blue focus:outline-none focus:ring-2 focus:ring-blue-100">
                            @error('name')
                                <p class="mt-2 text-sm font-semibold text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="email" class="mb-2 block text-sm font-semibold text-slate-700">Email</label>
                            <input id="email" type="email" value="{{ $user->email }}" readonly
                                class="w-full cursor-not-allowed rounded-md border border-slate-200 bg-slate-100 px-4 py-3 text-slate-500">
                        </div>

                        <div class="flex justify-end">
                            <button type="submit"
                                class="rounded-md bg-sidewas-blue px-6 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-blue-700">
                                Simpan Nama
                            </button>
                        </div>
                    </form>
                </div>

                <div class="rounded-md border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-100 px-8 py-6">
                        <p class="text-sm font-semibold uppercase tracking-wide text-sidewas-blue">Keamanan</p>
                        <h2 class="mt-1 text-2xl font-bold text-slate-900">Ubah Password</h2>
                    </div>

                    <form method="POST" action="{{ route('password.update') }}" class="space-y-6 px-8 py-6">
                        @csrf
                        @method('PUT')

                        <div>
                            <label for="update_password_current_password" class="mb-2 block text-sm font-semibold text-slate-700">
                                Password Saat Ini
                            </label>
                            <input id="update_password_current_password" name="current_password" type="password"
                                autocomplete="current-password"
                                class="w-full rounded-md border border-slate-300 px-4 py-3 text-slate-900 shadow-sm focus:border-sidewas-blue focus:outline-none focus:ring-2 focus:ring-blue-100">
                            @foreach ($errors->updatePassword->get('current_password') as $message)
                                <p class="mt-2 text-sm font-semibold text-red-600">{{ $message }}</p>
                            @endforeach
                        </div>

                        <div class="grid gap-5 md:grid-cols-2">
                            <div>
                                <label for="update_password_password" class="mb-2 block text-sm font-semibold text-slate-700">
                                    Password Baru
                                </label>
                                <input id="update_password_password" name="password" type="password"
                                    autocomplete="new-password"
                                    class="w-full rounded-md border border-slate-300 px-4 py-3 text-slate-900 shadow-sm focus:border-sidewas-blue focus:outline-none focus:ring-2 focus:ring-blue-100">
                                @foreach ($errors->updatePassword->get('password') as $message)
                                    <p class="mt-2 text-sm font-semibold text-red-600">{{ $message }}</p>
                                @endforeach
                            </div>

                            <div>
                                <label for="update_password_password_confirmation" class="mb-2 block text-sm font-semibold text-slate-700">
                                    Konfirmasi Password
                                </label>
                                <input id="update_password_password_confirmation" name="password_confirmation"
                                    type="password" autocomplete="new-password"
                                    class="w-full rounded-md border border-slate-300 px-4 py-3 text-slate-900 shadow-sm focus:border-sidewas-blue focus:outline-none focus:ring-2 focus:ring-blue-100">
                                @foreach ($errors->updatePassword->get('password_confirmation') as $message)
                                    <p class="mt-2 text-sm font-semibold text-red-600">{{ $message }}</p>
                                @endforeach
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit"
                                class="rounded-md bg-sidewas-blue px-6 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-blue-700">
                                Simpan Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <aside class="space-y-6">
                <div class="rounded-md border border-slate-200 bg-white p-6 shadow-sm">
                    <p class="text-sm font-semibold uppercase tracking-wide text-sidewas-blue">Informasi Akun</p>

                    <div class="mt-5 space-y-4">
                        <div>
                            <p class="text-xs font-semibold uppercase text-slate-400">Status</p>
                            <p class="mt-1 font-semibold text-slate-800">{{ $statusLabel }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase text-slate-400">Login</p>
                            <p class="mt-1 font-semibold text-slate-800">{{ $providerLabel }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase text-slate-400">Verifikasi Email</p>
                            <p class="mt-1 font-semibold text-slate-800">{{ $verifiedLabel }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase text-slate-400">Registrasi</p>
                            <p class="mt-1 font-semibold text-slate-800">
                                {{ $user->created_at?->format('d/m/Y H:i') ?? '-' }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="rounded-md border border-slate-200 bg-white p-6 shadow-sm">
                    <p class="text-sm font-semibold uppercase tracking-wide text-sidewas-blue">Akses</p>

                    <div class="mt-5 space-y-5">
                        <div>
                            <p class="text-xs font-semibold uppercase text-slate-400">Role</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                @forelse ($roleLabels as $roleLabel)
                                    <span class="rounded-full bg-blue-100 px-3 py-1 text-sm font-bold text-sidewas-blue">
                                        {{ $roleLabel }}
                                    </span>
                                @empty
                                    <span class="text-sm font-semibold text-slate-500">-</span>
                                @endforelse
                            </div>
                        </div>

                        <div>
                            <p class="text-xs font-semibold uppercase text-slate-400">Direktorat</p>
                            <div class="mt-2 space-y-2">
                                @forelse ($direktoratLabels as $direktoratLabel)
                                    <p class="rounded-md bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-700">
                                        {{ $direktoratLabel }}
                                    </p>
                                @empty
                                    <p class="text-sm font-semibold text-slate-500">-</p>
                                @endforelse
                            </div>
                        </div>

                        <div>
                            <p class="text-xs font-semibold uppercase text-slate-400">Unit Kerja</p>
                            <div class="mt-2 space-y-2">
                                @forelse ($unitLabels as $unitLabel)
                                    <p class="rounded-md bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-700">
                                        {{ $unitLabel }}
                                    </p>
                                @empty
                                    <p class="text-sm font-semibold text-slate-500">-</p>
                                @endforelse
                            </div>
                        </div>

                        <div>
                            <p class="text-xs font-semibold uppercase text-slate-400">Komite</p>
                            <div class="mt-2 space-y-2">
                                @forelse ($komiteLabels as $komiteLabel)
                                    <p class="rounded-md bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-700">
                                        {{ $komiteLabel }}
                                    </p>
                                @empty
                                    <p class="text-sm font-semibold text-slate-500">-</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            </aside>
        </section>
    </div>
</x-app-layout>
