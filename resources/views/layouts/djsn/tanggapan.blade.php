<x-app-layout>
    <div x-data="{ openModal: false, selectedButir: null }" class="space-y-6">
        {{-- HEADER --}}
        <div class="rounded-2xl border border-blue-100 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide" style="color: #2377b9;">
                        DJSN
                    </p>

                    <h1 class="mt-2 text-3xl font-bold text-slate-800">
                        Tanggapan DJSN
                    </h1>

                    <p class="mt-2 text-sm text-slate-500">
                        Halaman ini digunakan untuk memberikan tanggapan terhadap butir DJSN yang menjadi tanggung jawab
                        PIC.
                    </p>
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

        {{-- FILTER --}}
        @include('layouts.djsn.partials.filter-lanjutan', [
            'action' => route('djsn.tanggapan.index'),
            'statusOptions' => [
                'belum' => 'Belum Ditanggapi',
                'sudah' => 'Sudah Ditanggapi',
            ],
            'keywordPlaceholder' => 'Cari ID DJSN, ID butir, nomor surat, perihal, isi butir, atau tanggapan...',
        ])

        {{-- TABLE --}}
        <div class="overflow-hidden rounded-2xl border border-blue-100 bg-white shadow-sm">
            <div
                class="flex flex-col gap-3 border-b border-blue-50 px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-bold text-slate-800">
                        Daftar Butir DJSN
                    </h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Setiap butir hanya bisa memiliki satu tanggapan.
                    </p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-600">
                                Informasi DJSN
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-600">
                                Butir DJSN
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-600">
                                PIC
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-600">
                                Tanggapan
                            </th>
                            <th class="px-6 py-4 text-center text-xs font-bold uppercase tracking-wider text-slate-600">
                                Aksi
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-200 bg-white">
                        @forelse ($butirs as $butir)
                            @php
                                $picUtama = $butir->butirPics->where('jenis_pic', 'utama')->first();
                                $picPendukung = $butir->butirPics->where('jenis_pic', 'pendukung');
                                $canRespond =
                                    \App\Models\User::find(auth()->id())?->canCreateDjsnTanggapanForButir($butir) ??
                                    false;
                            @endphp

                            <tr class="hover:bg-blue-50/40">
                                <td class="px-6 py-6 align-top">
                                    <p class="text-xs font-bold" style="color: #2377b9;">
                                        {{ $butir->record?->id_djsn ?? '-' }}
                                    </p>

                                    <p class="mt-2 text-xs text-slate-700">
                                        Nomor: {{ $butir->record?->nomor_surat ?? '-' }}
                                    </p>

                                    <p class="mt-1 text-xs text-slate-700">
                                        Tanggal:
                                        {{ $butir->record?->tanggal_surat ? \Carbon\Carbon::parse($butir->record->tanggal_surat)->format('d/m/Y') : '-' }}
                                    </p>

                                    <p
                                        class="mt-3 max-w-sm text-xs font-medium uppercase leading-relaxed text-slate-800">
                                        {{ $butir->record?->perihal_surat ?? '-' }}
                                    </p>

                                    <div class="mt-4">
                                        <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                                            Dokumen Surat
                                        </p>

                                        @if ($butir->record?->dokumen)
                                            <a href="{{ route('djsn.perekaman.dokumen', $butir->record->id) }}"
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
                                </td>

                                <td class="px-6 py-6 align-top">
                                    <p class="text-xs font-bold" style="color: #2377b9;">
                                        {{ $butir->id_butir_djsn }}
                                    </p>

                                    <p
                                        class="mt-3 max-w-lg text-xs font-medium uppercase leading-relaxed text-slate-800">
                                        {{ $butir->butir_djsn }}
                                    </p>
                                </td>

                                <td class="px-6 py-6 align-top">
                                    <div>
                                        <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                                            PIC Utama
                                        </p>

                                        @if ($picUtama?->unitKerja)
                                            <span
                                                class="mt-2 inline-flex rounded-xl px-3 py-1 text-xs font-bold text-white"
                                                style="background-color: #6bb17e;">
                                                {{ $picUtama->unitKerja->kode_unit }} -
                                                {{ $picUtama->unitKerja->nama_unit }}
                                            </span>
                                        @else
                                            <p class="mt-1 text-sm text-slate-400">-</p>
                                        @endif
                                    </div>

                                    <div class="mt-4">
                                        <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                                            PIC Pendukung
                                        </p>

                                        @if ($picPendukung->count() > 0)
                                            <div class="mt-2 flex flex-wrap gap-2">
                                                @foreach ($picPendukung as $pic)
                                                    @if ($pic->unitKerja)
                                                        <span
                                                            class="inline-flex rounded-full px-3 py-1 text-xs font-bold text-slate-700"
                                                            style="background-color: #c8e079;">
                                                            {{ $pic->unitKerja->kode_unit }}
                                                        </span>
                                                    @endif
                                                @endforeach
                                            </div>
                                        @else
                                            <p class="mt-1 text-sm text-slate-400">-</p>
                                        @endif
                                    </div>
                                </td>

                                <td class="px-6 py-6 align-top">
                                    @if ($butir->tanggapan)
                                        <span
                                            class="inline-flex text-center rounded-full px-3 py-1 text-xs font-bold text-white"
                                            style="background-color: #6bb17e;">
                                            Sudah Ditanggapi
                                        </span>

                                        <p class="whitespace-pre-line mt-3 text-xs text-slate-700">
                                            {{ $butir->tanggapan->tanggapan }}
                                        </p>

                                        <p class="mt-2 text-xs text-slate-500">
                                            Oleh: {{ $butir->tanggapan->creator?->name ?? '-' }}
                                        </p>
                                    @else
                                        <span
                                            class="inline-flex text-center rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-500">
                                            Belum Ditanggapi
                                        </span>
                                    @endif
                                </td>

                                <td class="px-6 py-6 align-top">
                                    <div class="flex justify-center">
                                        @if (!$butir->tanggapan && $canRespond)
                                            <button type="button"
                                                @click="selectedButir = {
                                                        id: {{ $butir->id }},
                                                        id_butir_djsn: @js($butir->id_butir_djsn),
                                                        id_djsn: @js($butir->record?->id_djsn)
                                                    }; openModal = true"
                                                class="rounded-lg px-4 py-2 text-xs font-bold text-white shadow-sm hover:opacity-90"
                                                style="background-color: #2377b9;">
                                                Beri Tanggapan
                                            </button>
                                        @elseif ($butir->tanggapan)
                                            <span class="text-xs font-semibold text-slate-400">
                                                Tanggapan sudah ada
                                            </span>
                                        @else
                                            <span class="text-xs font-semibold text-slate-400">
                                                Tidak memiliki akses
                                            </span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center">
                                    <p class="text-sm font-semibold text-slate-600">
                                        Belum ada butir DJSN yang dapat ditanggapi.
                                    </p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div
                class="flex flex-col gap-3 border-t border-slate-100 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-slate-500">
                    Menampilkan
                    <span class="font-semibold text-slate-700">{{ $butirs->firstItem() ?? 0 }}</span>
                    -
                    <span class="font-semibold text-slate-700">{{ $butirs->lastItem() ?? 0 }}</span>
                    dari
                    <span class="font-semibold text-slate-700">{{ $butirs->total() }}</span>
                    entri
                </p>

                <div>
                    {{ $butirs->links() }}
                </div>
            </div>
        </div>

        {{-- Modal Tanggapan --}}
        <div x-show="openModal" x-transition.opacity
            class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/60 px-4 py-8"
            style="display: none;">
            <div @click.outside="openModal = false" x-transition
                class="w-full max-w-3xl overflow-hidden rounded-2xl bg-white shadow-2xl">

                <div class="flex items-start justify-between border-b border-slate-100 px-6 py-5">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-wide" style="color: #2377b9;">
                            Form Tanggapan DJSN
                        </p>

                        <h2 class="mt-1 text-2xl font-bold text-slate-800">
                            <span x-text="selectedButir?.id_butir_djsn"></span>
                        </h2>

                        <p class="mt-1 text-sm text-slate-500">
                            Satu butir DJSN hanya dapat memiliki satu tanggapan.
                        </p>
                    </div>

                    <button type="button" @click="openModal = false"
                        class="rounded-xl p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                        ✕
                    </button>
                </div>

                <form method="POST" enctype="multipart/form-data" :action="`/djsn/tanggapan/${selectedButir?.id}`"
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

                    <div class="grid gap-5">
                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-700">
                                Tanggapan
                            </label>
                            <textarea name="tanggapan" rows="4" required
                                class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                placeholder="Nomor surat tanggapan (B/XXXX/MMYYYY)&#10;Tanggal (DD-MM-YYYY)&#10;&#10;Isi tanggapan...">{{ old('tanggapan') }}</textarea>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-700">
                                Deliverables
                            </label>
                            <textarea name="deliverables" rows="3" required
                                class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                placeholder="Masukkan deliverables...">{{ old('deliverables') }}</textarea>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-700">
                                Dokumen Pendukung
                            </label>
                            <input type="file" name="dokumen"
                                class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <p class="mt-1 text-xs text-slate-500">
                                Opsional. Format: PDF, Word, Excel, JPG, PNG. Maksimal 5 MB.
                            </p>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-700">
                                Ubah Tanggal Jatuh Tempo
                            </label>
                            <input type="date" name="ubah_tgl" value="{{ old('ubah_tgl') }}"
                                class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <p class="mt-1 text-xs text-slate-500">
                                Opsional jika mengajukan perubahan tanggal.
                            </p>
                        </div>
                    </div>

                    <div class="mt-8 flex justify-end gap-3 border-t border-slate-100 pt-5">
                        <button type="button" @click="openModal = false"
                            class="rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                            Batal
                        </button>

                        <button type="submit"
                            class="rounded-xl px-5 py-3 text-sm font-semibold text-white shadow-sm hover:opacity-90"
                            style="background-color: #2377b9;">
                            Simpan Tanggapan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
