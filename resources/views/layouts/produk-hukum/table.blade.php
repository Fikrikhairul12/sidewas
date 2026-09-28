<div class="overflow-x-auto focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-sidewas-blue"
    role="region" aria-label="Tabel produk hukum, geser horizontal untuk melihat seluruh kolom" tabindex="0">
    <table class="w-full min-w-[1120px] table-fixed divide-y divide-slate-200 text-left text-sm">
        <caption class="sr-only">Daftar produk hukum beserta sifat dokumen, status peraturan, dan aksi.</caption>
        <colgroup>
            <col class="w-[4%]">
            <col class="w-[28%]">
            <col class="w-[13%]">
            <col class="w-[6%]">
            <col class="w-[16%]">
            <col class="w-[8%]">
            <col class="w-[10%]">
            <col class="w-[15%]">
        </colgroup>
        <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
            <tr>
                <th scope="col" class="px-4 py-4 text-center">No.</th>
                <th scope="col" class="px-4 py-4">Produk Hukum</th>
                <th scope="col" class="px-4 py-4">Nomor</th>
                <th scope="col" class="px-4 py-4">Tahun</th>
                <th scope="col" class="px-4 py-4">Jenis / Bidang</th>
                <th scope="col" class="px-4 py-4">Sifat</th>
                <th scope="col" class="px-4 py-4">Status</th>
                <th scope="col" class="px-4 py-4">Aksi</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($produkHukums as $produk)
                @php
                    $canAccessProduk = $produk->sifat_dokumen === 'publik'
                        || $canViewRahasiaProdukHukum
                        || in_array((int) $produk->id, $approvedAccessIds, true);
                    $pendingAccess = in_array((int) $produk->id, $pendingAccessIds, true);
                    $pendingDelete = in_array((int) $produk->id, $pendingDeleteIds, true);
                    $statusClass = match ($produk->status_peraturan) {
                        'berlaku' => 'bg-blue-100 text-blue-700',
                        'tidak_berlaku' => 'bg-slate-100 text-slate-600',
                        default => 'bg-amber-100 text-amber-800',
                    };
                @endphp
                <tr class="align-top transition-colors hover:bg-blue-50/40">
                    <td class="px-4 py-5 text-center tabular-nums text-slate-500">
                        {{ $produkHukums->firstItem() + $loop->index }}
                    </td>
                    <th scope="row" class="px-4 py-5 font-normal [overflow-wrap:anywhere]">
                        <p class="font-semibold leading-6 text-slate-800">{{ $produk->judul }}</p>
                        <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
                            <span class="font-semibold text-sidewas-blue">{{ $produk->kode_produk_hukum }}</span>
                            <span class="text-slate-500">{{ $produk->files_count }} file</span>
                        </div>
                        @if ($produk->sifat_dokumen === 'rahasia' && ! $canAccessProduk)
                            <p class="mt-2 text-xs leading-5 text-orange-700">Detail lengkap dokumen ini bersifat rahasia.</p>
                        @endif
                    </th>
                    <td class="px-4 py-5 leading-6 text-slate-600 [overflow-wrap:anywhere]">
                        {{ $produk->nomor_peraturan_keputusan ?? '-' }}
                    </td>
                    <td class="px-4 py-5 tabular-nums leading-6 text-slate-600">
                        {{ $produk->tahun_peraturan ?? '-' }}
                    </td>
                    <td class="px-4 py-5 [overflow-wrap:anywhere]">
                        <p class="leading-6 text-slate-700">{{ $produk->jenis_bentuk_peraturan ?? '-' }}</p>
                        <p class="mt-1 text-xs leading-5 text-slate-500">Bidang: {{ $produk->bidang_pengaturan ?? '-' }}</p>
                    </td>
                    <td class="px-4 py-5">
                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $produk->sifat_dokumen === 'rahasia' ? 'bg-orange-100 text-orange-700' : 'bg-green-100 text-green-700' }}">
                            {{ ucfirst($produk->sifat_dokumen) }}
                        </span>
                    </td>
                    <td class="px-4 py-5">
                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClass }}">
                            {{ ucwords(str_replace('_', ' ', $produk->status_peraturan ?? '-')) }}
                        </span>
                    </td>
                    <td class="px-4 py-5">
                        <div class="flex flex-wrap items-start gap-2">
                            @if ($canAccessProduk)
                                <a href="{{ route('produk-hukum.show', $produk) }}"
                                    aria-label="Detail {{ $produk->kode_produk_hukum }}"
                                    class="inline-flex items-center justify-center rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-xs font-semibold text-green-700 transition hover:bg-green-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-green-700">
                                    Detail
                                </a>
                            @elseif ($pendingAccess)
                                <button type="button" disabled
                                    class="cursor-not-allowed rounded-lg border border-slate-200 bg-slate-100 px-3 py-2 text-xs font-semibold text-slate-500">
                                    Menunggu Approval
                                </button>
                            @else
                                <form method="POST" action="{{ route('produk-hukum.request-access', $produk->id) }}">
                                    @csrf
                                    <input type="hidden" name="reason" value="Mengajukan akses lihat produk hukum rahasia.">
                                    <button type="submit"
                                        class="rounded-lg border border-orange-200 bg-orange-50 px-3 py-2 text-xs font-semibold text-orange-700 transition hover:bg-orange-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-orange-700">
                                        Ajukan Akses
                                    </button>
                                </form>
                            @endif

                            @if ($canDeleteProdukHukum)
                                @if ($pendingDelete)
                                    <button type="button" disabled
                                        class="cursor-not-allowed rounded-lg border border-slate-200 bg-slate-100 px-3 py-2 text-xs font-semibold text-slate-500">
                                        Menunggu Hapus
                                    </button>
                                @else
                                    <form method="POST" action="{{ route('produk-hukum.request-delete', $produk->id) }}"
                                        onsubmit="return confirm('Ajukan penghapusan Produk Hukum ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="reason" value="Mengajukan hapus Produk Hukum.">
                                        <button type="submit" aria-label="Hapus {{ $produk->kode_produk_hukum }}"
                                            class="rounded-lg border border-red-200 bg-white px-3 py-2 text-xs font-semibold text-red-600 transition hover:bg-red-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600">
                                            Hapus
                                        </button>
                                    </form>
                                @endif
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="px-6 py-14 text-center text-sm font-semibold text-slate-600">
                        Belum ada Produk Hukum.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="flex flex-col gap-3 border-t border-slate-100 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
    <p class="text-sm text-slate-500">
        Menampilkan
        <span class="font-semibold text-slate-700">{{ $produkHukums->firstItem() ?? 0 }}</span>
        -
        <span class="font-semibold text-slate-700">{{ $produkHukums->lastItem() ?? 0 }}</span>
        dari
        <span class="font-semibold text-slate-700">{{ $produkHukums->total() }}</span>
        produk hukum
    </p>
    @include('layouts.partials.compact-pagination', ['paginator' => $produkHukums])
</div>
