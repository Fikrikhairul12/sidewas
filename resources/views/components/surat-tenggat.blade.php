@props(['record', 'stage' => null])
@if (\App\Services\IsianUnitTahapService::enabled())
    @php($tenggat = app(\App\Services\SuratTanggalService::class)->presentation($record, $stage))
    <div data-surat-tenggat class="mt-3 space-y-1 break-words text-xs text-slate-600">
        <p class="font-semibold">{{ $tenggat['label'] }}</p>
        @if ($tenggat['verified'])
            <p>Mulai: {{ $tenggat['mulai'] ?? '-' }}</p>
            <p>Batas: {{ $tenggat['batas'] }}</p>
        @else
            <p>Tanggal belum terverifikasi. Batas otomatis belum diberlakukan.</p>
        @endif
        @if ($tenggat['late'])<p data-tenggat-terlambat class="font-semibold text-amber-800">Terlambat</p>@endif
        @if ($tenggat['historis'])<p>Jatuh tempo tercatat sebelumnya: {{ $tenggat['historis'] }}</p>@endif
        @if ($tenggat['history'])
            <details class="mt-2">
                <summary class="cursor-pointer font-semibold text-blue-700">Riwayat tanggal</summary>
                @foreach ($tenggat['history'] as $tanggal)
                    <p class="mt-2">{{ ucfirst($tanggal['jenis']) }}:
                        @if ($tanggal['diterapkan']){{ $tanggal['lama'] }} → {{ $tanggal['baru'] }}.
                        @else Usulan {{ $tanggal['baru'] }}; tanggal berlaku tetap {{ $tanggal['lama'] }}.@endif
                        {{ $tanggal['alasan'] }} ({{ $tanggal['pengguna'] }}, {{ $tanggal['waktu'] }}).
                    </p>
                @endforeach
            </details>
        @endif
    </div>
@endif
