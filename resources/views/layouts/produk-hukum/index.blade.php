<x-app-layout>
    @php
        $authUser = \App\Models\User::find(auth()->id());
        $canCreateProdukHukum = $authUser?->canCreateProdukHukum() ?? false;
        $canDeleteProdukHukum = $authUser?->canDeleteProdukHukum() ?? false;
        $canViewRahasiaProdukHukum = $authUser?->canViewRahasiaProdukHukum() ?? false;
        $jenisSingkatan = $jenisOptions->mapWithKeys(fn ($jenis) => [$jenis->nama => $jenis->singkatan ?: '']);
    @endphp

    <div x-data="{
        openCreateModal: @js($canCreateProdukHukum && $errors->any()),
        fileMode: @js(old('bentuk_file', 'file')),
        relationMode: @js(old('jenis_relasi', '')),
        selectedJenis: @js(old('jenis_bentuk_peraturan', '')),
        singkatanPeraturan: @js(old('singkatan_peraturan', '')),
        jenisSingkatan: @js($jenisSingkatan),
        relatedSearch: '',
        relatedProductId: @js((string) old('produk_hukum_terkait_id', '')),
        relatedOptions: @js($relatedProdukOptions),
        filteredRelatedOptions() {
            const keyword = this.relatedSearch.toLowerCase().trim();
            return keyword === ''
                ? this.relatedOptions
                : this.relatedOptions.filter((option) => String(option.id) === String(this.relatedProductId)
                    || option.label.toLowerCase().includes(keyword));
        },
    }"
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
                        $canAccessProduk = $produk->sifat_dokumen === 'publik'
                            || $canViewRahasiaProdukHukum
                            || in_array((int) $produk->id, $approvedAccessIds, true);
                        $pendingAccess = in_array((int) $produk->id, $pendingAccessIds, true);
                        $pendingDelete = in_array((int) $produk->id, $pendingDeleteIds, true);
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
                                <a href="{{ route('produk-hukum.show', $produk) }}"
                                    class="rounded-xl px-4 py-3 text-center text-sm font-bold text-white shadow-sm hover:opacity-90"
                                    style="background-color: #6bb17e;">
                                    Detail
                                </a>
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
                                <select name="jenis_bentuk_peraturan" x-model="selectedJenis"
                                    @change="singkatanPeraturan = jenisSingkatan[selectedJenis] ?? ''"
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
                                <input type="text" name="singkatan_peraturan" x-model="singkatanPeraturan"
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
                                <input type="file" name="files[]" multiple :disabled="fileMode !== 'file'"
                                    class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <p class="mt-1 text-xs text-slate-500">Opsional. PDF, Word, Excel, JPG, PNG. Maksimal 10 MB per file.</p>
                            </div>

                            <div x-show="fileMode === 'link'">
                                <label class="mb-2 block text-sm font-semibold text-slate-700">Nama Link</label>
                                <input type="text" name="nama_link_file" value="{{ old('nama_link_file') }}" :disabled="fileMode !== 'link'"
                                    placeholder="Contoh: Dokumen JDIH"
                                    class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>

                            <div x-show="fileMode === 'link'">
                                <label class="mb-2 block text-sm font-semibold text-slate-700">Link Produk Hukum</label>
                                <input type="url" name="link_file" value="{{ old('link_file') }}" :disabled="fileMode !== 'link'"
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
                                <select name="jenis_relasi" x-model="relationMode"
                                    class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="">Tidak ada relasi</option>
                                    <option value="mencabut" @selected(old('jenis_relasi') === 'mencabut')>Mencabut</option>
                                    <option value="dicabut_oleh" @selected(old('jenis_relasi') === 'dicabut_oleh')>Dicabut Oleh</option>
                                    <option value="mengubah" @selected(old('jenis_relasi') === 'mengubah')>Mengubah</option>
                                    <option value="diubah_oleh" @selected(old('jenis_relasi') === 'diubah_oleh')>Diubah Oleh</option>
                                    <option value="terkait" @selected(old('jenis_relasi') === 'terkait')>Terkait</option>
                                </select>
                            </div>

                            <div class="lg:col-span-2" x-show="relationMode !== ''" style="display: none;">
                                <label class="mb-2 block text-sm font-semibold text-slate-700">Cari Produk Hukum yang Sudah Tersimpan</label>
                                <input type="search" x-model="relatedSearch" placeholder="Cari kode, nomor, tahun, atau judul"
                                    class="mb-2 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <select name="produk_hukum_terkait_id" x-model="relatedProductId"
                                    class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="">Pilih produk hukum jika sudah tersedia</option>
                                    <template x-for="option in filteredRelatedOptions()" :key="option.id">
                                        <option :value="option.id" x-text="option.label"></option>
                                    </template>
                                </select>
                                <p class="mt-1 text-xs text-slate-500">Jika belum ada di database, isi nomor dan judul terkait secara manual.</p>
                            </div>

                            <div x-show="relationMode !== ''" style="display: none;">
                                <label class="mb-2 block text-sm font-semibold text-slate-700">Nomor Produk Hukum Terkait</label>
                                <input type="text" name="nomor_produk_hukum_terkait" value="{{ old('nomor_produk_hukum_terkait') }}"
                                    class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>

                            <div class="lg:col-span-2" x-show="relationMode !== ''" style="display: none;">
                                <label class="mb-2 block text-sm font-semibold text-slate-700">Judul Terkait</label>
                                <input type="text" name="judul_terkait" value="{{ old('judul_terkait') }}"
                                    class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>

                            <div class="lg:col-span-3" x-show="relationMode !== ''" style="display: none;">
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
