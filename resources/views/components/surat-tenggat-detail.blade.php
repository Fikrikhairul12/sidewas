@props(['expression'])
<div data-detail-tenggat class="mt-3 break-words rounded-xl bg-slate-50 p-3 text-xs text-slate-600" x-show="{{ $expression }}">
    <p class="font-semibold" x-text="{{ $expression }}?.label"></p>
    <p x-text="{{ $expression }}?.verified ? 'Batas: ' + {{ $expression }}.batas : 'Tanggal belum terverifikasi. Batas otomatis belum diberlakukan.'"></p>
    <p x-show="{{ $expression }}?.mulai" x-text="'Mulai: ' + ({{ $expression }}?.mulai ?? '-')"></p>
    <p x-show="{{ $expression }}?.late" class="font-semibold text-amber-800">Terlambat</p>
    <details x-show="{{ $expression }}?.history?.length" class="mt-2">
        <summary class="cursor-pointer font-semibold">Riwayat tanggal</summary>
        <template x-for="(tanggal, index) in ({{ $expression }}?.history ?? [])" :key="index">
            <p class="mt-2" x-text="tanggal.jenis + ': ' + (tanggal.diterapkan ? tanggal.lama + ' → ' + tanggal.baru : 'Usulan ' + tanggal.baru + '; tanggal berlaku tetap ' + tanggal.lama) + '. ' + tanggal.alasan + ' (' + tanggal.pengguna + ', ' + tanggal.waktu + ').'"></p>
        </template>
    </details>
</div>
