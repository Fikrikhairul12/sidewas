<x-app-layout>
    @php
        $authUser = \App\Models\User::find(auth()->id());
        $canCreateProdukHukum = $authUser?->canCreateProdukHukum() ?? false;
        $canViewRahasiaProdukHukum = $authUser?->canViewRahasiaProdukHukum() ?? false;

        $produkPayloads = $produkHukums->getCollection()
            ->map(function ($produk) use ($canViewRahasiaProdukHukum, $approvedAccessIds, $pendingAccessIds) {
                $canAccess = $produk->sifat_dokumen === 'publik'
                    || $canViewRahasiaProdukHukum
                    || in_array((int) $produk->id, $approvedAccessIds, true);

                return [
                    'id' => $produk->id,
                    'kode_produk_hukum' => $produk->kode_produk_hukum,
                    'tipe_dokumen' => $produk->tipe_dokumen ?: '-',
                    'judul' => $produk->judul,
                    'nomor_peraturan' => $produk->nomor_peraturan ?: '-',
                    'tahun_peraturan' => $produk->tahun_peraturan ?: '-',
                    'jenis_bentuk_peraturan' => $produk->jenis_bentuk_peraturan ?: '-',
                    'singkatan_peraturan' => $produk->singkatan_peraturan ?: '-',
                    'tempat_penetapan' => $produk->tempat_penetapan ?: '-',
                    'tanggal_penetapan' => $produk->tanggal_penetapan ? \Carbon\Carbon::parse($produk->tanggal_penetapan)->format('d F Y') : '-',
                    'tanggal_diundangkan' => $produk->tanggal_diundangkan ? \Carbon\Carbon::parse($produk->tanggal_diundangkan)->format('d F Y') : '-',
                    'sumber_ln' => $produk->sumber_ln ?: '-',
                    'sumber_tln' => $produk->sumber_tln ?: '-',
                    'subjek' => $produk->subjek ?: '-',
                    'bahasa' => $produk->bahasa ?: '-',
                    'lokasi' => $produk->lokasi ?: '-',
                    'bidang_hukum' => $produk->bidang_hukum ?: '-',
                    'abstrak' => $produk->abstrak ?: '-',
                    'status_peraturan' => $produk->status_peraturan ?: '-',
                    'sifat_dokumen' => $produk->sifat_dokumen,
                    'status_publish' => $produk->status_publish,
                    'can_access' => $canAccess,
                    'pending_access' => in_array((int) $produk->id, $pendingAccessIds, true),
                    'files' => $canAccess
                        ? $produk->files
                            ->map(fn($file) => [
                                'id' => $file->id,
                                'nama_file' => $file->nama_file,
                                'jenis_file' => $file->jenis_file ?: '-',
                                'ukuran_file' => $file->ukuran_file ? number_format($file->ukuran_file / 1024 / 1024, 2) . ' MB' : '-',
                                'url' => route('produk-hukum.file.download', $file->id),
                            ])
                            ->values()
                            ->all()
                        : [],
                    'relasis' => $canAccess
                        ? $produk->relasis
                            ->map(fn($relasi) => [
                                'jenis_relasi' => ucwords(str_replace('_', ' ', $relasi->jenis_relasi)),
                                'nomor' => $relasi->produkHukumTerkait?->nomor_peraturan ?? $relasi->nomor_peraturan_terkait ?? '-',
                                'judul' => $relasi->produkHukumTerkait?->judul ?? $relasi->judul_terkait ?? '-',
                                'keterangan' => $relasi->keterangan ?: '-',
                            ])
                            ->values()
                            ->all()
                        : [],
                ];
            })
            ->values();
    @endphp

    <div x-data="{ openCreateModal: @js($canCreateProdukHukum && $errors->any()), openDetailModal: false, selectedProduk: null, openDetail(produk) { this.selectedProduk = produk; this.openDetailModal = true; } }"
        class="space-y-6">
        <div class="rounded-2xl border border-blue-100 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide" style="color: #2377b9;">
                        Produk Hukum
                    </p>

                    <h1 class="mt-2 text-3xl font-bold text-slate-800">
                        Daftar Produk Hukum
                    </h1>

                    <p class="mt-2 text-sm text-slate-500">
                        Halaman ini berisi daftar dokumen produk hukum, lampiran, status peraturan, dan relasinya.
                    </p>
                </div>

                @if ($canCreateProdukHukum)
                    <button type="button" @click="openCreateModal = true"
                        class="rounded-xl px-5 py-3 text-sm font-bold text-white shadow-sm hover:opacity-90"
                        style="background-color: #2377b9;">
                        Tambah Produk Hukum
                    </button>
                @endif
            </div>
        </div>

        @if (session('success'))
            <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-700">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
                {{ session('error') }}
            </div>
        @endif

        <div class="rounded-2xl border border-blue-100 bg-white p-6 shadow-sm">
            <form method="GET" action="{{ route('produk-hukum.index') }}">
                <div class="grid gap-4 lg:grid-cols-4">
                    <div class="lg:col-span-2">
                        <label class="mb-2 block text-sm font-semibold text-slate-700">Keyword</label>
                        <input type="text" name="keyword" value="{{ request('keyword') }}"
                            placeholder="Cari judul, nomor, tahun, subjek, bidang hukum..."
                            class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-semibold text-slate-700">Sifat Dokumen</label>
                        <select name="sifat_dokumen"
                            class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Semua Sifat</option>
                            <option value="publik" @selected(request('sifat_dokumen') === 'publik')>Publik</option>
                            <option value="rahasia" @selected(request('sifat_dokumen') === 'rahasia')>Rahasia</option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-semibold text-slate-700">Status Publish</label>
                        <select name="status_publish"
                            class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Semua Status</option>
                            <option value="draft" @selected(request('status_publish') === 'draft')>Draft</option>
                            <option value="berlaku" @selected(request('status_publish') === 'berlaku')>Berlaku</option>
                            <option value="tidak_berlaku" @selected(request('status_publish') === 'tidak_berlaku')>Tidak Berlaku</option>
                        </select>
                    </div>
                </div>

                <div class="mt-5 flex justify-end gap-3">
                    <a href="{{ route('produk-hukum.index') }}"
                        class="rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Reset
                    </a>

                    <button type="submit"
                        class="rounded-xl px-5 py-3 text-sm font-bold text-white shadow-sm hover:opacity-90"
                        style="background-color: #2377b9;">
                        Terapkan Filter
                    </button>
                </div>
            </form>
        </div>

        <div class="overflow-hidden rounded-2xl border border-blue-100 bg-white shadow-sm">
            <div class="border-b border-blue-50 px-6 py-5">
                <h2 class="text-lg font-bold text-slate-800">List Produk Hukum</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Dokumen rahasia membutuhkan approval sebelum detail lengkap dan file dapat dibuka.
                </p>
            </div>

            <div class="divide-y divide-slate-100">
                @forelse ($produkHukums as $produk)
                    @php
                        $payload = $produkPayloads->firstWhere('id', $produk->id);
                        $canAccessProduk = $payload['can_access'] ?? false;
                        $pendingAccess = $payload['pending_access'] ?? false;
                    @endphp

                    <div class="grid gap-5 px-6 py-5 hover:bg-blue-50/40 lg:grid-cols-[minmax(0,1fr)_220px_180px] lg:items-start">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-sm font-bold" style="color: #2377b9;">
                                    {{ $produk->kode_produk_hukum }}
                                </p>

                                <span class="rounded-full px-3 py-1 text-xs font-bold {{ $produk->sifat_dokumen === 'rahasia' ? 'bg-orange-100 text-orange-700' : 'bg-green-100 text-green-700' }}">
                                    {{ ucfirst($produk->sifat_dokumen) }}
                                </span>

                                <span class="rounded-full bg-blue-100 px-3 py-1 text-xs font-bold text-blue-700">
                                    {{ ucfirst($produk->status_publish) }}
                                </span>
                            </div>

                            <h3 class="mt-2 text-lg font-bold text-slate-800">
                                {{ $produk->judul }}
                            </h3>

                            <div class="mt-3 grid gap-2 text-sm text-slate-600 md:grid-cols-2">
                                <p><span class="font-semibold">Tipe:</span> {{ $produk->tipe_dokumen ?? '-' }}</p>
                                <p><span class="font-semibold">Nomor:</span> {{ $produk->nomor_peraturan ?? '-' }}</p>
                                <p><span class="font-semibold">Tahun:</span> {{ $produk->tahun_peraturan ?? '-' }}</p>
                                <p><span class="font-semibold">Bidang:</span> {{ $produk->bidang_hukum ?? '-' }}</p>
                            </div>

                            @if ($produk->sifat_dokumen === 'rahasia' && ! $canAccessProduk)
                                <p class="mt-3 rounded-xl bg-orange-50 px-4 py-3 text-sm font-semibold text-orange-700">
                                    Detail lengkap dokumen ini bersifat rahasia.
                                </p>
                            @else
                                <p class="mt-3 max-w-3xl text-sm leading-relaxed text-slate-600">
                                    {{ \Illuminate\Support\Str::limit($produk->abstrak ?: $produk->subjek ?: '-', 180) }}
                                </p>
                            @endif
                        </div>

                        <div class="rounded-xl bg-slate-50 p-4 text-sm text-slate-600">
                            <p class="font-bold uppercase tracking-wide text-slate-500">File Peraturan</p>
                            <p class="mt-2">
                                Jumlah File:
                                <span class="font-bold text-slate-800">{{ $produk->files_count }}</span>
                            </p>

                            <p class="mt-2">
                                Status:
                                <span class="font-bold text-slate-800">{{ $produk->status_peraturan ?? '-' }}</span>
                            </p>
                        </div>

                        <div class="flex flex-col gap-2">
                            @if ($canAccessProduk)
                                <button type="button" @click="openDetail(@js($payload))"
                                    class="rounded-xl px-4 py-3 text-sm font-bold text-white shadow-sm hover:opacity-90"
                                    style="background-color: #6bb17e;">
                                    Detail
                                </button>
                            @elseif ($pendingAccess)
                                <button type="button" disabled
                                    class="rounded-xl bg-slate-300 px-4 py-3 text-sm font-bold text-white">
                                    Menunggu Approval
                                </button>
                            @else
                                <form method="POST" action="{{ route('produk-hukum.request-access', $produk->id) }}">
                                    @csrf
                                    <input type="hidden" name="reason" value="Mengajukan akses lihat produk hukum rahasia.">
                                    <button type="submit"
                                        class="w-full rounded-xl bg-orange-500 px-4 py-3 text-sm font-bold text-white shadow-sm hover:bg-orange-600">
                                        Ajukan Akses
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="px-6 py-14 text-center">
                        <p class="text-sm font-semibold text-slate-600">Belum ada Produk Hukum.</p>
                    </div>
                @endforelse
            </div>

            <div class="border-t border-slate-100 px-6 py-4">
                @include('layouts.partials.compact-pagination', ['paginator' => $produkHukums])
            </div>
        </div>

        <div x-show="openDetailModal" x-transition.opacity
            class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/60 px-4 py-8"
            style="display: none;">
            <div @click.outside="openDetailModal = false" x-transition
                class="w-full max-w-6xl overflow-hidden rounded-2xl bg-white shadow-2xl">
                <div class="flex items-start justify-between border-b border-slate-100 px-6 py-5">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-wide" style="color: #2377b9;">
                            Detail Produk Hukum
                        </p>
                        <h2 class="mt-1 text-2xl font-bold text-slate-800" x-text="selectedProduk?.judul ?? '-'"></h2>
                        <p class="mt-1 text-sm font-semibold text-slate-500" x-text="selectedProduk?.kode_produk_hukum ?? '-'"></p>
                    </div>

                    <button type="button" @click="openDetailModal = false"
                        class="rounded-xl p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="grid max-h-[72vh] overflow-y-auto p-6 lg:grid-cols-[minmax(0,1fr)_360px] lg:gap-6">
                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="mb-4 rounded-lg px-4 py-2 text-center text-sm font-bold text-white" style="background-color: #2377b9;">
                            Keterangan
                        </div>

                        <dl class="grid gap-x-4 gap-y-3 text-sm text-slate-700 md:grid-cols-[210px_minmax(0,1fr)]">
                            <dt class="font-bold">Tipe Dokumen</dt><dd x-text="selectedProduk?.tipe_dokumen ?? '-'"></dd>
                            <dt class="font-bold">Judul</dt><dd x-text="selectedProduk?.judul ?? '-'"></dd>
                            <dt class="font-bold">Nomor Peraturan</dt><dd x-text="selectedProduk?.nomor_peraturan ?? '-'"></dd>
                            <dt class="font-bold">Jenis/Bentuk Peraturan</dt><dd x-text="selectedProduk?.jenis_bentuk_peraturan ?? '-'"></dd>
                            <dt class="font-bold">Singkatan Peraturan</dt><dd x-text="selectedProduk?.singkatan_peraturan ?? '-'"></dd>
                            <dt class="font-bold">Tempat Penetapan</dt><dd x-text="selectedProduk?.tempat_penetapan ?? '-'"></dd>
                            <dt class="font-bold">Tanggal-Bulan-Tahun</dt>
                            <dd>
                                <p>Disahkan <span x-text="selectedProduk?.tanggal_penetapan ?? '-'"></span></p>
                                <p>Diundangkan <span x-text="selectedProduk?.tanggal_diundangkan ?? '-'"></span></p>
                            </dd>
                            <dt class="font-bold">Sumber</dt>
                            <dd>
                                <p x-text="selectedProduk?.sumber_ln ?? '-'"></p>
                                <p x-text="selectedProduk?.sumber_tln ?? '-'"></p>
                            </dd>
                            <dt class="font-bold">Subjek</dt><dd x-text="selectedProduk?.subjek ?? '-'"></dd>
                            <dt class="font-bold">Bahasa</dt><dd x-text="selectedProduk?.bahasa ?? '-'"></dd>
                            <dt class="font-bold">Lokasi</dt><dd x-text="selectedProduk?.lokasi ?? '-'"></dd>
                            <dt class="font-bold">Bidang Hukum</dt><dd x-text="selectedProduk?.bidang_hukum ?? '-'"></dd>
                            <dt class="font-bold">Status Peraturan</dt><dd x-text="selectedProduk?.status_peraturan ?? '-'"></dd>
                            <dt class="font-bold">Abstrak</dt><dd class="whitespace-pre-line" x-text="selectedProduk?.abstrak ?? '-'"></dd>
                        </dl>
                    </div>

                    <div class="space-y-5">
                        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                            <div class="mb-4 rounded-lg px-4 py-2 text-center text-sm font-bold text-white" style="background-color: #2377b9;">
                                File Peraturan
                            </div>

                            <p class="text-sm font-semibold text-slate-700">
                                Jumlah File: <span x-text="selectedProduk?.files?.length ?? 0"></span>
                            </p>

                            <div class="mt-3 space-y-2">
                                <template x-for="file in selectedProduk?.files ?? []" :key="file.id">
                                    <a :href="file.url"
                                        class="block rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-50">
                                        <span x-text="file.nama_file"></span>
                                        <span class="text-slate-500" x-text="`(${file.ukuran_file})`"></span>
                                    </a>
                                </template>

                                <p x-show="(selectedProduk?.files?.length ?? 0) === 0" class="text-sm text-slate-400">-</p>
                            </div>
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                            <div class="mb-4 rounded-lg px-4 py-2 text-center text-sm font-bold text-white" style="background-color: #2377b9;">
                                Peraturan Terkait
                            </div>

                            <div class="space-y-3">
                                <template x-for="relasi in selectedProduk?.relasis ?? []" :key="`${relasi.jenis_relasi}-${relasi.nomor}-${relasi.judul}`">
                                    <div class="rounded-lg bg-slate-50 p-3 text-sm text-slate-700">
                                        <p class="font-bold" x-text="relasi.jenis_relasi"></p>
                                        <p x-text="relasi.nomor"></p>
                                        <p x-text="relasi.judul"></p>
                                        <p class="mt-1 text-slate-500" x-text="relasi.keterangan"></p>
                                    </div>
                                </template>

                                <p x-show="(selectedProduk?.relasis?.length ?? 0) === 0" class="text-sm text-slate-400">-</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end border-t border-slate-100 px-6 py-4">
                    <button type="button" @click="openDetailModal = false"
                        class="rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Tutup
                    </button>
                </div>
            </div>
        </div>

        @if ($canCreateProdukHukum)
            <div x-show="openCreateModal" x-transition.opacity
                class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/60 px-4 py-8"
                style="display: none;">
                <div @click.outside="openCreateModal = false" x-transition
                    class="w-full max-w-6xl overflow-hidden rounded-2xl bg-white shadow-2xl">
                    <div class="flex items-start justify-between border-b border-slate-100 px-6 py-5">
                        <div>
                            <p class="text-sm font-semibold uppercase tracking-wide" style="color: #2377b9;">Form Produk Hukum</p>
                            <h2 class="mt-1 text-2xl font-bold text-slate-800">Tambah Produk Hukum</h2>
                            <p class="mt-1 text-sm text-slate-500">Isi data sesuai kolom database Produk Hukum.</p>
                        </div>

                        <button type="button" @click="openCreateModal = false"
                            class="rounded-xl p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <form method="POST" action="{{ route('produk-hukum.store') }}" enctype="multipart/form-data" class="px-6 py-6">
                        @csrf

                        @if ($errors->any())
                            <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                                <p class="font-semibold">Data belum bisa disimpan.</p>
                                <ul class="mt-2 list-disc pl-5">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <div class="grid gap-5 lg:grid-cols-3">
                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-700">Kode Produk Hukum</label>
                                <input type="text" name="kode_produk_hukum" value="{{ old('kode_produk_hukum') }}"
                                    placeholder="Otomatis jika kosong"
                                    class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-700">Tipe Dokumen</label>
                                <input type="text" name="tipe_dokumen" value="{{ old('tipe_dokumen') }}"
                                    placeholder="Contoh: Undang-Undang"
                                    class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-700">Nomor Peraturan</label>
                                <input type="text" name="nomor_peraturan" value="{{ old('nomor_peraturan') }}"
                                    class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>

                            <div class="lg:col-span-3">
                                <label class="mb-2 block text-sm font-semibold text-slate-700">Judul</label>
                                <textarea name="judul" rows="2" required
                                    class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('judul') }}</textarea>
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-700">Tahun Peraturan</label>
                                <input type="number" name="tahun_peraturan" value="{{ old('tahun_peraturan') }}"
                                    class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-700">Jenis/Bentuk Peraturan</label>
                                <input type="text" name="jenis_bentuk_peraturan" value="{{ old('jenis_bentuk_peraturan') }}"
                                    class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-700">Singkatan Peraturan</label>
                                <input type="text" name="singkatan_peraturan" value="{{ old('singkatan_peraturan') }}"
                                    class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-700">Tempat Penetapan</label>
                                <input type="text" name="tempat_penetapan" value="{{ old('tempat_penetapan') }}"
                                    class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-700">Tanggal Penetapan</label>
                                <input type="date" name="tanggal_penetapan" value="{{ old('tanggal_penetapan') }}"
                                    class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-700">Tanggal Diundangkan</label>
                                <input type="date" name="tanggal_diundangkan" value="{{ old('tanggal_diundangkan') }}"
                                    class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-700">Sumber LN</label>
                                <input type="text" name="sumber_ln" value="{{ old('sumber_ln') }}"
                                    class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-700">Sumber TLN</label>
                                <input type="text" name="sumber_tln" value="{{ old('sumber_tln') }}"
                                    class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-700">Bahasa</label>
                                <input type="text" name="bahasa" value="{{ old('bahasa', 'Indonesia') }}"
                                    class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-700">Lokasi</label>
                                <input type="text" name="lokasi" value="{{ old('lokasi') }}"
                                    class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-700">Bidang Hukum</label>
                                <input type="text" name="bidang_hukum" value="{{ old('bidang_hukum') }}"
                                    class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-700">Status Peraturan</label>
                                <input type="text" name="status_peraturan" value="{{ old('status_peraturan') }}"
                                    placeholder="Contoh: Berlaku, Mencabut"
                                    class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-700">Sifat Dokumen</label>
                                <select name="sifat_dokumen" required
                                    class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="publik" @selected(old('sifat_dokumen', 'publik') === 'publik')>Publik</option>
                                    <option value="rahasia" @selected(old('sifat_dokumen') === 'rahasia')>Rahasia</option>
                                </select>
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-700">Status Publish</label>
                                <select name="status_publish" required
                                    class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="draft" @selected(old('status_publish') === 'draft')>Draft</option>
                                    <option value="berlaku" @selected(old('status_publish', 'berlaku') === 'verlaku')>Berlaku</option>
                                    <option value="tidak_berlaku" @selected(old('status_publish') === 'tidak_berlaku')>Tidak Berlaku</option>
                                </select>
                            </div>

                            <div class="lg:col-span-3">
                                <label class="mb-2 block text-sm font-semibold text-slate-700">Subjek</label>
                                <textarea name="subjek" rows="2"
                                    class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('subjek') }}</textarea>
                            </div>

                            <div class="lg:col-span-3">
                                <label class="mb-2 block text-sm font-semibold text-slate-700">Abstrak</label>
                                <textarea name="abstrak" rows="3"
                                    class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('abstrak') }}</textarea>
                            </div>

                            <div class="lg:col-span-3">
                                <label class="mb-2 block text-sm font-semibold text-slate-700">File Produk Hukum</label>
                                <input type="file" name="files[]" multiple
                                    class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <p class="mt-1 text-xs text-slate-500">Opsional. PDF, Word, Excel, JPG, PNG. Maksimal 10 MB per file.</p>
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-700">Jenis File</label>
                                <input type="text" name="jenis_file" value="{{ old('jenis_file', 'lampiran') }}"
                                    class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-700">Jenis Relasi</label>
                                <select name="jenis_relasi"
                                    class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="">Tidak ada relasi</option>
                                    <option value="mencabut" @selected(old('jenis_relasi') === 'mencabut')>Mencabut</option>
                                    <option value="dicabut_oleh" @selected(old('jenis_relasi') === 'dicabut_oleh')>Dicabut Oleh</option>
                                    <option value="mengubah" @selected(old('jenis_relasi') === 'mengubah')>Mengubah</option>
                                    <option value="diubah_oleh" @selected(old('jenis_relasi') === 'diubah_oleh')>Diubah Oleh</option>
                                    <option value="terkait" @selected(old('jenis_relasi') === 'terkait')>Terkait</option>
                                </select>
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-700">Produk Hukum Terkait ID</label>
                                <input type="number" name="produk_hukum_terkait_id" value="{{ old('produk_hukum_terkait_id') }}"
                                    class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-700">Nomor Peraturan Terkait</label>
                                <input type="text" name="nomor_peraturan_terkait" value="{{ old('nomor_peraturan_terkait') }}"
                                    class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>

                            <div class="lg:col-span-2">
                                <label class="mb-2 block text-sm font-semibold text-slate-700">Judul Terkait</label>
                                <input type="text" name="judul_terkait" value="{{ old('judul_terkait') }}"
                                    class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>

                            <div class="lg:col-span-3">
                                <label class="mb-2 block text-sm font-semibold text-slate-700">Keterangan Relasi</label>
                                <textarea name="keterangan_relasi" rows="2"
                                    class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('keterangan_relasi') }}</textarea>
                            </div>
                        </div>

                        <div class="mt-8 flex justify-end gap-3 border-t border-slate-100 pt-5">
                            <button type="button" @click="openCreateModal = false"
                                class="rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                                Batal
                            </button>

                            <button type="submit"
                                class="rounded-xl px-5 py-3 text-sm font-bold text-white shadow-sm hover:opacity-90"
                                style="background-color: #2377b9;">
                                Simpan Produk Hukum
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
