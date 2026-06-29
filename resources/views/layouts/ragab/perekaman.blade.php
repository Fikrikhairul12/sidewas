<x-app-layout>
    @php
        $summaryTotal = $statistik['total'] ?? $records->total();
        $summaryDraft = $statistik['draft'] ?? $records->getCollection()->where('status', 'draft')->count();
        $summaryProses = $statistik['dalam_proses'] ?? $statistik['proses'] ?? $records->getCollection()->where('status', 'dalam_proses')->count();
        $summaryTuntas = $statistik['tuntas'] ?? $records->getCollection()->where('status', 'tuntas')->count();
    @endphp

    <div x-data="perekamanRagabModal(@js($clusters), @js($direktorats), @js($unitKerjas))" class="space-y-6">
        {{-- Page Header --}}
        <div class="rounded-2xl border border-blue-100 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide" style="color: #2377b9;">
                        Keputusan RAGAB
                    </p>

                    <h1 class="mt-2 text-3xl font-bold text-slate-800">
                        Perekaman Keputusan RAGAB
                    </h1>

                    <p class="mt-2 text-sm text-slate-500">
                        Halaman ini berisi riwayat perekaman Rapat Gabungan.
                    </p>
                </div>

                <div class="flex flex-wrap gap-3">
                    @if (auth()->user()->canCreateRagabPerekaman())
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

        @if (session('error'))
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
                {{ session('error') }}
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
                            {{ $summaryTotal }}
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

            {{-- Draft --}}
            <div class="rounded-2xl border border-blue-100 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-500">Draft</p>
                        <p class="mt-2 text-3xl font-bold" style="color: #2377b9;">
                            {{ $summaryDraft }}
                        </p>
                    </div>

                    <div class="flex h-12 w-12 items-center justify-center rounded-xl text-white"
                        style="background-color: #2377b9;">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
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
                            {{ $summaryProses }}
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

            {{-- Tuntas --}}
            <div class="rounded-2xl border border-green-100 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-500">Tuntas</p>
                        <p class="mt-2 text-3xl font-bold" style="color: #6bb17e;">
                            {{ $summaryTuntas }}
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
        </div>

        {{-- Filter --}}
        @include('layouts.ragab.partials.filter-lanjutan', [
            'action' => route('ragab.perekaman'),
            'statusOptions' => $statusOptions ?? [
                'draft' => 'Draft',
                'dalam_proses' => 'Dalam Proses',
                'tuntas' => 'Tuntas',
            ],
            'keywordPlaceholder' => 'Cari ID Keputusan RAGAB, ID butir, nomor surat, perihal, agenda, keputusan, atau PIC unit...',
        ])

        {{-- Table --}}
        <div class="overflow-hidden rounded-2xl border border-blue-100 bg-white shadow-sm">
            <div
                class="flex flex-col gap-4 border-b border-blue-50 px-6 py-5 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 class="text-lg font-bold text-slate-800">
                        Riwayat Perekaman
                    </h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Daftar Keputusan RAGAB Dewas yang sudah pernah direkam ke sistem.
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
                                Butir Keputusan RAGAB
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-600">
                                Cluster
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-600">
                                Status
                            </th>
                            <th class="px-6 py-4 text-center text-xs font-bold uppercase tracking-wider text-slate-600">
                                Aksi
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-200 bg-white">
                        @forelse ($records as $record)
                            @php
                                $butirCount = $record->butirRagab->count();
                                $detailRecordPayload = [
                                    'id' => $record->id,
                                    'id_ragab' => $record->id_ragab,
                                    'nomor_surat' => $record->nomor_surat,
                                    'butirs' => $record->butirRagab
                                        ->map(function ($butir) {
                                            $picUnit = $butir->butirPics->where('jenis_pic', 'unit');
                                            $komite = $butir->butirPics->where('jenis_pic', 'komite')->first();
                                            $direktoratButir = $butir->butirDirektorats ?? collect();
                                            $subClusterNames = $butir->subClusters->isNotEmpty()
                                                ? $butir->subClusters->pluck('nama_sub_cluster')
                                                : collect([$butir->subCluster?->nama_sub_cluster])->filter();

                                            return [
                                                'id' => $butir->id,
                                                'id_butir_ragab' => $butir->id_butir_ragab,
                                                'tanggal_ragab' => $butir->tanggal_ragab
                                                    ? \Carbon\Carbon::parse($butir->tanggal_ragab)->format('d/m/Y')
                                                    : '-',
                                                'agenda_ragab' => $butir->agenda_ragab ?? '-',
                                                'keputusan_ragab' => $butir->keputusan_ragab ?? '-',
                                                'ringkasan' => \Illuminate\Support\Str::limit(
                                                    $butir->keputusan_ragab ?? $butir->agenda_ragab ?? '-',
                                                    90,
                                                ),
                                                'status' => $butir->statusTindakLanjut(),
                                                'status_label' => $butir->statusTindakLanjutLabel(),
                                                'cluster' => $butir->cluster?->nama_cluster ?? '-',
                                                'sub_cluster' => $subClusterNames->implode(', ') ?: '-',
                                                'sub_clusters' => $subClusterNames->values()->all(),
                                                'direktorats' => $direktoratButir
                                                    ->map(fn($item) => $item->direktorat?->nama_direktorat)
                                                    ->filter()
                                                    ->values()
                                                    ->all(),
                                                'pic_unit' => $picUnit
                                                    ->map(
                                                        fn($pic) => $pic->unitKerja
                                                            ? $pic->unitKerja->kode_unit .
                                                                ' - ' .
                                                                $pic->unitKerja->nama_unit
                                                            : null,
                                                    )
                                                    ->filter()
                                                    ->values()
                                                    ->all(),
                                                'komite' => $komite?->komite
                                                    ? $komite->komite->kode_komite .
                                                        ' - ' .
                                                        $komite->komite->nama_komite
                                                    : '-',
                                            ];
                                        })
                                        ->values()
                                        ->all(),
                                ];
                            @endphp
                            <tr class="border-b border-slate-200 transition hover:bg-blue-50/40">
                                {{-- Informasi Surat --}}
                                <td class="px-6 py-6 align-top">
                                    <div class="space-y-2">
                                        <p class="text-sm font-bold tracking-wide" style="color: #2377b9;">
                                            {{ $record->id_ragab }}
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
                                                <a href="{{ route('ragab.perekaman.dokumen', $record->id) }}"
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

                                        <div class="mt-3">
                                            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                                                Dokumen Memo
                                            </p>

                                            @if ($record->dokumen_memo)
                                                <a href="{{ route('ragab.perekaman.dokumen-memo', $record->id) }}"
                                                    class="mt-2 inline-flex rounded-lg px-3 py-2 text-xs font-bold text-white hover:opacity-90"
                                                    style="background-color: #6bb17e;">
                                                    Download Memo
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

                                {{-- Butir RAGAB --}}
                                <td class="px-6 py-6 align-top">
                                    @if ($butirCount === 1)
                                        <div class="space-y-6">
                                            @foreach ($record->butirRagab as $butir)
                                                @php
                                                    $picUnit = $butir->butirPics->where('jenis_pic', 'unit');
                                                    $komite = $butir->butirPics->where('jenis_pic', 'komite')->first();
                                                    $direktoratButir = $butir->butirDirektorats ?? collect();
                                                @endphp

                                                <div
                                                    class="{{ !$loop->first ? 'mt-5 border-t border-slate-300 pt-5' : '' }}">
                                                    <p class="text-sm font-bold tracking-wide"
                                                        style="color: #2377b9;">
                                                        {{ $butir->id_butir_ragab }}
                                                    </p>

                                                    <div class="mt-3 space-y-3">
                                                        <div>
                                                            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                                                                Tanggal & Agenda RAGAB
                                                            </p>
                                                            <p class="mt-1 text-xs font-semibold leading-relaxed text-slate-800">
                                                                {{ $butir->tanggal_ragab ? \Carbon\Carbon::parse($butir->tanggal_ragab)->format('d/m/Y') : '-' }}
                                                            </p>
                                                            <p class="mt-1 whitespace-pre-line text-xs font-medium leading-relaxed text-slate-800">
                                                                {{ $butir->agenda_ragab ?? '-' }}
                                                            </p>
                                                        </div>

                                                        <div>
                                                            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                                                                Keputusan RAGAB
                                                            </p>
                                                            <p class="mt-1 max-w-xl whitespace-pre-line text-xs font-medium uppercase leading-relaxed text-slate-800">
                                                                {{ $butir->keputusan_ragab ?? '-' }}
                                                            </p>
                                                        </div>
                                                    </div>

                                                    <div class="mt-5 space-y-4">
                                                        {{-- Direktorat terkait --}}
                                                        <div>
                                                            <p
                                                                class="text-xs font-bold uppercase tracking-wide text-slate-500">
                                                                Direktorat Terkait
                                                            </p>

                                                            @if ($direktoratButir->count() > 0)
                                                                <div class="mt-2 flex flex-wrap gap-2">
                                                                    @foreach ($direktoratButir as $direktoratItem)
                                                                        @if ($direktoratItem->direktorat)
                                                                            <span
                                                                                class="inline-flex rounded-full bg-blue-50 px-4 py-1.5 text-xs font-bold text-blue-700">
                                                                                {{ $direktoratItem->direktorat->nama_direktorat }}
                                                                            </span>
                                                                        @endif
                                                                    @endforeach
                                                                </div>
                                                            @else
                                                                <p class="mt-1 text-sm text-slate-400">-</p>
                                                            @endif
                                                        </div>

                                                        {{-- PIC Unit --}}
                                                        <div>
                                                            <p
                                                                class="text-xs font-bold uppercase tracking-wide text-slate-500">
                                                                PIC Unit
                                                            </p>

                                                            @if ($picUnit->count() > 0)
                                                                <div class="mt-2 flex flex-wrap gap-2">
                                                                    @foreach ($picUnit as $pic)
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
                                                                <span class="font-normal text-slate-400">(Opsional)</span>
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
                                    @elseif ($butirCount > 1)
                                        @php
                                            $firstButir = $record->butirRagab->first();
                                        @endphp

                                        <div class="rounded-xl border border-slate-200 bg-white p-4">
                                            <p class="text-sm font-bold tracking-wide" style="color: #2377b9;">
                                                {{ $firstButir?->id_butir_ragab }}
                                            </p>

                                            <p class="mt-3 text-xs font-bold uppercase tracking-wide text-slate-500">
                                                Ringkasan Butir
                                            </p>
                                            <p class="mt-1 max-w-xl whitespace-pre-line text-xs font-medium uppercase leading-relaxed text-slate-800">
                                                {{ \Illuminate\Support\Str::limit($firstButir?->keputusan_ragab ?? '-', 180) }}
                                            </p>

                                            <div class="mt-4 rounded-lg bg-slate-50 px-3 py-2">
                                                <p class="text-xs font-semibold text-slate-500">
                                                    Menampilkan ringkasan 1 dari {{ $butirCount }} butir.
                                                    Detail lengkap ada di tombol Detail.
                                                </p>
                                            </div>
                                        </div>
                                    @else
                                        <span class="text-sm text-slate-400">-</span>
                                    @endif
                                </td>

                                {{-- Cluster --}}
                                <td class="px-6 py-6 align-top">
                                    @if ($record->butirRagab->count() > 0)
                                        <div class="space-y-5">
                                            @foreach ($record->butirRagab as $butir)
                                                @php
                                                    $subClusterNames = $butir->subClusters->isNotEmpty()
                                                        ? $butir->subClusters->pluck('nama_sub_cluster')
                                                        : collect([$butir->subCluster?->nama_sub_cluster])->filter();
                                                @endphp
                                                <div class="{{ !$loop->first ? 'border-t border-slate-200 pt-4' : '' }}">
                                                    <p class="max-w-xs text-sm font-bold leading-relaxed text-slate-800">
                                                        {{ $butir->cluster?->nama_cluster ?? '-' }}
                                                    </p>

                                                    <p class="mt-2 max-w-xs text-sm leading-relaxed text-slate-500">
                                                        {{ $subClusterNames->implode(', ') ?: '-' }}
                                                    </p>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <p class="text-sm text-slate-400">-</p>
                                    @endif
                                </td>

                                {{-- Status --}}
                                <td class="px-6 py-6 align-top">
                                    @php
                                        $statusLabel =
                                            [
                                                'draft' => 'Draft',
                                                'dalam_proses' => 'Dalam Proses',
                                                'tuntas' => 'Tuntas',
                                            ][$record->status] ?? ucwords(str_replace('_', ' ', $record->status));

                                        $statusColor =
                                            [
                                                'draft' => '#64748b',
                                                'dalam_proses' => '#c8e079',
                                                'tuntas' => '#6bb17e',
                                            ][$record->status] ?? '#64748b';

                                        $teksColor =
                                            [
                                                'draft' => 'text-white',
                                                'dalam_proses' => 'text-black',
                                                'tuntas' => 'text-white',
                                            ][$record->status] ?? 'text-white';
                                    @endphp

                                    <span
                                        class="inline-flex rounded-full px-4 py-1.5 text-center text-xs font-bold {{ $teksColor }}"
                                        style="background-color: {{ $statusColor }};">
                                        {{ $statusLabel }}
                                    </span>

                                    <p class="mt-3 text-sm text-slate-500">
                                        {{ $record->butir_ragab_count ?? $record->butirRagab->count() }} butir
                                    </p>
                                </td>

                                {{-- Aksi --}}
                                <td class="px-6 py-6 align-top">
                                    <div class="flex flex-wrap justify-center gap-2">
                                        @if (auth()->user()->canCreateRagabPerekaman())
                                            @if ($record->isButirAdditionLocked())
                                                <button type="button" disabled
                                                    class="cursor-not-allowed rounded-lg bg-slate-200 px-4 py-2 text-xs font-bold text-slate-500 shadow-sm"
                                                    title="Butir tidak dapat ditambah karena satu-satunya butir sudah selesai tuntas.">
                                                    Butir Tuntas
                                                </button>
                                            @else
                                                <button type="button"
                                                    @click="openButirModalFor({
                                                        id: {{ $record->id }},
                                                        id_ragab: @js($record->id_ragab),
                                                        nomor_surat: @js($record->nomor_surat)
                                                    })"
                                                    class="rounded-lg px-4 py-2 text-xs font-bold text-slate-700 shadow-sm transition hover:opacity-90"
                                                    style="background-color: #c8e079;">
                                                    + Butir
                                                </button>
                                            @endif
                                        @endif

                                        <button type="button"
                                            @click="openDetailModalFor(@js($detailRecordPayload))"
                                            class="rounded-lg px-4 py-2 text-xs font-bold text-white shadow-sm transition hover:opacity-90"
                                            style="background-color: #6bb17e;">
                                            Detail
                                        </button>

                                        <a href="#"
                                            class="rounded-lg px-4 py-2 text-xs font-bold text-white shadow-sm transition hover:opacity-90"
                                            style="background-color: #2377b9;">
                                            Edit
                                        </a>

                                        @if (auth()->user()->canRequestDeleteRagabPerekaman())
                                            <form method="POST"
                                                action="{{ route('ragab.perekaman.destroy.request', $record->id) }}"
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
                            <tr class="border-b-4 border-slate-200 transition hover:bg-blue-50/40">
                                <td colspan="5" class="px-6 py-14 text-center">
                                    <p class="text-sm font-semibold text-slate-600">
                                        Belum ada data perekaman Keputusan RAGAB.
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

        {{-- Modal Detail Butir --}}
        <div x-show="openDetailModal" x-transition.opacity
            class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/60 px-4 py-8"
            style="display: none;">
            <div @click.outside="openDetailModal = false" x-transition
                class="w-full max-w-6xl overflow-hidden rounded-2xl bg-white shadow-2xl">

                <div class="flex items-start justify-between border-b border-slate-100 px-6 py-5">
                    <div class="flex items-start gap-4">
                        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-blue-50"
                            style="color: #2377b9;">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M7 3h7l5 5v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14 3v5h5" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 13h6M9 17h4" />
                            </svg>
                        </div>

                        <div>
                            <h2 class="text-2xl font-bold text-slate-800">
                                Detail Butir RAGAB
                            </h2>
                            <p class="mt-1 text-sm font-semibold text-slate-500"
                                x-text="detailRecord?.id_ragab ?? '-'"></p>
                        </div>
                    </div>

                    <button type="button" @click="openDetailModal = false"
                        class="rounded-xl p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="grid max-h-[70vh] overflow-hidden lg:grid-cols-[340px_minmax(0,1fr)]">
                    <div class="border-b border-slate-100 p-5 lg:border-b-0 lg:border-r">
                        <div class="relative">
                            <svg class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400"
                                fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="m21 21-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z" />
                            </svg>
                            <input type="text" x-model="detailSearch" placeholder="Cari ID / isi butir..."
                                class="w-full rounded-xl border-slate-300 pl-10 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>

                        <div class="mt-4 max-h-[52vh] space-y-3 overflow-y-auto pr-1">
                            <template x-for="butir in filteredDetailButirs" :key="butir.id">
                                <button type="button" @click="selectDetailButir(butir)"
                                    class="block w-full rounded-xl border px-4 py-3 text-left transition hover:bg-blue-50"
                                    :class="String(selectedDetailButir?.id) === String(butir.id)
                                        ? 'border-blue-300 bg-blue-50'
                                        : 'border-slate-200 bg-white'">
                                    <span class="block text-sm font-bold" style="color: #2377b9;"
                                        x-text="butir.id_butir_ragab"></span>
                                    <span
                                        class="mt-2 inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600"
                                        x-text="butir.status_label"></span>
                                    <span class="mt-1 block text-sm leading-relaxed text-slate-600"
                                        x-text="butir.ringkasan"></span>
                                </button>
                            </template>

                            <div x-show="filteredDetailButirs.length === 0"
                                class="rounded-xl border border-dashed border-slate-200 px-4 py-8 text-center text-sm text-slate-400">
                                Butir tidak ditemukan.
                            </div>
                        </div>
                    </div>

                    <div class="max-h-[70vh] overflow-y-auto p-6">
                        <template x-if="selectedDetailButir">
                            <div class="space-y-5">
                                <div>
                                    <p class="text-2xl font-bold" style="color: #2377b9;"
                                        x-text="selectedDetailButir.id_butir_ragab"></p>
                                    <span
                                        class="mt-3 inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600"
                                        x-text="selectedDetailButir.status_label"></span>
                                    <p class="mt-2 text-sm text-slate-500">
                                        Cluster:
                                        <span x-text="selectedDetailButir.cluster ?? '-'"></span>
                                        <template x-if="selectedDetailButir.sub_cluster">
                                            <span>
                                                /
                                                <span x-text="selectedDetailButir.sub_cluster"></span>
                                            </span>
                                        </template>
                                    </p>
                                </div>

                                <div class="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <p class="mb-2 text-sm font-bold text-slate-700">Tanggal RAGAB</p>
                                        <div class="rounded-xl border border-slate-200 bg-white px-4 py-4 text-sm font-medium text-slate-700"
                                            x-text="selectedDetailButir.tanggal_ragab"></div>
                                    </div>

                                    <div>
                                        <p class="mb-2 text-sm font-bold text-slate-700">Agenda RAGAB</p>
                                        <div class="rounded-xl border border-slate-200 bg-white px-4 py-4 text-sm font-medium leading-relaxed text-slate-700"
                                            x-text="selectedDetailButir.agenda_ragab"></div>
                                    </div>
                                </div>

                                <div>
                                    <p class="mb-2 text-sm font-bold text-slate-700">Keputusan RAGAB</p>
                                    <div
                                        class="min-h-28 rounded-xl border border-slate-200 bg-white px-4 py-4 text-sm font-medium leading-relaxed text-slate-700">
                                        <p x-text="selectedDetailButir.keputusan_ragab"></p>
                                    </div>
                                </div>

                                <div class="space-y-3">
                                    <div
                                        class="grid gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-4 md:grid-cols-[150px_minmax(0,1fr)] md:items-center">
                                        <p class="text-sm font-bold text-slate-600">Direktorat</p>
                                        <div class="flex flex-wrap gap-2"
                                            x-show="selectedDetailButir.direktorats.length > 0">
                                            <template x-for="direktorat in selectedDetailButir.direktorats"
                                                :key="direktorat">
                                                <span
                                                    class="inline-flex rounded-full bg-blue-50 px-4 py-1.5 text-xs font-bold text-blue-700"
                                                    x-text="direktorat"></span>
                                            </template>
                                        </div>
                                        <p class="text-sm text-slate-400"
                                            x-show="selectedDetailButir.direktorats.length === 0">
                                            -
                                        </p>
                                    </div>

                                    <div
                                        class="grid gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-4 md:grid-cols-[150px_minmax(0,1fr)] md:items-center">
                                        <p class="text-sm font-bold text-slate-600">PIC Unit</p>
                                        <div class="flex flex-wrap gap-2"
                                            x-show="selectedDetailButir.pic_unit.length > 0">
                                            <template x-for="unit in selectedDetailButir.pic_unit" :key="unit">
                                                <span
                                                    class="inline-flex rounded-full px-4 py-1.5 text-xs font-bold text-slate-700"
                                                    style="background-color: #c8e079;" x-text="unit"></span>
                                            </template>
                                        </div>
                                        <p class="text-sm text-slate-400"
                                            x-show="selectedDetailButir.pic_unit.length === 0">
                                            -
                                        </p>
                                    </div>

                                    <div
                                        class="grid gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-4 md:grid-cols-[150px_minmax(0,1fr)] md:items-center">
                                        <p class="text-sm font-bold text-slate-600">Komite Dewas</p>
                                        <span
                                            class="inline-flex w-fit rounded-full px-4 py-1.5 text-xs font-bold text-white"
                                            style="background-color: #2377b9;"
                                            x-text="selectedDetailButir.komite"></span>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <div x-show="!selectedDetailButir"
                            class="rounded-xl border border-dashed border-slate-200 px-4 py-14 text-center text-sm text-slate-400">
                            Surat ini belum memiliki butir RAGAB.
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-3 border-t border-slate-100 px-6 py-4">
                    <button type="button" @click="openDetailModal = false"
                        class="rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Tutup
                    </button>
                </div>
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
                            Tambah Perekaman Keputusan RAGAB
                        </h2>

                        <p class="mt-1 text-sm text-slate-500">
                            ID Keputusan RAGAB, tanggal jatuh tempo, dan status draft akan dibuat otomatis.
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

                <form method="POST" action="{{ route('ragab.perekaman.store') }}" enctype="multipart/form-data"
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

                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-700">
                                Dokumen Surat
                            </label>

                            <input type="file" name="dokumen"
                                class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">

                            <p class="mt-1 text-xs text-slate-500">
                                Opsional. Format: PDF, Word, Excel, JPG, PNG. Maksimal 5 MB.
                            </p>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-700">
                                Dokumen Memo
                            </label>

                            <input type="file" name="dokumen_memo"
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
                class="w-full max-w-5xl overflow-hidden rounded-2xl bg-white shadow-2xl">

                <div class="flex items-start justify-between border-b border-slate-100 px-6 py-5">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-wide" style="color: #2377b9;">
                            Tambah Butir Keputusan RAGAB
                        </p>
                        <h2 class="mt-1 text-2xl font-bold text-slate-800">
                            Surat <span x-text="selectedRecord?.id_ragab"></span>
                        </h2>
                        <p class="mt-1 text-sm text-slate-500">
                            ID Butir Keputusan RAGAB akan dibuat otomatis.
                        </p>
                    </div>

                    <button type="button" @click="openButirModal = false"
                        class="rounded-xl p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                        ✕
                    </button>
                </div>

                <form method="POST" :action="`/ragab/perekaman/${selectedRecord?.id}/butir`" class="px-6 py-6">
                    @csrf

                    <div class="grid gap-5 lg:grid-cols-2">
                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-700">
                                Tanggal RAGAB
                            </label>

                            <input type="date" name="tanggal_ragab" required
                                class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-700">
                                Agenda RAGAB
                            </label>

                            <input type="text" name="agenda_ragab" required
                                class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                placeholder="Masukkan agenda RAGAB...">
                        </div>

                        <div class="lg:col-span-2">
                            <label class="mb-2 block text-sm font-semibold text-slate-700">
                                Butir Keputusan RAGAB
                            </label>

                            <textarea name="keputusan_ragab" rows="4" required
                                class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                placeholder="Masukkan butir keputusan RAGAB..."></textarea>
                        </div>

                        <div class="lg:col-span-2">
                            <label class="mb-2 block text-sm font-semibold text-slate-700">
                                Direktorat Terkait
                            </label>

                            <div class="grid max-h-56 gap-2 overflow-y-auto rounded-xl border border-slate-300 bg-white p-3 md:grid-cols-2">
                                @foreach ($direktorats as $direktorat)
                                    <label class="flex cursor-pointer items-start gap-3 rounded-lg px-3 py-2 hover:bg-blue-50">
                                        <input type="checkbox" name="direktorat_ids[]" value="{{ $direktorat->id }}"
                                            x-model="selectedDirektoratIds"
                                            class="mt-1 rounded border-slate-300 text-blue-600 focus:ring-blue-500">

                                        <span class="text-sm font-medium text-slate-700">
                                            {{ $direktorat->nama_direktorat }}
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div class="lg:col-span-2">
                            <label class="mb-2 block text-sm font-semibold text-slate-700">
                                PIC Unit
                            </label>

                            <div class="rounded-xl border border-slate-300 bg-white">
                                <div class="border-b border-slate-200 p-3">
                                    <input type="text" x-model="unitKerjaSearch"
                                        placeholder="Cari kode/nama unit kerja, contoh: REN, SDW, KEU..."
                                        class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                </div>

                                <div x-show="selectedUnitKerjaDetail.length > 0"
                                    class="border-b border-slate-200 bg-slate-50 p-3">
                                    <p class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-500">
                                        PIC Unit Terpilih
                                    </p>

                                    <div class="flex flex-wrap gap-2">
                                        <template x-for="unit in selectedUnitKerjaDetail" :key="unit.id">
                                            <span
                                                class="inline-flex items-center gap-2 rounded-full bg-blue-100 px-3 py-1 text-xs font-bold text-blue-700">
                                                <span x-text="`${unit.kode_unit ?? '-'} - ${unit.nama_unit}`"></span>

                                                <button type="button" @click="removeUnitKerja(unit.id)"
                                                    class="text-blue-500 hover:text-red-500">
                                                    ×
                                                </button>
                                            </span>
                                        </template>
                                    </div>
                                </div>

                                <div class="max-h-72 overflow-y-auto p-3">
                                    <template x-for="unit in filteredUnitKerjas" :key="unit.id">
                                        <label
                                            class="mb-2 flex cursor-pointer items-start gap-3 rounded-lg border border-slate-100 px-3 py-2 text-sm hover:bg-blue-50">
                                            <input type="checkbox" name="unit_kerja_ids[]" :value="unit.id"
                                                x-model="selectedUnitKerjaIds"
                                                class="mt-1 rounded border-slate-300 text-blue-600 focus:ring-blue-500">

                                            <span>
                                                <span class="font-bold text-slate-700"
                                                    x-text="`${unit.kode_unit ?? '-'} - ${unit.nama_unit}`"></span>
                                                <br>
                                                <span class="text-xs text-slate-500"
                                                    x-text="unit.direktorat?.nama_direktorat ?? unit.direktorat_nama ?? '-'"></span>
                                            </span>
                                        </label>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-700">
                                Cluster
                            </label>

                            <select name="cluster_id" x-model="selectedClusterId" @change="selectedSubClusterIds = []" required
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

                            <div class="rounded-xl border border-slate-300 bg-white">
                                <div x-show="selectedSubClusterDetail.length > 0"
                                    class="border-b border-slate-200 bg-slate-50 p-3">
                                    <p class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-500">
                                        Sub-Cluster Terpilih
                                    </p>

                                    <div class="flex flex-wrap gap-2">
                                        <template x-for="subCluster in selectedSubClusterDetail" :key="subCluster.id">
                                            <span
                                                class="inline-flex items-center gap-2 rounded-full bg-blue-100 px-3 py-1 text-xs font-bold text-blue-700">
                                                <span x-text="subCluster.nama_sub_cluster"></span>

                                                <button type="button" @click="removeSubCluster(subCluster.id)"
                                                    class="text-blue-500 hover:text-red-500">
                                                    ×
                                                </button>
                                            </span>
                                        </template>
                                    </div>
                                </div>

                                <div class="max-h-56 overflow-y-auto p-3">
                                    <template x-if="!selectedClusterId">
                                        <p class="rounded-lg bg-slate-50 px-3 py-3 text-sm text-slate-500">
                                            Pilih cluster terlebih dahulu.
                                        </p>
                                    </template>

                                    <template x-if="selectedClusterId && filteredSubClusters.length === 0">
                                        <p class="rounded-lg bg-slate-50 px-3 py-3 text-sm text-slate-500">
                                            Sub-cluster belum tersedia untuk cluster ini.
                                        </p>
                                    </template>

                                    <template x-for="subCluster in filteredSubClusters" :key="subCluster.id">
                                        <label
                                            class="mb-2 flex cursor-pointer items-start gap-3 rounded-lg border border-slate-100 px-3 py-2 text-sm hover:bg-blue-50">
                                            <input type="checkbox" name="sub_cluster_ids[]" :value="subCluster.id"
                                                x-model="selectedSubClusterIds"
                                                class="mt-1 rounded border-slate-300 text-blue-600 focus:ring-blue-500">

                                            <span class="font-medium text-slate-700"
                                                x-text="subCluster.nama_sub_cluster"></span>
                                        </label>
                                    </template>
                                </div>
                            </div>

                            <p class="mt-1 text-xs text-slate-500">
                                Pilih satu atau lebih sub-cluster.
                            </p>
                        </div>

                        <div class="lg:col-span-2">
                            <label class="mb-2 block text-sm font-semibold text-slate-700">
                                Komite Dewas <span class="font-normal text-slate-400">(Opsional)</span>
                            </label>

                            <select name="komite_id"
                                class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Tidak memilih komite</option>
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
