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
                                $userPayload = $payload['payload'] ?? [];
                                $canRejectItem = in_array($item->status, ['pending_admin_verification', 'pending_super_admin_approval'], true)
                                    && ($authUser?->canApprovePengajuan() || $authUser?->canVerifyPengajuanType($item->type_code));
                            @endphp
                            <tr class="hover:bg-blue-50/40">
                                <td class="px-6 py-5 align-top">
                                    <span class="inline-flex text-center rounded-full px-3 py-1 text-xs font-bold {{ $isUserRequest ? 'bg-blue-100 text-blue-700' : ($isProdukHukumView ? 'bg-orange-100 text-orange-700' : 'bg-red-100 text-red-600') }}">
                                        @if ($isUserUpdate)
                                            Edit User
                                        @elseif ($isUserDelete)
                                            Hapus User
                                        @elseif ($isProdukHukumView)
                                            Lihat Produk Hukum
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
                                    @elseif ($isProdukHukumView)
                                        <div class="mt-2 space-y-1 text-sm text-slate-600">
                                            <p>Kode: {{ $payload['kode_produk_hukum'] ?? '-' }}</p>
                                            <p>Judul: {{ $payload['judul'] ?? '-' }}</p>
                                            <p>Catatan: {{ $payload['catatan'] ?? '-' }}</p>
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
                                                onsubmit="return confirm('{{ $isUserUpdate ? 'Setujui perubahan user ini?' : ($isUserDelete ? 'Setujui penghapusan user ini? User akan dihapus.' : ($isProdukHukumView ? 'Setujui akses lihat Produk Hukum ini?' : 'Setujui penghapusan data ini? Data akan dihapus permanen.')) }}')">
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
    </div>
</x-app-layout>
