<form class="card filter-card" method="GET">
    <div class="filter-grid advanced">
        <div class="field"><label for="search">Pencarian</label><input class="input" id="search" name="search" value="{{ request('search') }}" placeholder="Cari nomor, judul, PIC atau peserta..."></div>
        <div class="field"><label for="status">Status</label><select class="select" id="status" name="status"><option value="">Semua status</option>@foreach($statuses as $status)<option value="{{ $status->value }}" @selected(request('status')===$status->value)>{{ $status->label() }}</option>@endforeach</select></div>
        <div class="field"><label for="province">Provinsi</label><select class="select" id="province" name="province"><option value="">Semua provinsi</option>@foreach($provinces as $province)<option value="{{ $province }}" @selected(request('province')===$province)>{{ $province }}</option>@endforeach</select></div>
        <div class="field"><label for="unit_id">Unit Tujuan</label><select class="select" id="unit_id" name="unit_id"><option value="">Semua unit tujuan</option>@foreach($units as $unit)<option value="{{ $unit->id }}" @selected((string)request('unit_id')===(string)$unit->id)>{{ $unit->nama_unit_kerja }}</option>@endforeach</select></div>
        <div class="field"><label for="pic_unit_kerja_id">PIC Unit Kerja</label><select class="select" id="pic_unit_kerja_id" name="pic_unit_kerja_id"><option value="">Semua PIC</option>@foreach($picUnitKerjas as $unit)<option value="{{ $unit->id }}" @selected((string)request('pic_unit_kerja_id')===(string)$unit->id)>{{ $unit->kode_unit }} - {{ $unit->nama_unit }}</option>@endforeach</select></div>
        <div class="field"><label for="employee_id">Peserta</label><select class="select" id="employee_id" name="employee_id"><option value="">Semua peserta</option>@foreach($employees as $employee)<option value="{{ $employee->id }}" @selected((string)request('employee_id')===(string)$employee->id)>{{ $employee->name }}</option>@endforeach</select></div>
        <div class="field"><label for="date_from">Tanggal Mulai</label><input class="input" type="date" id="date_from" name="date_from" value="{{ request('date_from') }}"></div>
        <div class="field"><label for="date_to">Tanggal Akhir</label><input class="input" type="date" id="date_to" name="date_to" value="{{ request('date_to') }}"></div>
        <div style="display:flex;align-items:flex-end;gap:8px"><button class="btn btn-primary" type="submit">Terapkan Filter</button><a class="btn btn-light" href="{{ url()->current() }}">Reset</a></div>
    </div>
</form>

