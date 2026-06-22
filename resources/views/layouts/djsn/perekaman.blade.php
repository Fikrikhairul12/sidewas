<x-app-layout>
    <div x-data="perekamanDjsnModal(@js($clusters), @js($direktorats))" class="space-y-6">
        {{-- Page Header --}}
        <div class="rounded-2xl border border-blue-100 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide" style="color: #2377b9;">
                        DJSN
                    </p>

                    <h1 class="mt-2 text-3xl font-bold text-slate-800">
                        Perekaman Rekomendasi DJSN
                    </h1>

                    <p class="mt-2 text-sm text-slate-500">
                        Halaman ini berisi riwayat perekaman Rekomendasi Dewan Jaminan Sosial Nasional.
                    </p>
                </div>

                <div class="flex flex-wrap gap-3">
                    @if (auth()->user()->canCreateDjsnPerekaman())
                        <button type="button" @click="openCreateModal = true"
                            class="inline-flex items-center gap-2 rounded-xl px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:opacity-90"
                            style="background-color: #2377b9;">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                            Tambah Perekaman
                        </button>
                    @endif

                </div>
            </div>
        </div>
        @if (session('success'))
            <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-700">
                {{ session('success') }}
            </div>
        @endif

        {{-- Summary Cards --}}
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            {{-- Total Perekaman --}}
            <div class="rounded-2xl border border-blue-100 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-500">Total Perekaman</p>
                        <p class="mt-2 text-3xl font-bold text-slate-800">
                            {{ $statistik['total'] ?? 0 }}
                        </p>
                    </div>

                    <div class="flex h-12 w-12 items-center justify-center rounded-xl text-white"
                        style="background-color: #2377b9;">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M7 3h7l5 5v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14 3v5h5" />
                        </svg>
                    </div>
                </div>
            </div>

            {{-- Selesai --}}
            <div class="rounded-2xl border border-green-100 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-500">Selesai</p>
                        <p class="mt-2 text-3xl font-bold" style="color: #6bb17e;">
                            {{ $statistik['selesai'] ?? 0 }}
                        </p>
                    </div>

                    <div class="flex h-12 w-12 items-center justify-center rounded-xl text-white"
                        style="background-color: #6bb17e;">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                    </div>
                </div>
            </div>

            {{-- Dalam Proses --}}
            <div class="rounded-2xl border border-yellow-100 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-500">Dalam Proses</p>
                        <p class="mt-2 text-3xl font-bold text-slate-800">
                            {{ $statistik['proses'] ?? 0 }}
                        </p>
                    </div>

                    <div class="flex h-12 w-12 items-center justify-center rounded-xl text-slate-700"
                        style="background-color: #c8e079;">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 6v6l4 2m6-2a10 10 0 1 1-20 0 10 10 0 0 1 20 0Z" />
                        </svg>
                    </div>
                </div>
            </div>

            {{-- Draft --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-500">Draf</p>
                        <p class="mt-2 text-3xl font-bold text-slate-700">
                            {{ $statistik['draft'] ?? 0 }}
                        </p>
                    </div>

                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-slate-100 text-slate-600">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M16.862 4.487 18.55 2.8a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z" />
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        {{-- Filter --}}
        <div x-data="{ openFilter: false }" class="rounded-2xl border border-blue-100 bg-white shadow-sm">
            <button type="button" @click="openFilter = !openFilter"
                class="flex w-full items-center justify-between px-6 py-4 text-left">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50"
                        style="color: #2377b9;">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 4.5h18M6 12h12M10 19.5h4" />
                        </svg>
                    </div>

                    <div>
                        <p class="font-semibold text-slate-800">Filter Lanjutan</p>
                        <p class="text-sm text-slate-500">
                            Isi minimal satu filter untuk mencari data perekaman DJSN.
                        </p>
                    </div>
                </div>

                <svg class="h-5 w-5 text-slate-500 transition-transform" :class="{ 'rotate-180': openFilter }"
                    fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m19 9-7 7-7-7" />
                </svg>
            </button>

            <div x-show="openFilter" x-transition class="border-t border-blue-50 px-6 py-5" style="display: none;">

                <form method="GET" action="{{ route('djsn.perekaman') }}">
                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {{-- Status --}}
                        <div>
                            <label class="mb-2 block text-sm font-medium text-slate-700">Status</label>
                            <select name="status"
                                class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Semua Status</option>
                                <option value="draft" @selected(request('status') === 'draft')>Draft</option>
                                <option value="terbit" @selected(request('status') === 'terbit')>Terbit</option>
                                <option value="dalam_proses" @selected(request('status') === 'dalam_proses')>Dalam Proses</option>
                                <option value="selesai" @selected(request('status') === 'selesai')>Selesai</option>
                            </select>
                        </div>

                        {{-- Direktorat --}}
                        <div>
                            <label class="mb-2 block text-sm font-medium text-slate-700">
                                Direktorat Penanggung Jawab
                            </label>
                            <select name="direktorat_id"
                                class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Semua Direktorat</option>
                                @foreach ($direktorats as $direktorat)
                                    <option value="{{ $direktorat->id }}" @selected(request('direktorat_id') == $direktorat->id)>
                                        {{ $direktorat->nama_direktorat }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Unit Kerja Utama --}}
                        <div>
                            <label class="mb-2 block text-sm font-medium text-slate-700">
                                Unit Kerja Utama
                            </label>
                            <select name="unit_kerja_utama_id"
                                class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Semua Unit Kerja Utama</option>
                                @foreach ($unitKerjas as $unit)
                                    <option value="{{ $unit->id }}" @selected(request('unit_kerja_utama_id') == $unit->id)>
                                        {{ $unit->kode_unit }} - {{ $unit->nama_unit }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- PIC Pendukung --}}
                        <div>
                            <label class="mb-2 block text-sm font-medium text-slate-700">
                                PIC Pendukung
                            </label>
                            <select name="unit_kerja_pendukung_id"
                                class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Semua PIC Pendukung</option>
                                @foreach ($unitKerjas as $unit)
                                    <option value="{{ $unit->id }}" @selected(request('unit_kerja_pendukung_id') == $unit->id)>
                                        {{ $unit->kode_unit }} - {{ $unit->nama_unit }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Komite --}}
                        <div>
                            <label class="mb-2 block text-sm font-medium text-slate-700">Komite</label>
                            <select name="komite_id"
                                class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Semua Komite</option>
                                @foreach ($komites as $komite)
                                    <option value="{{ $komite->id }}" @selected(request('komite_id') == $komite->id)>
                                        {{ $komite->kode_komite }} - {{ $komite->nama_komite }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Cluster --}}
                        <div>
                            <label class="mb-2 block text-sm font-medium text-slate-700">Cluster</label>
                            <select name="cluster_id" x-model="selectedClusterId"
                                class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Semua Cluster</option>
                                @foreach ($clusters as $cluster)
                                    <option value="{{ $cluster->id }}" @selected(request('cluster_id') == $cluster->id)>
                                        {{ $cluster->nama_cluster }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Sub Cluster --}}
                        <div>
                            <label class="mb-2 block text-sm font-medium text-slate-700">Sub Cluster</label>
                            <select name="sub_cluster_id"
                                class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Semua Sub Cluster</option>

                                @if (request('sub_cluster_id'))
                                    @foreach ($clusters as $cluster)
                                        @foreach ($cluster->subClusters as $subCluster)
                                            <option value="{{ $subCluster->id }}" @selected(request('sub_cluster_id') == $subCluster->id)>
                                                {{ $subCluster->nama_sub_cluster }}
                                            </option>
                                        @endforeach
                                    @endforeach
                                @else
                                    <template x-for="subCluster in filteredSubClusters" :key="subCluster.id">
                                        <option :value="subCluster.id" x-text="subCluster.nama_sub_cluster"></option>
                                    </template>
                                @endif
                            </select>
                        </div>

                        {{-- Tanggal Mulai --}}
                        <div>
                            <label class="mb-2 block text-sm font-medium text-slate-700">Tanggal Mulai</label>
                            <input type="date" name="tanggal_mulai" value="{{ request('tanggal_mulai') }}"
                                class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>

                        {{-- Tanggal Selesai --}}
                        <div>
                            <label class="mb-2 block text-sm font-medium text-slate-700">Tanggal Selesai</label>
                            <input type="date" name="tanggal_selesai" value="{{ request('tanggal_selesai') }}"
                                class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>

                        {{-- Kata Kunci --}}
                        <div class="md:col-span-2 xl:col-span-3">
                            <label class="mb-2 block text-sm font-medium text-slate-700">Kata Kunci</label>
                            <input type="text" name="keyword" value="{{ request('keyword') }}"
                                placeholder="Cari ID DJSN, nomor surat, perihal, ID butir, atau isi butir..."
                                class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                    </div>

                    <div class="mt-5 flex justify-end gap-3">
                        <a href="{{ route('djsn.perekaman') }}"
                            class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                            Reset
                        </a>

                        <button type="submit"
                            class="rounded-xl px-4 py-2 text-sm font-semibold text-white hover:opacity-90"
                            style="background-color: #2377b9;">
                            Terapkan Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Table --}}
        <div class="overflow-hidden rounded-2xl border border-blue-100 bg-white shadow-sm">
            <div
                class="flex flex-col gap-4 border-b border-blue-50 px-6 py-5 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 class="text-lg font-bold text-slate-800">
                        Riwayat Perekaman
                    </h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Daftar DJSN Dewas yang sudah pernah direkam ke sistem.
                    </p>
                </div>

            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-600">
                                Informasi Surat
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-600">
                                Butir Rekomendasi DJSN
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-600">
                                Cluster
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-600">
                                Status
                            </th>
                            <th
                                class="px-6 py-4 text-center text-xs font-bold uppercase tracking-wider text-slate-600">
                                Aksi
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-200 bg-white">
                        @forelse ($records as $record)
                            <tr class="border-b border-slate-200 transition hover:bg-blue-50/40">
                                {{-- Informasi Surat --}}
                                <td class="px-6 py-6 align-top">
                                    <div class="space-y-2">
                                        <p class="text-sm font-bold tracking-wide" style="color: #2377b9;">
                                            {{ $record->id_djsn }}
                                        </p>

                                        <p class="text-xs text-slate-700">
                                            <span class="font-medium">Nomor:</span>
                                            {{ $record->nomor_surat }}
                                        </p>

                                        <p class="text-xs text-slate-700">
                                            <span class="font-medium">Tanggal Surat:</span>
                                            {{ $record->tanggal_surat ? \Carbon\Carbon::parse($record->tanggal_surat)->format('d/m/Y') : '-' }}
                                        </p>

                                        <p
                                            class="max-w-md text-xs font-medium uppercase leading-relaxed text-slate-800">
                                            {{ $record->perihal_surat }}
                                        </p>

                                        <div class="mt-3">
                                            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                                                Dokumen Surat
                                            </p>

                                            @if ($record->dokumen)
                                                <a href="{{ route('djsn.perekaman.dokumen', $record->id) }}"
                                                    class="mt-2 inline-flex rounded-lg px-3 py-2 text-xs font-bold text-white hover:opacity-90"
                                                    style="background-color: #2377b9;">
                                                    Download Dokumen
                                                </a>
                                            @else
                                                <p class="mt-1 text-xs text-slate-400">
                                                    -
                                                </p>
                                            @endif
                                        </div>

                                        <div class="mt-4 rounded-xl bg-slate-50 p-4">
                                            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                                                Jatuh Tempo
                                            </p>

                                            <p class="mt-1 text-sm font-bold text-slate-800">
                                                {{ $record->jth_tempo ? \Carbon\Carbon::parse($record->jth_tempo)->format('d/m/Y') : '-' }}
                                            </p>

                                            <p class="mt-2 text-xs text-slate-500">
                                                Diinput oleh:
                                                {{ $record->creator?->name ?? '-' }}
                                            </p>
                                        </div>
                                    </div>
                                </td>

                                {{-- Butir DJSN --}}
                                <td class="px-6 py-6 align-top">
                                    @if ($record->butirDjsn->count() > 0)
                                        <div class="space-y-6">
                                            @foreach ($record->butirDjsn as $butir)
                                                <div
                                                    class="{{ !$loop->first ? 'mt-5 border-t border-slate-300 pt-5' : '' }}">
                                                    <p class="text-sm font-bold tracking-wide"
                                                        style="color: #2377b9;">
                                                        {{ $butir->id_butir_djsn }}
                                                    </p>

                                                    <p
                                                        class="mt-3 max-w-xl text-xs font-medium uppercase leading-relaxed text-slate-800">
                                                        {{ $butir->butir_djsn }}
                                                    </p>

                                                    @php
                                                        $picUtama = $butir->butirPics
                                                            ->where('jenis_pic', 'utama')
                                                            ->first();

                                                        $picPendukung = $butir->butirPics->where(
                                                            'jenis_pic',
                                                            'pendukung',
                                                        );

                                                        $komite = $butir->butirPics
                                                            ->where('jenis_pic', 'komite')
                                                            ->first();
                                                    @endphp

                                                    <div class="mt-5 space-y-4">
                                                        {{-- PIC Utama --}}
                                                        <div>
                                                            <p
                                                                class="text-xs font-bold uppercase tracking-wide text-slate-500">
                                                                PIC Utama
                                                            </p>

                                                            @if ($picUtama?->unitKerja)
                                                                <span
                                                                    class="mt-2 inline-flex rounded-full px-4 py-1.5 text-xs font-bold text-white"
                                                                    style="background-color: #6bb17e;">
                                                                    {{ $picUtama->unitKerja->kode_unit }}
                                                                    -
                                                                    {{ $picUtama->unitKerja->nama_unit }}
                                                                </span>
                                                            @else
                                                                <p class="mt-1 text-sm text-slate-400">-</p>
                                                            @endif
                                                        </div>

                                                        {{-- PIC Pendukung --}}
                                                        <div>
                                                            <p
                                                                class="text-xs font-bold uppercase tracking-wide text-slate-500">
                                                                PIC Pendukung
                                                            </p>

                                                            @if ($picPendukung->count() > 0)
                                                                <div class="mt-2 flex flex-wrap gap-2">
                                                                    @foreach ($picPendukung as $pic)
                                                                        @if ($pic->unitKerja)
                                                                            <span
                                                                                class="inline-flex rounded-full px-4 py-1.5 text-xs font-bold text-slate-700"
                                                                                style="background-color: #c8e079;">
                                                                                {{ $pic->unitKerja->kode_unit }}
                                                                                -
                                                                                {{ $pic->unitKerja->nama_unit }}
                                                                            </span>
                                                                        @endif
                                                                    @endforeach
                                                                </div>
                                                            @else
                                                                <p class="mt-1 text-sm text-slate-400">-</p>
                                                            @endif
                                                        </div>

                                                        {{-- Komite Dewas --}}
                                                        <div>
                                                            <p
                                                                class="text-xs font-bold uppercase tracking-wide text-slate-500">
                                                                Komite Dewas
                                                            </p>

                                                            @if ($komite?->komite)
                                                                <span
                                                                    class="mt-2 inline-flex rounded-full px-4 py-1.5 text-xs font-bold text-white"
                                                                    style="background-color: #2377b9;">
                                                                    {{ $komite->komite->kode_komite }}
                                                                    -
                                                                    {{ $komite->komite->nama_komite }}
                                                                </span>
                                                            @else
                                                                <p class="mt-1 text-sm text-slate-400">-</p>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="text-sm text-slate-400">-</span>
                                    @endif
                                </td>

                                {{-- Cluster --}}
                                <td class="px-6 py-6 align-top">
                                    @php
                                        $clusterItems = $record->butirDjsn
                                            ->map(function ($butir) {
                                                return [
                                                    'cluster' => $butir->cluster?->nama_cluster,
                                                    'sub_cluster' => $butir->subCluster?->nama_sub_cluster,
                                                ];
                                            })
                                            ->filter(fn ($item) => !empty($item['cluster']) || !empty($item['sub_cluster']))
                                            ->unique(fn ($item) => ($item['cluster'] ?? '-') . '|' . ($item['sub_cluster'] ?? '-'))
                                            ->values();
                                    @endphp

                                    @forelse ($clusterItems as $clusterItem)
                                        <div @class(['mt-3' => !$loop->first])>
                                            <p class="max-w-xs text-sm font-bold leading-relaxed text-slate-800">
                                                {{ $clusterItem['cluster'] ?? '-' }}
                                            </p>

                                            <p class="mt-1 max-w-xs text-sm leading-relaxed text-slate-500">
                                                {{ $clusterItem['sub_cluster'] ?? '-' }}
                                            </p>
                                        </div>
                                    @empty
                                        <span class="text-sm text-slate-400">-</span>
                                    @endforelse
                                </td>

                                {{-- Status --}}
                                <td class="px-6 py-6 align-top">
                                    @php
                                        $statusLabel =
                                            [
                                                'draft' => 'Draft',
                                                'terbit' => 'Terbit',
                                                'dalam_proses' => 'Proses',
                                                'selesai' => 'Selesai',
                                            ][$record->status] ?? ucwords(str_replace('_', ' ', $record->status));

                                        $statusColor =
                                            [
                                                'draft' => '#64748b',
                                                'terbit' => '#2377b9',
                                                'dalam_proses' => '#c8e079',
                                                'selesai' => '#6bb17e',
                                            ][$record->status] ?? '#64748b';

                                        $teksColor =
                                            [
                                                'draft' => 'text-white',
                                                'terbit' => 'text-white',
                                                'dalam_proses' => 'text-black',
                                                'selesai' => 'text-white',
                                            ][$record->status] ?? 'text-white';
                                    @endphp

                                    <span class="inline-flex text-center rounded-full px-4 py-1.5 text-xs font-bold {{ $teksColor }}"
                                        style="background-color: {{ $statusColor }};">
                                        {{ $statusLabel }}
                                    </span>

                                    <p class="mt-3 text-sm text-slate-500">
                                        {{ $record->butir_djsn_count ?? $record->butirDjsn->count() }} butir
                                    </p>
                                </td>

                                {{-- Aksi --}}
                                <td class="px-6 py-6 align-top">
                                    <div class="flex flex-wrap justify-center gap-2">
                                        @if (auth()->user()->canCreateDjsnPerekaman())
                                            <button type="button"
                                                @click="openButirModalFor({
                                                    id: {{ $record->id }},
                                                    id_djsn: @js($record->id_djsn),
                                                    nomor_surat: @js($record->nomor_surat)
                                                })"
                                                class="rounded-lg px-4 py-2 text-xs font-bold text-slate-700 shadow-sm transition hover:opacity-90"
                                                style="background-color: #c8e079;">
                                                + Butir
                                            </button>
                                        @endif

                                        <a href="#"
                                            class="rounded-lg px-4 py-2 text-xs font-bold text-white shadow-sm transition hover:opacity-90"
                                            style="background-color: #6bb17e;">
                                            Detail
                                        </a>

                                        <a href="#"
                                            class="rounded-lg px-4 py-2 text-xs font-bold text-white shadow-sm transition hover:opacity-90"
                                            style="background-color: #2377b9;">
                                            Edit
                                        </a>

                                        @if (auth()->user()->canRequestDeleteDjsnPerekaman())
                                            <form method="POST"
                                                action="{{ route('djsn.perekaman.destroy.request', $record->id) }}"
                                                onsubmit="return confirm('Ajukan penghapusan perekaman ini?')">
                                                @csrf
                                                @method('DELETE')

                                                <button type="submit"
                                                    class="rounded-lg bg-red-500 px-4 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-red-600">
                                                    Hapus
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr class="py-3 border-b-4 border-slate-200 transition hover:bg-blue-50/40">
                                <td colspan="5" class="px-6 py-14 text-center">
                                    <p class="text-sm font-semibold text-slate-600">
                                        Belum ada data perekaman DJSN.
                                    </p>
                                    <p class="mt-1 text-xs text-slate-400">
                                        Klik tombol Tambah Perekaman untuk menambahkan data pertama.
                                    </p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            <div
                class="flex flex-col gap-3 border-t border-slate-100 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-slate-500">
                    Menampilkan
                    <span class="font-semibold text-slate-700">{{ $records->firstItem() ?? 0 }}</span>
                    -
                    <span class="font-semibold text-slate-700">{{ $records->lastItem() ?? 0 }}</span>
                    dari
                    <span class="font-semibold text-slate-700">{{ $records->total() }}</span>
                    entri
                </p>

                @include('layouts.partials.compact-pagination', ['paginator' => $records])
            </div>
        </div>

        {{-- Modal Tambah Perekaman --}}
        <div x-show="openCreateModal" x-transition.opacity
            class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/60 px-4 py-8"
            style="display: none;">
            <div @click.outside="openCreateModal = false" x-transition
                class="w-full max-w-3xl overflow-hidden rounded-2xl bg-white shadow-2xl">

                <div class="flex items-start justify-between border-b border-slate-100 px-6 py-5">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-wide" style="color: #2377b9;">
                            Form Perekaman Surat
                        </p>

                        <h2 class="mt-1 text-2xl font-bold text-slate-800">
                            Tambah Perekaman Rekomendasi DJSN
                        </h2>

                        <p class="mt-1 text-sm text-slate-500">
                            ID Rekomendasi DJSN, tanggal jatuh tempo, dan status draft akan dibuat otomatis.
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

                <form method="POST" action="{{ route('djsn.perekaman.store') }}" enctype="multipart/form-data"
                    class="px-6 py-6">
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

                    <div class="grid gap-5 lg:grid-cols-2">
                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-700">
                                Nomor Surat
                            </label>
                            <input type="text" name="nomor_surat" value="{{ old('nomor_surat') }}" required
                                placeholder="Contoh: B/XXXX/MMYYYY"
                                class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-700">
                                Tanggal Surat
                            </label>
                            <input type="date" name="tanggal_surat" value="{{ old('tanggal_surat') }}" required
                                class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <p class="mt-1 text-xs text-slate-500">
                                Tanggal jatuh tempo otomatis 30 hari setelah tanggal ini.
                            </p>
                        </div>

                        <div class="lg:col-span-2">
                            <label class="mb-2 block text-sm font-semibold text-slate-700">
                                Perihal Surat
                            </label>
                            <textarea name="perihal_surat" rows="3" required placeholder="Masukkan perihal surat..."
                                class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('perihal_surat') }}</textarea>
                        </div>

                        <div class="lg:col-span-2">
                            <label class="mb-2 block text-sm font-semibold text-slate-700">
                                Dokumen Surat
                            </label>

                            <input type="file" name="dokumen"
                                class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">

                            <p class="mt-1 text-xs text-slate-500">
                                Opsional. Format: PDF, Word, Excel, JPG, PNG. Maksimal 5 MB.
                            </p>
                        </div>

                        <div class="lg:col-span-2">
                            <label class="mb-2 block text-sm font-semibold text-slate-700">
                                Status
                            </label>
                            <div
                                class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-600">
                                Draft otomatis
                            </div>
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
                            Simpan Perekaman Surat
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Modal Tambah Butir --}}
        <div x-show="openButirModal" x-transition.opacity
            class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/60 px-4 py-8"
            style="display: none;">
            <div @click.outside="openButirModal = false" x-transition
                class="w-full max-w-4xl overflow-hidden rounded-2xl bg-white shadow-2xl">

                <div class="flex items-start justify-between border-b border-slate-100 px-6 py-5">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-wide" style="color: #2377b9;">
                            Tambah Butir Rekomendasi DJSN
                        </p>
                        <h2 class="mt-1 text-2xl font-bold text-slate-800">
                            Surat <span x-text="selectedRecord?.id_djsn"></span>
                        </h2>
                        <p class="mt-1 text-sm text-slate-500">
                            ID Butir Rekomendasi DJSN akan dibuat otomatis.
                        </p>
                    </div>

                    <button type="button" @click="openButirModal = false"
                        class="rounded-xl p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                        ✕
                    </button>
                </div>

                <form method="POST" :action="`/djsn/perekaman/${selectedRecord?.id}/butir`" class="px-6 py-6">
                    @csrf

                    <div class="grid gap-5 lg:grid-cols-2">
                        <div class="lg:col-span-2">
                            <label class="mb-2 block text-sm font-semibold text-slate-700">
                                Isi Butir Rekomendasi DJSN
                            </label>
                            <textarea name="butir_djsn" rows="4" required
                                class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                placeholder="Masukkan isi butir Rekomendasi DJSN..."></textarea>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-700">
                                Cluster
                            </label>
                            <select name="cluster_id" x-model="selectedClusterId" @change="selectedSubClusterId = ''"
                                required
                                class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Pilih Cluster</option>
                                @foreach ($clusters as $cluster)
                                    <option value="{{ $cluster->id }}">
                                        {{ $cluster->nama_cluster }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-700">
                                Sub-Cluster
                            </label>
                            <select name="sub_cluster_id" x-model="selectedSubClusterId" required
                                class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Pilih Sub-Cluster</option>
                                <template x-for="subCluster in filteredSubClusters" :key="subCluster.id">
                                    <option :value="subCluster.id" x-text="subCluster.nama_sub_cluster"></option>
                                </template>
                            </select>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-700">
                                Direktorat Penanggung Jawab
                            </label>
                            <select x-model="selectedDirektoratUtamaId" required
                                class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Pilih Direktorat</option>
                                @foreach ($direktorats as $direktorat)
                                    <option value="{{ $direktorat->id }}">
                                        {{ $direktorat->nama_direktorat }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-700">
                                PIC Utama
                            </label>
                            <select name="unit_kerja_utama_id" required
                                class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Pilih PIC Utama</option>
                                <template x-for="unit in filteredUnitKerjaUtama" :key="unit.id">
                                    <option :value="unit.id"
                                        x-text="`${unit.kode_unit ?? '-'} - ${unit.nama_unit}`"></option>
                                </template>
                            </select>
                        </div>

                        <div class="lg:col-span-2">
                            <label class="mb-2 block text-sm font-semibold text-slate-700">
                                PIC Pendukung
                            </label>

                            <div class="rounded-xl border border-slate-300 bg-white">
                                {{-- Search --}}
                                <div class="border-b border-slate-200 p-3">
                                    <input type="text" x-model="picPendukungSearch"
                                        placeholder="Cari kode/nama unit kerja, contoh: SDW, LND, KEU..."
                                        class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                </div>

                                {{-- Selected chips --}}
                                <div x-show="selectedPicPendukungDetail.length > 0"
                                    class="border-b border-slate-200 bg-slate-50 p-3">
                                    <p class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-500">
                                        PIC Pendukung Terpilih
                                    </p>

                                    <div class="flex flex-wrap gap-2">
                                        <template x-for="unit in selectedPicPendukungDetail"
                                            :key="`selected-${unit.id}`">
                                            <span
                                                class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold text-slate-700"
                                                style="background-color: #c8e079;">
                                                <span x-text="`${unit.kode_unit ?? '-'} - ${unit.nama_unit}`"></span>

                                                <button type="button" @click="removePicPendukung(unit.id)"
                                                    class="font-bold text-slate-600 hover:text-red-600">
                                                    ×
                                                </button>
                                            </span>
                                        </template>
                                    </div>
                                </div>

                                {{-- Checkbox list --}}
                                <div class="max-h-64 overflow-y-auto p-3">
                                    <div class="grid gap-2 md:grid-cols-2">
                                        <template x-for="unit in filteredAllUnitKerjaPendukung" :key="unit.id">
                                            <label
                                                class="flex cursor-pointer items-start gap-3 rounded-lg border border-slate-100 p-3 transition hover:bg-blue-50">
                                                <input type="checkbox" :value="String(unit.id)"
                                                    x-model="selectedPicPendukung"
                                                    class="mt-1 rounded border-slate-300 text-blue-600 shadow-sm focus:ring-blue-500">

                                                <span>
                                                    <span class="block text-sm font-semibold text-slate-800"
                                                        x-text="`${unit.kode_unit ?? '-'} - ${unit.nama_unit}`"></span>

                                                    <span class="mt-1 block text-xs text-slate-500"
                                                        x-text="unit.direktorat_nama"></span>
                                                </span>
                                            </label>
                                        </template>
                                    </div>

                                    <div x-show="filteredAllUnitKerjaPendukung.length === 0"
                                        class="py-8 text-center text-sm text-slate-400">
                                        Unit kerja tidak ditemukan.
                                    </div>
                                </div>
                            </div>

                            <p class="mt-1 text-xs text-slate-500">
                                Opsional. Ketik kode/nama unit, lalu centang unit kerja yang ingin dijadikan PIC
                                pendukung.
                            </p>
                        </div>

                        <template x-for="picId in selectedPicPendukung" :key="`hidden-pic-${picId}`">
                            <input type="hidden" name="unit_kerja_pendukung_id[]" :value="picId">
                        </template>

                        <div class="lg:col-span-2">
                            <label class="mb-2 block text-sm font-semibold text-slate-700">
                                Komite Dewas
                            </label>
                            <select name="komite_id" required
                                class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Pilih Komite</option>
                                @foreach ($komites as $komite)
                                    <option value="{{ $komite->id }}">
                                        {{ $komite->kode_komite }} - {{ $komite->nama_komite }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="mt-8 flex justify-end gap-3 border-t border-slate-100 pt-5">
                        <button type="button" @click="openButirModal = false"
                            class="rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                            Batal
                        </button>

                        <button type="submit"
                            class="rounded-xl px-5 py-3 text-sm font-semibold text-white shadow-sm hover:opacity-90"
                            style="background-color: #2377b9;">
                            Simpan Butir
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
