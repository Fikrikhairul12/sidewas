<x-layouts.app title="Ajukan Kunjungan">
    <x-page-header title="Ajukan Rencana Kunjungan" description="Lengkapi rencana kunjungan untuk diperiksa dan diputuskan oleh moderator." />
    <form class="card form-card" method="POST" action="{{ route('kunjungan.visits.store') }}">@csrf
        @include('kunjungan.visits._form')
        <div class="form-actions"><a class="btn btn-light" href="{{ route('kunjungan.visits.index') }}">Batal</a><button class="btn btn-primary" type="submit">Ajukan Kunjungan</button></div>
    </form>
</x-layouts.app>

