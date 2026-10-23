@props(['statistics'])

@php
    $cards = ['total' => 'Total Perekaman'];
    foreach (\App\Enums\SuratStatus::options() as $value => $label) {
        if (in_array($value, ['draft', 'terbit', 'belum_ditindaklanjuti', 'proses'], true) || ($statistics[$value] ?? 0) > 0) {
            $cards[$value] = $label;
        }
    }
    if (($statistics['tuntas'] ?? 0) > 0) {
        $cards['tuntas'] = 'Tuntas (Alur Lama)';
    }
    if (array_key_exists('terlambat_tindak_lanjut', $statistics)) {
        $cards['terlambat_tindak_lanjut'] = 'Tindak Lanjut Terlambat';
    }
@endphp

<div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
    @foreach ($cards as $value => $label)
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" data-surat-statistic="{{ $value }}">
            <p class="text-sm font-medium text-slate-500">{{ $label }}</p>
            <p class="mt-2 text-3xl font-bold text-slate-800">{{ $statistics[$value] ?? 0 }}</p>
        </div>
    @endforeach
</div>
