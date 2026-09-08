@props(['status', 'overdue' => false, 'label' => null])
<span class="badge badge-{{ $status->color() }}">{{ $label ?? $status->label() }}</span>@if($overdue)<span class="overdue">Lewat Jadwal / Perlu Konfirmasi</span>@endif

