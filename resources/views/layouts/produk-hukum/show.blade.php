@php
    $jenisLabel = $produkHukum->jenis_bentuk_peraturan ?: 'Jenis peraturan belum dicantumkan';
    if ($produkHukum->singkatan_peraturan) {
        $jenisLabel .= ' (' . $produkHukum->singkatan_peraturan . ')';
    }

    $statusLabel = ucwords(str_replace('_', ' ', $produkHukum->status_peraturan ?: 'draft'));
    $statusClasses = match ($produkHukum->status_peraturan) {
        'berlaku' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
        'tidak_berlaku' => 'border-rose-200 bg-rose-50 text-rose-700',
        default => 'border-amber-200 bg-amber-50 text-amber-700',
    };
    $sifatClasses = $produkHukum->sifat_dokumen === 'rahasia'
        ? 'border-orange-200 bg-orange-50 text-orange-700'
        : 'border-sky-200 bg-sky-50 text-sky-700';
    $relationLabels = [
        'mencabut' => 'Mencabut',
        'dicabut_oleh' => 'Dicabut Oleh',
        'mengubah' => 'Mengubah',
        'diubah_oleh' => 'Diubah Oleh',
        'terkait' => 'Terkait',
    ];
    $relationsByType = $produkHukum->relasis->groupBy('jenis_relasi');
@endphp

<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        <a href="{{ route('produk-hukum.index') }}"
            class="inline-flex items-center gap-2 text-sm font-semibold text-slate-600 transition hover:text-blue-700">
            <svg class="h-4 w-4" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6" />
            </svg>
            Kembali ke Produk Hukum
        </a>

        <header class="overflow-hidden rounded-lg border border-blue-100 bg-white shadow-sm">
            <div class="border-l-4 border-[#2377b9] px-5 py-6 sm:px-7">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-[#2377b9]">
                            {{ $jenisLabel }}
                            @if ($produkHukum->nomor_peraturan_keputusan)
                                <span class="text-slate-400">&middot;</span>
                                Nomor {{ $produkHukum->nomor_peraturan_keputusan }}
                            @endif
                            @if ($produkHukum->tahun_peraturan)
                                <span class="text-slate-400">&middot;</span>
                                {{ $produkHukum->tahun_peraturan }}
                            @endif
                        </p>

                        <h1 class="mt-2 max-w-5xl text-2xl font-bold text-slate-900 sm:text-3xl">
                            {{ $produkHukum->judul }}
                        </h1>

                        <p class="mt-3 text-sm font-semibold text-slate-500">
                            {{ $produkHukum->kode_produk_hukum }}
                        </p>
                    </div>

                    <div class="flex shrink-0 flex-wrap gap-2">
                        <span class="inline-flex items-center rounded-md border px-3 py-1.5 text-xs font-bold {{ $statusClasses }}">
                            {{ $statusLabel }}
                        </span>
                        <span class="inline-flex items-center gap-1.5 rounded-md border px-3 py-1.5 text-xs font-bold {{ $sifatClasses }}">
                            @if ($produkHukum->sifat_dokumen === 'rahasia')
                                <svg class="h-3.5 w-3.5" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <rect width="18" height="11" x="3" y="11" rx="2" />
                                    <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                                </svg>
                            @endif
                            {{ ucfirst($produkHukum->sifat_dokumen ?: 'publik') }}
                        </span>
                    </div>
                </div>
            </div>
        </header>

        <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
            <main class="min-w-0 space-y-6">
                <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div class="flex items-center gap-3 border-b border-slate-200 px-5 py-4 sm:px-6">
                        <span class="flex h-9 w-9 items-center justify-center rounded-md bg-blue-50 text-[#2377b9]">
                            <svg class="h-5 w-5" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 21v-8H7v8M7 3v5h8" />
                            </svg>
                        </span>
                        <div>
                            <h2 class="text-lg font-bold text-slate-900">Ringkasan Peraturan</h2>
                            <p class="text-sm text-slate-500">Abstrak dan pokok isi dokumen.</p>
                        </div>
                    </div>
                    <div class="px-5 py-5 sm:px-6">
                        @if ($produkHukum->abstrak)
                            <p class="whitespace-pre-line text-sm leading-7 text-slate-700 sm:text-base">{{ $produkHukum->abstrak }}</p>
                        @else
                            <p class="text-sm text-slate-500">Belum ada abstrak untuk produk hukum ini.</p>
                        @endif
                    </div>
                </section>

                <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div class="flex items-center gap-3 border-b border-slate-200 px-5 py-4 sm:px-6">
                        <span class="flex h-9 w-9 items-center justify-center rounded-md bg-emerald-50 text-emerald-700">
                            <svg class="h-5 w-5" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01" />
                            </svg>
                        </span>
                        <div>
                            <h2 class="text-lg font-bold text-slate-900">Metadata Peraturan</h2>
                            <p class="text-sm text-slate-500">Identitas dan informasi penerbitan.</p>
                        </div>
                    </div>

                    <dl class="divide-y divide-slate-100 text-sm">
                        @foreach ([
                            ['Kode Produk Hukum', $produkHukum->kode_produk_hukum],
                            ['Judul', $produkHukum->judul],
                            ['Jenis/Bentuk Peraturan', $produkHukum->jenis_bentuk_peraturan],
                            ['Singkatan Peraturan', $produkHukum->singkatan_peraturan],
                            ['Nomor Peraturan/Keputusan', $produkHukum->nomor_peraturan_keputusan],
                            ['Tahun Peraturan', $produkHukum->tahun_peraturan],
                            ['Tanggal Penetapan', $produkHukum->tanggal_penetapan?->locale('id')->translatedFormat('d F Y')],
                            ['Tanggal Diundangkan', $produkHukum->tanggal_diundangkan?->locale('id')->translatedFormat('d F Y')],
                            ['Sumber LN/TBN', $produkHukum->sumber_ln_tbn],
                            ['Sumber TLN/TBN', $produkHukum->sumber_tln_tbn],
                            ['Subjek', $produkHukum->subjek],
                            ['Bidang Pengaturan', $produkHukum->bidang_pengaturan],
                        ] as [$label, $value])
                            <div class="grid gap-1 px-5 py-4 sm:grid-cols-[220px_minmax(0,1fr)] sm:gap-5 sm:px-6 odd:bg-slate-50/70">
                                <dt class="font-semibold text-slate-600">{{ $label }}</dt>
                                <dd class="whitespace-pre-line font-medium text-slate-900">{{ filled($value) ? $value : '-' }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </section>

                <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-5 py-4 sm:px-6">
                        <h2 class="text-lg font-bold text-slate-900">Keterangan</h2>
                    </div>
                    <div class="px-5 py-5 sm:px-6">
                        <p class="whitespace-pre-line text-sm leading-7 text-slate-700">{{ $produkHukum->keterangan ?: 'Belum ada keterangan tambahan.' }}</p>
                    </div>
                </section>

                <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-5 py-4 sm:px-6">
                        <h2 class="text-lg font-bold text-slate-900">Muatan Substansial</h2>
                    </div>
                    <div class="px-5 py-5 sm:px-6">
                        <p class="whitespace-pre-line text-sm leading-7 text-slate-700">{{ $produkHukum->muatan_substansial ?: 'Belum ada muatan substansial.' }}</p>
                    </div>
                </section>
            </main>

            <aside class="min-w-0 space-y-6">
                <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div class="flex items-center gap-3 border-b border-slate-200 px-5 py-4">
                        <span class="flex h-9 w-9 items-center justify-center rounded-md bg-rose-50 text-rose-700">
                            <svg class="h-5 w-5" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14 2v6h6M8 13h8M8 17h5" />
                            </svg>
                        </span>
                        <div>
                            <h2 class="font-bold text-slate-900">Dokumen</h2>
                            <p class="text-xs text-slate-500">{{ $produkHukum->files->count() }} file atau tautan</p>
                        </div>
                    </div>

                    <div class="divide-y divide-slate-100">
                        @forelse ($produkHukum->files as $file)
                            <div class="p-5">
                                <div class="flex min-w-0 items-start gap-3">
                                    <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-slate-100 text-slate-600">
                                        @if ($file->bentuk_file === 'link')
                                            <svg class="h-4 w-4" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71" />
                                            </svg>
                                        @else
                                            <svg class="h-4 w-4" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M14 2v6h6" />
                                            </svg>
                                        @endif
                                    </span>
                                    <div class="min-w-0">
                                        <p class="break-words text-sm font-bold text-slate-900">{{ $file->nama_file ?: 'Dokumen Produk Hukum' }}</p>
                                        <p class="mt-1 text-xs text-slate-500">
                                            {{ ucfirst($file->jenis_file ?: ($file->bentuk_file === 'link' ? 'tautan' : 'lampiran')) }}
                                            @if ($file->bentuk_file === 'file' && $file->ukuran_file)
                                                &middot; {{ number_format($file->ukuran_file / 1024 / 1024, 2) }} MB
                                            @elseif ($file->bentuk_file === 'link' && $file->link_file)
                                                &middot; {{ parse_url($file->link_file, PHP_URL_HOST) ?: 'Tautan eksternal' }}
                                            @endif
                                        </p>
                                    </div>
                                </div>

                                <div class="mt-4 flex flex-wrap gap-2">
                                    @if ($file->bentuk_file === 'link')
                                        <a href="{{ $file->link_file }}" target="_blank" rel="noopener noreferrer"
                                            class="inline-flex min-h-10 flex-1 items-center justify-center gap-2 rounded-md bg-[#173f64] px-3 py-2 text-sm font-bold text-white transition hover:bg-[#0f3150]">
                                            <svg class="h-4 w-4" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 3h6v6M10 14 21 3M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6" />
                                            </svg>
                                            Buka Tautan
                                        </a>
                                    @else
                                        @if ($file->isPreviewable())
                                            <a href="{{ route('produk-hukum.file.preview', $file) }}" target="_blank"
                                                class="inline-flex min-h-10 flex-1 items-center justify-center gap-2 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-bold text-slate-700 transition hover:bg-slate-50">
                                                <svg class="h-4 w-4" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.06 12.35a1 1 0 0 1 0-.7C3.73 7.6 7.66 5 12 5c4.34 0 8.27 2.6 9.94 6.65a1 1 0 0 1 0 .7C20.27 16.4 16.34 19 12 19c-4.34 0-8.27-2.6-9.94-6.65Z" />
                                                    <circle cx="12" cy="12" r="3" />
                                                </svg>
                                                Preview
                                            </a>
                                        @endif
                                        <a href="{{ route('produk-hukum.file.download', $file) }}"
                                            class="inline-flex min-h-10 flex-1 items-center justify-center gap-2 rounded-md bg-[#173f64] px-3 py-2 text-sm font-bold text-white transition hover:bg-[#0f3150]">
                                            <svg class="h-4 w-4" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3" />
                                            </svg>
                                            Download
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <p class="px-5 py-6 text-sm text-slate-500">Belum ada dokumen atau tautan.</p>
                        @endforelse
                    </div>
                </section>

                <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-5 py-4">
                        <h2 class="font-bold text-slate-900">Status Dokumen</h2>
                    </div>
                    <dl class="divide-y divide-slate-100 px-5 text-sm">
                        <div class="flex items-center justify-between gap-4 py-4">
                            <dt class="font-semibold text-slate-600">Status Peraturan</dt>
                            <dd class="rounded-md border px-2.5 py-1 text-xs font-bold {{ $statusClasses }}">{{ $statusLabel }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4 py-4">
                            <dt class="font-semibold text-slate-600">Sifat Dokumen</dt>
                            <dd class="rounded-md border px-2.5 py-1 text-xs font-bold {{ $sifatClasses }}">{{ ucfirst($produkHukum->sifat_dokumen ?: 'publik') }}</dd>
                        </div>
                    </dl>
                </section>

                <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-5 py-4">
                        <h2 class="font-bold text-slate-900">Hubungan Peraturan</h2>
                    </div>

                    @if ($produkHukum->relasis->isEmpty())
                        <p class="px-5 py-6 text-sm text-slate-500">Belum ada hubungan dengan produk hukum lain.</p>
                    @else
                        <div class="divide-y divide-slate-100">
                            @foreach ($relationLabels as $relationType => $relationLabel)
                                @if ($relationsByType->has($relationType))
                                    <div class="p-5">
                                        <p class="mb-3 inline-flex rounded-md bg-blue-50 px-2.5 py-1 text-xs font-bold text-blue-700">{{ $relationLabel }}</p>
                                        <div class="space-y-4">
                                            @foreach ($relationsByType->get($relationType) as $relation)
                                                @php
                                                    $relatedNumber = $relation->produkHukumTerkait?->nomor_peraturan_keputusan
                                                        ?? $relation->nomor_produk_hukum_terkait;
                                                    $relatedTitle = $relation->produkHukumTerkait?->judul
                                                        ?? $relation->judul_terkait;
                                                @endphp
                                                <div>
                                                    <p class="text-sm font-bold text-slate-900">{{ $relatedNumber ?: 'Nomor belum dicantumkan' }}</p>
                                                    <p class="mt-1 text-sm leading-6 text-slate-700">{{ $relatedTitle ?: 'Judul belum dicantumkan' }}</p>
                                                    @if ($relation->keterangan)
                                                        <p class="mt-2 whitespace-pre-line text-xs leading-5 text-slate-500">{{ $relation->keterangan }}</p>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </section>

                <section class="rounded-lg border border-slate-200 bg-white px-5 py-4 text-xs text-slate-500 shadow-sm">
                    <p>Dibuat oleh <span class="font-semibold text-slate-700">{{ $produkHukum->creator?->name ?: 'Pengguna' }}</span></p>
                    <p class="mt-1">Terakhir diperbarui {{ $produkHukum->updated_at?->locale('id')->translatedFormat('d F Y, H:i') ?: '-' }} WIB</p>
                </section>
            </aside>
        </div>
    </div>
</x-app-layout>
