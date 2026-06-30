<x-app-layout>
    @php
        $authUser = \App\Models\User::find(auth()->id());
        $canCreateProdukHukum = $authUser?->canCreateProdukHukum() ?? false;
        $canDeleteProdukHukum = $authUser?->canDeleteProdukHukum() ?? false;
        $canViewRahasiaProdukHukum = $authUser?->canViewRahasiaProdukHukum() ?? false;

        $produkPayloads = $produkHukums->getCollection()
            ->map(function ($produk) use ($canViewRahasiaProdukHukum, $approvedAccessIds, $pendingAccessIds, $pendingDeleteIds) {
                $canAccess = $produk->sifat_dokumen === 'publik'
                    || $canViewRahasiaProdukHukum
                    || in_array((int) $produk->id, $approvedAccessIds, true);

                return [
                    'id' => $produk->id,
                    'kode_produk_hukum' => $produk->kode_produk_hukum,
                    'judul' => $produk->judul,
                    'nomor_peraturan_keputusan' => $produk->nomor_peraturan_keputusan ?: '-',
                    'tahun_peraturan' => $produk->tahun_peraturan ?: '-',
                    'jenis_bentuk_peraturan' => $produk->jenis_bentuk_peraturan ?: '-',
                    'singkatan_peraturan' => $produk->singkatan_peraturan ?: '-',
                    'tanggal_penetapan' => $produk->tanggal_penetapan ? \Carbon\Carbon::parse($produk->tanggal_penetapan)->format('d F Y') : '-',
                    'tanggal_diundangkan' => $produk->tanggal_diundangkan ? \Carbon\Carbon::parse($produk->tanggal_diundangkan)->format('d F Y') : '-',
                    'sumber_ln_tbn' => $produk->sumber_ln_tbn ?: '-',
                    'sumber_tln_tbn' => $produk->sumber_tln_tbn ?: '-',
                    'subjek' => $produk->subjek ?: '-',
                    'bidang_pengaturan' => $produk->bidang_pengaturan ?: '-',
                    'abstrak' => $produk->abstrak ?: '-',
                    'keterangan' => $produk->keterangan ?: '-',
                    'muatan_substansial' => $produk->muatan_substansial ?: '-',
                    'status_peraturan' => $produk->status_peraturan ?: '-',
                    'sifat_dokumen' => $produk->sifat_dokumen,
                    'can_access' => $canAccess,
                    'pending_access' => in_array((int) $produk->id, $pendingAccessIds, true),
                    'pending_delete' => in_array((int) $produk->id, $pendingDeleteIds, true),
                    'files' => $canAccess
                        ? $produk->files
                            ->map(fn($file) => [
                                'id' => $file->id,
                                'nama_file' => $file->nama_file,
                                'bentuk_file' => $file->bentuk_file ?: 'file',
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
                                'nomor' => $relasi->produkHukumTerkait?->nomor_peraturan_keputusan ?? $relasi->nomor_produk_hukum_terkait ?? '-',
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

    <div x-data="{ openCreateModal: @js($canCreateProdukHukum && $errors->any()), openDetailModal: false, selectedProduk: null, fileMode: @js(old('bentuk_file', 'file')), openDetail(produk) { this.selectedProduk = produk; this.openDetailModal = true; } }"
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
                    <div>
                        <label class="mb-2 block text-sm font-semibold text-slate-700">Keyword</label>
                        <input type="text" name="keyword" value="{{ request('keyword') }}"
                            placeholder="Cari judul, nomor, subjek..."
                            class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-semibold text-slate-700">Bidang</label>
                        <select name="bidang_pengaturan"
                            class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Semua Bidang</option>
                            @foreach ($bidangOptions as $bidang)
                                <option value="{{ $bidang }}" @selected(request('bidang_pengaturan') === $bidang)>{{ $bidang }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-semibold text-slate-700">Jenis</label>
                        <select name="jenis_bentuk_peraturan"
                            class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Semua Jenis</option>
                            @foreach ($jenisOptions as $jenis)
                                @php
                                    $jenisLabel = $jenis->singkatan
                                        ? $jenis->nama . ' (' . $jenis->singkatan . ')'
                                        : $jenis->nama;
                                @endphp
                                <option value="{{ $jenis->nama }}" @selected(request('jenis_bentuk_peraturan') === $jenis->nama)>{{ $jenisLabel }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-semibold text-slate-700">Tahun</label>
                        <select name="tahun_peraturan"
                            class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Semua Tahun</option>
                            @foreach ($tahunOptions as $tahun)
                                <option value="{{ $tahun }}" @selected((string) request('tahun_peraturan') === (string) $tahun)>{{ $tahun }}</option>
                            @endforeach
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
                        $pendingDelete = $payload['pending_delete'] ?? false;
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
                                    {{ ucwords(str_replace('_', ' ', $produk->status_peraturan ?? '-')) }}
                                </span>
                            </div>

                            <h3 class="mt-2 text-lg font-bold text-slate-800">
                                {{ $produk->judul }}
                            </h3>

                            <div class="mt-3 grid gap-2 text-sm text-slate-600 md:grid-cols-2">
                                <p><span class="font-semibold">Nomor:</span> {{ $produk->nomor_peraturan_keputusan ?? '-' }}</p>
                                <p><span class="font-semibold">Tahun:</span> {{ $produk->tahun_peraturan ?? '-' }}</p>
                                <p><span class="font-semibold">Jenis:</span> {{ $produk->jenis_bentuk_peraturan ?? '-' }}</p>
                                <p><span class="font-semibold">Bidang:</span> {{ $produk->bidang_pengaturan ?? '-' }}</p>
                            </div>

                            @if ($produk->sifat_dokumen === 'rahasia' && ! $canAccessProduk)
                                <p class="mt-3 rounded-xl bg-orange-50 px-4 py-3 text-sm font-semibold text-orange-700">
                                    Detail lengkap dokumen ini bersifat rahasia.
                                </p>
                            @else
                                <p class="mt-3 max-w-3xl text-sm leading-relaxed text-slate-600">
                                    {{ \Illuminate\Support\Str::limit($produk->abstrak ?: $produk->muatan_substansial ?: $produk->subjek ?: '-', 180) }}
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

                            @if ($canDeleteProdukHukum)
                                @if ($pendingDelete)
                                    <button type="button" disabled
                                        class="rounded-xl bg-slate-300 px-4 py-3 text-sm font-bold text-white">
                                        Menunggu Hapus
                                    </button>
                                @else
                                    <form method="POST" action="{{ route('produk-hukum.request-delete', $produk->id) }}"
                                        onsubmit="return confirm('Ajukan penghapusan Produk Hukum ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="reason" value="Mengajukan hapus Produk Hukum.">
                                        <button type="submit"
                                            class="w-full rounded-xl bg-red-500 px-4 py-3 text-sm font-bold text-white shadow-sm hover:bg-red-600">
                                            Hapus
                                        </button>
                                    </form>
                                @endif
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
                            <dt class="font-bold">Abstrak</dt><dd class="whitespace-pre-line" x-text="selectedProduk?.abstrak ?? '-'"></dd>
                            <dt class="font-bold">Judul</dt><dd x-text="selectedProduk?.judul ?? '-'"></dd>
                            <dt class="font-bold">Nomor Peraturan/Keputusan</dt><dd x-text="selectedProduk?.nomor_peraturan_keputusan ?? '-'"></dd>
                            <dt class="font-bold">Jenis/Bentuk Peraturan</dt><dd x-text="selectedProduk?.jenis_bentuk_peraturan ?? '-'"></dd>
                            <dt class="font-bold">Singkatan Peraturan</dt><dd x-text="selectedProduk?.singkatan_peraturan ?? '-'"></dd>
                            <dt class="font-bold">Tanggal-Bulan-Tahun</dt>
                            <dd>
                                <p>Disahkan <span x-text="selectedProduk?.tanggal_penetapan ?? '-'"></span></p>
                                <p>Diundangkan <span x-text="selectedProduk?.tanggal_diundangkan ?? '-'"></span></p>
                            </dd>
                            <dt class="font-bold">Sumber</dt>
                            <dd>
                                <p>Sumber LN/TBN: <span x-text="selectedProduk?.sumber_ln_tbn ?? '-'"></span></p>
                                <p>Sumber TLN/TBN: <span x-text="selectedProduk?.sumber_tln_tbn ?? '-'"></span></p>
                            </dd>
                            <dt class="font-bold">Subjek</dt><dd x-text="selectedProduk?.subjek ?? '-'"></dd>
                            <dt class="font-bold">Bidang Pengaturan</dt><dd x-text="selectedProduk?.bidang_pengaturan ?? '-'"></dd>
                            <dt class="font-bold">Status Peraturan</dt><dd x-text="selectedProduk?.status_peraturan ?? '-'"></dd>
                            <dt class="font-bold">Keterangan</dt><dd class="whitespace-pre-line" x-text="selectedProduk?.keterangan ?? '-'"></dd>
                            <dt class="font-bold">Muatan Substansial</dt><dd class="whitespace-pre-line" x-text="selectedProduk?.muatan_substansial ?? '-'"></dd>
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
                                        <span class="text-slate-500" x-text="file.bentuk_file === 'link' ? '(Link)' : `(${file.ukuran_file})`"></span>
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
                                <label class="mb-2 block text-sm font-semibold text-slate-700">Jenis/Bentuk Peraturan</label>
                                <select name="jenis_bentuk_peraturan"
                                    class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="">Pilih Jenis/Bentuk Peraturan</option>
                                    @foreach ($jenisOptions as $jenis)
                                        @php
                                            $jenisLabel = $jenis->singkatan
                                                ? $jenis->nama . ' (' . $jenis->singkatan . ')'
                                                : $jenis->nama;
                                        @endphp
                                        <option value="{{ $jenis->nama }}" @selected(old('jenis_bentuk_peraturan') === $jenis->nama)>{{ $jenisLabel }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-700">Nomor Peraturan/Keputusan</label>
                                <input type="text" name="nomor_peraturan_keputusan" value="{{ old('nomor_peraturan_keputusan') }}"
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
                                <label class="mb-2 block text-sm font-semibold text-slate-700">Singkatan Peraturan</label>
                                <input type="text" name="singkatan_peraturan" value="{{ old('singkatan_peraturan') }}"
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
                                <label class="mb-2 block text-sm font-semibold text-slate-700">Sumber LN/TBN</label>
                                <input type="text" name="sumber_ln_tbn" value="{{ old('sumber_ln_tbn') }}"
                                    class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-700">Sumber TLN/TBN</label>
                                <input type="text" name="sumber_tln_tbn" value="{{ old('sumber_tln_tbn') }}"
                                    class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-700">Bidang Pengaturan</label>
                                <input type="text" name="bidang_pengaturan" value="{{ old('bidang_pengaturan') }}"
                                    class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-700">Status Peraturan</label>
                                <select name="status_peraturan" required
                                    class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="draft" @selected(old('status_peraturan') === 'draft')>Draft</option>
                                    <option value="berlaku" @selected(old('status_peraturan', 'berlaku') === 'berlaku')>Berlaku</option>
                                    <option value="tidak_berlaku" @selected(old('status_peraturan') === 'tidak_berlaku')>Tidak Berlaku</option>
                                </select>
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-700">Sifat Dokumen</label>
                                <select name="sifat_dokumen" required
                                    class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="publik" @selected(old('sifat_dokumen', 'publik') === 'publik')>Publik</option>
                                    <option value="rahasia" @selected(old('sifat_dokumen') === 'rahasia')>Rahasia</option>
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
                                <label class="mb-2 block text-sm font-semibold text-slate-700">Keterangan</label>
                                <textarea name="keterangan" rows="2"
                                    class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('keterangan') }}</textarea>
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-700">Bentuk File</label>
                                <select name="bentuk_file" x-model="fileMode" required
                                    class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="file">Upload File</option>
                                    <option value="link">Link</option>
                                </select>
                            </div>

                            <div class="lg:col-span-2" x-show="fileMode === 'file'">
                                <label class="mb-2 block text-sm font-semibold text-slate-700">File Produk Hukum</label>
                                <input type="file" name="files[]" multiple
                                    class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <p class="mt-1 text-xs text-slate-500">Opsional. PDF, Word, Excel, JPG, PNG. Maksimal 10 MB per file.</p>
                            </div>

                            <div x-show="fileMode === 'link'">
                                <label class="mb-2 block text-sm font-semibold text-slate-700">Nama Link</label>
                                <input type="text" name="nama_link_file" value="{{ old('nama_link_file') }}"
                                    placeholder="Contoh: Dokumen JDIH"
                                    class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>

                            <div x-show="fileMode === 'link'">
                                <label class="mb-2 block text-sm font-semibold text-slate-700">Link Produk Hukum</label>
                                <input type="url" name="link_file" value="{{ old('link_file') }}"
                                    placeholder="https://..."
                                    class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-700">Jenis File</label>
                                <input type="text" name="jenis_file" value="{{ old('jenis_file', 'lampiran') }}"
                                    class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>

                            <div class="lg:col-span-3">
                                <label class="mb-2 block text-sm font-semibold text-slate-700">Muatan Substansial</label>
                                <textarea name="muatan_substansial" rows="3"
                                    class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('muatan_substansial') }}</textarea>
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
                                <label class="mb-2 block text-sm font-semibold text-slate-700">Nomor Produk Hukum Terkait</label>
                                <input type="text" name="nomor_produk_hukum_terkait" value="{{ old('nomor_produk_hukum_terkait') }}"
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
