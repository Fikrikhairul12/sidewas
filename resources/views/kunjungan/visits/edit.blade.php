<x-layouts.app title="Edit Kunjungan">
    <x-page-header title="Edit Rencana Kunjungan" description="Perbarui informasi pengajuan {{ $visit->visit_number }} sebelum diputuskan atau diajukan ulang." />
    @if($visit->status === \App\Enums\VisitStatus::REJECTED && $visit->approvals()->latest('round')->first()?->notes)
        <div class="card filter-card" style="border-left: 4px solid var(--danger)">
            <strong>Alasan penolakan terakhir</strong>
            <p class="page-description" style="margin-top: 6px">{{ $visit->approvals()->latest('round')->first()->notes }}</p>
        </div>
    @endif
    <form class="card form-card" method="POST" action="{{ route('kunjungan.visits.update',$visit) }}">@csrf @method('PUT')
        @include('kunjungan.visits._form')
        <div class="form-actions"><a class="btn btn-light" href="{{ route('kunjungan.visits.show',$visit) }}">Batal</a><button class="btn btn-primary" type="submit">Simpan Perubahan</button></div>
    </form>
</x-layouts.app>

