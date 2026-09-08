<x-layouts.app title="Daftar Kunjungan">
    <x-page-header title="Daftar Kunjungan" description="Cari, filter, kelola jadwal, dan telusuri seluruh riwayat kunjungan kerja dalam satu halaman.">
        <x-slot:actions>
            <a class="btn btn-light" href="{{ route('kunjungan.map') }}">Lihat Peta</a>
            @can('create', \App\Models\Kunjungan\Visit::class)
                <a class="btn btn-primary" href="{{ route('kunjungan.visits.create') }}">+ Ajukan Kunjungan</a>
            @endcan
        </x-slot:actions>
    </x-page-header>
    @include('kunjungan.visits._filters')
    @include('kunjungan.visits._table',['tableTitle'=>'Semua Kunjungan'])
</x-layouts.app>

