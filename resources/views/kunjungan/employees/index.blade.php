<x-layouts.app title="Direktori Pegawai">
    <x-page-header title="Direktori Pegawai" description="Data pegawai, unit, peran, dan status disinkronkan dari master user SIDEWASI." />

    <form class="card filter-card" method="GET">
        <div class="filter-grid">
            <div class="field">
                <label for="search">Pencarian</label>
                <input class="input" id="search" name="search" value="{{ request('search') }}" placeholder="ID, nama, email, unit, atau direktorat...">
            </div>
            <div class="field">
                <label for="status">Status</label>
                <select class="select" id="status" name="status">
                    <option value="">Semua status</option>
                    <option value="active" @selected(request('status') === 'active')>Aktif</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Nonaktif</option>
                </select>
            </div>
            <div style="display:flex;align-items:flex-end;gap:8px">
                <button class="btn btn-primary">Terapkan</button>
                <a class="btn btn-light" href="{{ route('kunjungan.employees.index') }}">Reset</a>
            </div>
        </div>
    </form>

    <section class="card table-card">
        <div class="table-header"><div><h2>Daftar Pegawai SIDEWASI</h2><p>{{ $employees->total() }} user tersinkron. Perubahan data dilakukan dari menu Manajemen User SIDEWASI.</p></div></div>
        <div class="table-scroll">
            <table class="data-table employee-table">
                <thead><tr><th>Pegawai</th><th>Email Login</th><th>Peran SIDEWASI</th><th>Unit Organisasi</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse($employees as $employee)
                        <tr>
                            <td><div class="visit-identity"><strong>ID {{ $employee->sidewas_user_id }}</strong><div class="cell-title">{{ $employee->name }}</div></div></td>
                            <td>{{ $employee->email }}</td>
                            <td>{{ $employee->role_label ?: '—' }}</td>
                            <td>{{ $employee->organizational_unit ?: '—' }}<div class="cell-sub">{{ $employee->directorate }}</div></td>
                            <td><span class="badge {{ $employee->is_active ? 'badge-success' : 'badge-muted' }}">{{ $employee->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><x-empty-state title="Pegawai tidak ditemukan." description="Ubah filter atau sinkronkan kembali master SIDEWASI." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $employees->links() }}
    </section>
</x-layouts.app>

