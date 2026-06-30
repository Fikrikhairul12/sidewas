<x-app-layout>
    <div class="space-y-6">
        <div class="rounded-2xl border border-blue-100 bg-white p-6 shadow-sm">
            <p class="text-sm font-semibold uppercase tracking-wide" style="color: #2377b9;">
                Administrasi
            </p>

            <h1 class="mt-2 text-3xl font-bold text-slate-800">
                Pengajuan
            </h1>

            <p class="mt-2 text-sm text-slate-500">
                Halaman ini berisi daftar pengajuan yang membutuhkan verifikasi atau approval.
            </p>
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

        <div class="overflow-hidden rounded-2xl border border-blue-100 bg-white shadow-sm">
            <div class="border-b border-blue-50 px-6 py-5">
                <h2 class="text-lg font-bold text-slate-800">
                    Daftar Pengajuan
                </h2>

                <p class="mt-1 text-sm text-slate-500">
                    Pengajuan edit/hapus user, hapus perekaman, dan akses dokumen rahasia akan diproses sesuai alur approval.
                </p>
            </div>

            <div class="overflow-x-auto">
                @php
                    $authUser = \App\Models\User::find(auth()->id());
                    $snpClusterLabels = \App\Models\SnpCluster::query()
                        ->pluck('nama_cluster', 'id')
                        ->all();
                    $snpSubClusterLabels = \App\Models\SnpSubCluster::query()
                        ->pluck('nama_sub_cluster', 'id')
                        ->all();
                    $unitKerjaLabels = \App\Models\UnitKerja::query()
                        ->get(['id', 'kode_unit', 'nama_unit'])
                        ->mapWithKeys(fn ($unit) => [$unit->id => trim(($unit->kode_unit ?? '-') . ' - ' . ($unit->nama_unit ?? '-'))])
                        ->all();
                    $komiteLabels = \App\Models\Komite::query()
                        ->get(['id', 'kode_komite', 'nama_komite'])
                        ->mapWithKeys(fn ($komite) => [$komite->id => trim(($komite->kode_komite ?? '-') . ' - ' . ($komite->nama_komite ?? '-'))])
                        ->all();
                    $statusLabels = [
                        'draft' => 'Draft',
                        'terbit' => 'Terbit',
                        'dalam_proses' => 'Dalam Proses',
                        'diusulkan_tuntas' => 'Diusulkan Tuntas',
                        'selesai_tuntas' => 'Selesai Tuntas',
                        'tuntas' => 'Tuntas',
                    ];
                @endphp
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-600">
                                Jenis Pengajuan
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-600">
                                Data
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-600">
                                Pengaju
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-600">
                                Status
                            </th>
                            <th class="px-6 py-4 text-center text-xs font-bold uppercase tracking-wider text-slate-600">
                                Aksi
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse ($pengajuan as $item)
                            @php
                                $rawReason = $item->reason ?? '';
                                [$jsonReason, $rejectReason] = array_pad(explode("\n\nAlasan penolakan: ", $rawReason, 2), 2, null);
                                $payload = json_decode($jsonReason, true);
                                $payload = is_array($payload) ? $payload : [];
                                $isUserRequest = $item->table_name === 'users';
                                $userAction = $payload['action'] ?? null;
                                $isUserUpdate = $isUserRequest && $userAction === 'update_user';
                                $isUserDelete = $isUserRequest && $userAction === 'delete_user';
                                $isProdukHukumView = $item->type_code === 'produk_hukum'
                                    && $item->table_name === 'tb_produk_hukum'
                                    && ($payload['action'] ?? null) === 'view_produk_hukum';
                                $isProdukHukumDelete = $item->type_code === 'produk_hukum'
                                    && $item->table_name === 'tb_produk_hukum'
                                    && ($payload['action'] ?? null) === 'delete_produk_hukum';
                                $userPayload = $payload['payload'] ?? [];
                                $isSnpPerekamanUpdate = $item->type_code === 'snp'
                                    && $item->table_name === 'tb_record'
                                    && ($payload['action'] ?? null) === 'update_snp_perekaman';
                                $perekamanUpdateActions = [
                                    'snp' => 'update_snp_perekaman',
                                    'ragab' => 'update_ragab_perekaman',
                                    'rawas' => 'update_rawas_perekaman',
                                    'djsn' => 'update_djsn_perekaman',
                                    'eksternal' => 'update_eksternal_perekaman',
                                ];
                                $isPerekamanUpdate = $item->table_name === 'tb_record'
                                    && ($payload['action'] ?? null) === ($perekamanUpdateActions[$item->type_code] ?? null);
                                $snpEditPayload = $isSnpPerekamanUpdate ? ($payload['payload'] ?? []) : [];
                                $snpEditRecordPayload = $snpEditPayload['record'] ?? [];
                                $snpEditButirPayload = $snpEditPayload['butir'] ?? [];
                                $snpEditFiles = $snpEditPayload['files'] ?? [];
                                $snpEditPicPendukungLabels = collect($snpEditButirPayload['unit_kerja_pendukung_ids'] ?? [])
                                    ->map(fn ($unitId) => $unitKerjaLabels[$unitId] ?? $unitId)
                                    ->filter()
                                    ->join(', ');
                                $snpEditDetailPayload = [
                                    'title' => 'Detail Edit Perekaman',
                                    'subtitle' => $item->record_label,
                                    'record_key' => $item->record_key,
                                    'surat' => [
                                        'Tanggal Surat' => $snpEditRecordPayload['tanggal_surat'] ?? '-',
                                        'Perihal' => $snpEditRecordPayload['perihal_surat'] ?? '-',
                                        'Cluster' => $snpClusterLabels[$snpEditRecordPayload['cluster_id'] ?? null] ?? '-',
                                        'Sub-Cluster' => $snpSubClusterLabels[$snpEditRecordPayload['sub_cluster_id'] ?? null] ?? '-',
                                        'Status Surat' => $statusLabels[$snpEditRecordPayload['status'] ?? null] ?? '-',
                                    ],
                                    'butir' => [
                                        'ID Butir' => $snpEditButirPayload['id_butir_snp'] ?? '-',
                                        'Status Butir' => $statusLabels[$snpEditButirPayload['status'] ?? null] ?? '-',
                                        'PIC Utama' => $unitKerjaLabels[$snpEditButirPayload['unit_kerja_utama_id'] ?? null] ?? '-',
                                        'PIC Pendukung' => $snpEditPicPendukungLabels ?: '-',
                                        'Komite' => $komiteLabels[$snpEditButirPayload['komite_id'] ?? null] ?? '-',
                                        'Dokumen Diganti' => empty($snpEditFiles) ? 'Tidak' : collect(array_keys($snpEditFiles))->map(fn ($file) => str_replace('_', ' ', $file))->join(', '),
                                    ],
                                    'isi_butir' => $snpEditButirPayload['butir_snp'] ?? '-',
                                ];
                                $editPayload = $isPerekamanUpdate ? ($payload['payload'] ?? []) : [];
                                $editRecordPayload = $editPayload['record'] ?? [];
                                $editButirPayload = $editPayload['butir'] ?? [];
                                $editFiles = $editPayload['files'] ?? [];
                                $editButirId = $editButirPayload['id_butir_snp']
                                    ?? $editButirPayload['id_butir_ragab']
                                    ?? $editButirPayload['id_butir_rawas']
                                    ?? $editButirPayload['id_butir_djsn']
                                    ?? $editButirPayload['id_butir_eksternal']
                                    ?? '-';
                                $editIsiButir = $editButirPayload['butir_snp']
                                    ?? $editButirPayload['butir_djsn']
                                    ?? $editButirPayload['keputusan_ragab']
                                    ?? $editButirPayload['keputusan_rawas']
                                    ?? $editButirPayload['keputusan_eksternal']
                                    ?? '-';
                                $editUnitLabels = collect($editButirPayload['unit_kerja_ids'] ?? $editButirPayload['unit_kerja_pendukung_ids'] ?? [])
                                    ->map(fn ($unitId) => $unitKerjaLabels[$unitId] ?? $unitId)
                                    ->filter()
                                    ->join(', ');
                                $editPicIds = collect($editButirPayload['pic_ids'] ?? [])
                                    ->map(function ($picId) use ($unitKerjaLabels, $komiteLabels) {
                                        [$type, $id] = str_contains((string) $picId, ':')
                                            ? explode(':', (string) $picId, 2)
                                            : ['unit', $picId];

                                        return $type === 'komite'
                                            ? ($komiteLabels[$id] ?? $picId)
                                            : ($unitKerjaLabels[$id] ?? $picId);
                                    })
                                    ->join(', ');
                                $editDetailPayload = [
                                    'title' => 'Detail Edit Perekaman',
                                    'subtitle' => $item->record_label,
                                    'record_key' => $item->record_key,
                                    'surat' => [
                                        'Nomor Surat' => $editRecordPayload['nomor_surat'] ?? '-',
                                        'Tanggal Surat' => $editRecordPayload['tanggal_surat'] ?? '-',
                                        'Instansi Pengundang' => $editRecordPayload['nama_instansi_pengundang'] ?? '-',
                                        'Perihal' => $editRecordPayload['perihal_surat'] ?? '-',
                                        'Status Surat' => $statusLabels[$editRecordPayload['status'] ?? null] ?? ($editRecordPayload['status'] ?? '-'),
                                        'Dokumen Diganti' => empty($editFiles) ? 'Tidak' : collect(array_keys($editFiles))->map(fn ($file) => str_replace('_', ' ', $file))->join(', '),
                                    ],
                                    'butir' => [
                                        'ID Butir' => $editButirId,
                                        'Tanggal Butir' => $editButirPayload['tanggal_ragab']
                                            ?? $editButirPayload['tanggal_rawas']
                                            ?? $editButirPayload['tanggal_eksternal']
                                            ?? '-',
                                        'Agenda' => $editButirPayload['agenda_ragab']
                                            ?? $editButirPayload['agenda_rawas']
                                            ?? $editButirPayload['agenda_eksternal']
                                            ?? '-',
                                        'Status Butir' => $statusLabels[$editButirPayload['status'] ?? null] ?? ($editButirPayload['status'] ?? '-'),
                                        'Cluster ID' => $editButirPayload['cluster_id'] ?? '-',
                                        'Sub-Cluster ID' => collect($editButirPayload['sub_cluster_ids'] ?? [$editButirPayload['sub_cluster_id'] ?? null])->filter()->join(', ') ?: '-',
                                        'PIC Utama' => $unitKerjaLabels[$editButirPayload['unit_kerja_utama_id'] ?? null] ?? '-',
                                        'PIC Unit/Pendukung' => $editUnitLabels ?: ($editPicIds ?: '-'),
                                        'Komite' => $komiteLabels[$editButirPayload['komite_id'] ?? null] ?? '-',
                                    ],
                                    'isi_butir' => $editIsiButir,
                                ];
                                $canRejectItem = in_array($item->status, ['pending_admin_verification', 'pending_super_admin_approval'], true)
                                    && ($authUser?->canApprovePengajuan() || $authUser?->canVerifyPengajuanType($item->type_code));
                            @endphp
                            <tr class="hover:bg-blue-50/40">
                                <td class="px-6 py-5 align-top">
                                    <span class="inline-flex text-center rounded-full px-3 py-1 text-xs font-bold {{ $isUserRequest ? 'bg-blue-100 text-blue-700' : ($isProdukHukumView ? 'bg-orange-100 text-orange-700' : ($isProdukHukumDelete ? 'bg-red-100 text-red-600' : ($isPerekamanUpdate ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-600'))) }}">
                                        @if ($isUserUpdate)
                                            Edit User
                                        @elseif ($isUserDelete)
                                            Hapus User
                                        @elseif ($isProdukHukumView)
                                            Lihat Produk Hukum
                                        @elseif ($isProdukHukumDelete)
                                            Hapus Produk Hukum
                                        @elseif ($isPerekamanUpdate)
                                            Edit Perekaman
                                        @else
                                            Hapus Perekaman
                                        @endif
                                    </span>

                                    <p class="mt-2 text-xs text-slate-500">
                                        {{ strtoupper($item->type_code ?? 'administrasi') }}
                                    </p>
                                </td>

                                <td class="px-6 py-5 align-top">
                                    <p class="font-bold text-slate-800">
                                        {{ $item->record_label }}
                                    </p>

                                    <p class="mt-1 text-sm text-slate-500">
                                        Record Key: {{ $item->record_key }}
                                    </p>

                                    @if ($isUserRequest)
                                        <div class="mt-2 space-y-1 text-sm text-slate-600">
                                            <p>Nama: {{ $userPayload['name'] ?? '-' }}</p>
                                            <p>Email: {{ $userPayload['email'] ?? '-' }}</p>
                                            @if ($isUserUpdate)
                                                <p>Role ID: {{ collect($userPayload['role_type_ids'] ?? [])->join(', ') ?: '-' }}</p>
                                                <p>
                                                    Unit/Komite:
                                                    {{ ($userPayload['assignment']['type'] ?? null) ? (($userPayload['assignment']['type'] ?? '-') . ':' . ($userPayload['assignment']['id'] ?? '-')) : '-' }}
                                                </p>
                                            @endif
                                        </div>
                                    @elseif ($isProdukHukumView || $isProdukHukumDelete)
                                        <div class="mt-2 space-y-1 text-sm text-slate-600">
                                            <p>Kode: {{ $payload['kode_produk_hukum'] ?? '-' }}</p>
                                            <p>Judul: {{ $payload['judul'] ?? '-' }}</p>
                                            @if ($isProdukHukumDelete)
                                                <p>Nomor: {{ $payload['nomor_peraturan_keputusan'] ?? '-' }}</p>
                                                <p>Tahun: {{ $payload['tahun_peraturan'] ?? '-' }}</p>
                                                <p>Jenis: {{ $payload['jenis_bentuk_peraturan'] ?? '-' }}</p>
                                                <p>Sifat: {{ ucfirst($payload['sifat_dokumen'] ?? '-') }}</p>
                                            @endif
                                            <p>Catatan: {{ $payload['catatan'] ?? '-' }}</p>
                                        </div>
                                    @elseif ($isPerekamanUpdate)
                                        <div class="mt-2 space-y-1 text-sm text-slate-600">
                                            <p>Butir: {{ $editButirId }}</p>
                                            <p>Status Butir: {{ $statusLabels[$editButirPayload['status'] ?? null] ?? '-' }}</p>
                                            <p>Perihal: {{ $editRecordPayload['perihal_surat'] ?? '-' }}</p>
                                            <p class="text-xs text-slate-500">Detail perubahan lengkap ada di tombol Detail.</p>
                                        </div>
                                    @else
                                        <p class="mt-2 text-sm text-slate-600">
                                            Alasan: {{ $jsonReason ?: '-' }}
                                        </p>
                                    @endif
                                </td>

                                <td class="px-6 py-5 align-top">
                                    <p class="text-sm font-semibold text-slate-800">
                                        {{ $item->requester?->name ?? '-' }}
                                    </p>

                                    <p class="text-xs text-slate-500">
                                        {{ $item->requested_at?->format('d/m/Y H:i') ?? '-' }}
                                    </p>
                                </td>

                                <td class="px-6 py-5 align-top">
                                    @php
                                        $statusLabel =
                                            [
                                                'pending_admin_verification' => 'Menunggu Verifikasi Admin',
                                                'pending_super_admin_approval' => 'Menunggu Approval Super Admin',
                                                'approved' => 'Disetujui',
                                                'rejected' => 'Ditolak',
                                                'cancelled' => 'Dibatalkan',
                                            ][$item->status] ?? $item->status;
                                    @endphp

                                    <span class="inline-flex text-center rounded-full px-3 py-1 text-xs font-bold text-white"
                                        style="background-color: #2377b9;">
                                        {{ $statusLabel }}
                                    </span>

                                    <div class="mt-3 space-y-2 text-xs text-slate-500">
                                        <p>
                                            Diajukan:
                                            <span class="font-semibold text-slate-700">
                                                {{ $item->requested_at?->format('d/m/Y H:i') ?? '-' }}
                                            </span>
                                        </p>

                                        @if ($item->verifier)
                                            <p>
                                                Diverifikasi:
                                                <span class="font-semibold text-slate-700">{{ $item->verifier->name }}</span>
                                                @if ($item->verified_at)
                                                    <span>({{ $item->verified_at->format('d/m/Y H:i') }})</span>
                                                @endif
                                            </p>
                                        @endif

                                        @if ($item->approver)
                                            <p>
                                                Disetujui:
                                                <span class="font-semibold text-green-700">{{ $item->approver->name }}</span>
                                                @if ($item->approved_at)
                                                    <span>({{ $item->approved_at->format('d/m/Y H:i') }})</span>
                                                @endif
                                            </p>
                                        @endif

                                        @if ($item->rejecter)
                                            <p>
                                                Ditolak:
                                                <span class="font-semibold text-red-600">{{ $item->rejecter->name }}</span>
                                                @if ($item->rejected_at)
                                                    <span>({{ $item->rejected_at->format('d/m/Y H:i') }})</span>
                                                @endif
                                            </p>

                                            @if ($rejectReason)
                                                <p class="rounded-lg bg-red-50 px-3 py-2 text-red-600">
                                                    Alasan: {{ $rejectReason }}
                                                </p>
                                            @endif
                                        @endif
                                    </div>
                                </td>

                                <td class="px-6 py-5 align-top">
                                    <div class="flex flex-wrap justify-center gap-2">
                                        @if ($isPerekamanUpdate)
                                            <button type="button"
                                                data-pengajuan-detail-trigger
                                                data-detail-pengajuan="{{ base64_encode(json_encode($editDetailPayload)) }}"
                                                class="rounded-lg bg-emerald-500 px-4 py-2 text-xs font-bold text-white hover:bg-emerald-600">
                                                Detail
                                            </button>
                                        @endif

                                        @if ($authUser?->canVerifyPengajuanType($item->type_code) && $item->status === 'pending_admin_verification')
                                            <form method="POST"
                                                action="{{ route('administrasi.pengajuan.verify', $item->id) }}">
                                                @csrf
                                                @method('PATCH')

                                                <button type="submit"
                                                    class="rounded-lg px-4 py-2 text-xs font-bold text-white"
                                                    style="background-color: #6bb17e;">
                                                    Verifikasi
                                                </button>
                                            </form>
                                        @endif

                                        @if ($authUser?->canApprovePengajuan() && $item->status === 'pending_super_admin_approval')
                                            <form method="POST"
                                                action="{{ route('administrasi.pengajuan.approve', $item->id) }}"
                                                onsubmit="return confirm('{{ $isUserUpdate ? 'Setujui perubahan user ini?' : ($isUserDelete ? 'Setujui penghapusan user ini? User akan dihapus.' : ($isProdukHukumView ? 'Setujui akses lihat Produk Hukum ini?' : ($isProdukHukumDelete ? 'Setujui penghapusan Produk Hukum ini? Data dan file akan dihapus.' : ($isPerekamanUpdate ? 'Setujui edit perekaman ini?' : 'Setujui penghapusan data ini? Data akan dihapus permanen.')))) }}')">
                                                @csrf
                                                @method('PATCH')

                                                <button type="submit"
                                                    class="rounded-lg px-4 py-2 text-xs font-bold text-white"
                                                    style="background-color: #2377b9;">
                                                    Approve
                                                </button>
                                            </form>
                                        @endif

                                        @if ($canRejectItem)
                                            <form method="POST"
                                                action="{{ route('administrasi.pengajuan.reject', $item->id) }}"
                                                onsubmit="return confirm('Tolak pengajuan ini?')">
                                                @csrf
                                                @method('PATCH')

                                                <button type="submit"
                                                    class="rounded-lg bg-red-500 px-4 py-2 text-xs font-bold text-white hover:bg-red-600">
                                                    Tolak
                                                </button>
                                            </form>
                                        @endif

                                        @if (! $authUser?->canVerifyPengajuanType($item->type_code) && ! ($authUser?->canApprovePengajuan() && $item->status === 'pending_super_admin_approval') && ! $canRejectItem)
                                            <span class="rounded-lg bg-slate-100 px-4 py-2 text-xs font-bold text-slate-500">
                                                Riwayat
                                            </span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center">
                                    <p class="text-sm font-semibold text-slate-600">
                                        Belum ada pengajuan.
                                    </p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-100 px-6 py-4">
                {{ $pengajuan->links() }}
            </div>
        </div>

        <div id="pengajuanDetailModal"
            class="fixed inset-0 z-50 hidden items-start justify-center overflow-y-auto bg-slate-900/60 px-4 py-8">
            <div id="pengajuanDetailDialog"
                class="w-full max-w-4xl overflow-hidden rounded-2xl bg-white shadow-2xl">
                <div class="flex items-start justify-between border-b border-slate-100 px-6 py-5">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-wide" style="color: #2377b9;">
                            Pengajuan
                        </p>
                        <h2 id="pengajuanDetailTitle" class="mt-1 text-2xl font-bold text-slate-800"></h2>
                        <p id="pengajuanDetailSubtitle" class="mt-1 text-sm text-slate-500"></p>
                        <p class="mt-1 text-xs text-slate-400">
                            Record Key: <span id="pengajuanDetailRecordKey">-</span>
                        </p>
                    </div>

                    <button type="button" data-pengajuan-detail-close
                        class="rounded-xl p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="max-h-[70vh] overflow-y-auto px-6 py-6">
                    <div class="grid gap-4 lg:grid-cols-2">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <p class="mb-3 text-xs font-bold uppercase tracking-wide text-slate-500">
                                Perubahan Surat
                            </p>

                            <div class="space-y-3">
                                <div id="pengajuanDetailSurat" class="space-y-3"></div>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <p class="mb-3 text-xs font-bold uppercase tracking-wide text-slate-500">
                                Perubahan Butir
                            </p>

                            <div class="space-y-3">
                                <div id="pengajuanDetailButir" class="space-y-3"></div>
                            </div>
                        </div>

                        <div class="lg:col-span-2 rounded-2xl border border-slate-200 bg-white p-4">
                            <p class="mb-3 text-xs font-bold uppercase tracking-wide text-slate-500">
                                Isi Butir
                            </p>
                            <p id="pengajuanDetailIsiButir"
                                class="whitespace-pre-line text-sm leading-relaxed text-slate-700">-</p>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end border-t border-slate-100 px-6 py-4">
                    <button type="button" data-pengajuan-detail-close
                        class="rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
