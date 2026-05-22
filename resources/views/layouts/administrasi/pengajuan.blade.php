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
                    Daftar Pengajuan Hapus
                </h2>

                <p class="mt-1 text-sm text-slate-500">
                    Untuk saat ini daftar pengajuan berisi pengajuan hapus perekaman SNP.
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
                            <tr class="hover:bg-blue-50/40">
                                <td class="px-6 py-5 align-top">
                                    <span class="inline-flex text-center rounded-full bg-red-100 px-3 py-1 text-xs font-bold text-red-600">
                                        Hapus Perekaman
                                    </span>

                                    <p class="mt-2 text-xs text-slate-500">
                                        {{ strtoupper($item->type_code) }}
                                    </p>
                                </td>

                                <td class="px-6 py-5 align-top">
                                    <p class="font-bold text-slate-800">
                                        {{ $item->record_label }}
                                    </p>

                                    <p class="mt-1 text-sm text-slate-500">
                                        Record Key: {{ $item->record_key }}
                                    </p>

                                    <p class="mt-2 text-sm text-slate-600">
                                        Alasan: {{ $item->reason ?: '-' }}
                                    </p>
                                </td>

                                <td class="px-6 py-5 align-top">
                                    <p class="text-sm font-semibold text-slate-800">
                                        {{ $item->requester?->name ?? '-' }}
                                    </p>

                                    <p class="text-xs text-slate-500">
                                        {{ $item->requested_at?->format('d/m/Y H:i') ?? '-' }}
                                    </p>

                                    @if ($item->verifier)
                                        <p class="mt-2 text-xs text-slate-500">
                                            Diverifikasi oleh:
                                            <span class="font-semibold">{{ $item->verifier->name }}</span>
                                        </p>
                                    @endif
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
                                                onsubmit="return confirm('Setujui penghapusan data ini? Data akan dihapus permanen.')">
                                                @csrf
                                                @method('PATCH')

                                                <button type="submit"
                                                    class="rounded-lg px-4 py-2 text-xs font-bold text-white"
                                                    style="background-color: #2377b9;">
                                                    Approve
                                                </button>
                                            </form>
                                        @endif

                                        @if ($authUser?->canApprovePengajuan() || $authUser?->canVerifyPengajuanType($item->type_code))
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
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center">
                                    <p class="text-sm font-semibold text-slate-600">
                                        Belum ada pengajuan yang perlu diproses.
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
