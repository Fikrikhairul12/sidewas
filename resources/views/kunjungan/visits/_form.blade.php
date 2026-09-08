@php
    $editing = isset($visit);
    $participantSelection = collect(old('participant_ids', $editing ? $visit->participants->pluck('id')->all() : [$currentEmployee->id]))->map(fn($id) => (string) $id);
    $storedDestinations = $editing
        ? ($visit->destinations->isNotEmpty() ? $visit->destinations->pluck('id')->all() : [$visit->destination_unit_id])
        : [];
    $destinationSelection = collect(old('destination_unit_ids', $storedDestinations))->map(fn($id) => (string) $id);
    $picSelection = (string) old('pic_unit_kerja_id', $editing ? $visit->pic_unit_kerja_id : '');
@endphp

<div class="form-grid">
    <div class="field field-full">
        <label for="title">Nama Agenda *</label>
        <input class="input @error('title') invalid @enderror" id="title" name="title" value="{{ old('title', $visit->title ?? '') }}" maxlength="150" required placeholder="Contoh: Monitoring Implementasi Sistem Informasi">
        @error('title')<span class="field-error">{{ $message }}</span>@enderror
    </div>

    <div class="field field-full">
        <label for="letter_number">Nomor Surat</label>
        <input class="input @error('letter_number') invalid @enderror" id="letter_number" name="letter_number" value="{{ old('letter_number', $visit->letter_number ?? '') }}" maxlength="100" placeholder="Contoh: 123/DPW/IX/2026">
        @error('letter_number')<span class="field-error">{{ $message }}</span>@enderror
        <span class="help">Isi sesuai nomor surat tugas atau surat kunjungan jika sudah tersedia.</span>
    </div>

    <div class="field field-full">
        <label>Lokasi / Unit Kerja Tujuan *</label>
        <div class="check-picker @error('destination_unit_ids') invalid @enderror @error('destination_unit_ids.*') invalid @enderror" data-check-picker data-validation-message="Pilih minimal satu lokasi tujuan.">
            <div class="check-picker-search">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <input type="search" data-picker-search placeholder="Cari kode, nama unit, kota, atau provinsi..." aria-label="Cari lokasi tujuan">
            </div>
            <div class="check-picker-selected">
                <div class="check-picker-selected-title">LOKASI TERPILIH <span data-picker-count></span></div>
                <div class="check-picker-chips" data-picker-chips></div>
                <span class="check-picker-empty" data-picker-empty>Belum ada lokasi dipilih</span>
            </div>
            <div class="check-picker-list" data-picker-list>
                @foreach($units as $unit)
                    <label class="check-picker-option" data-picker-option>
                        <input type="checkbox" id="destination_unit_{{ $unit->id }}" name="destination_unit_ids[]" value="{{ $unit->id }}" data-picker-checkbox data-chip-label="{{ $unit->kode_unit }} - {{ $unit->nama_unit_kerja }}" @checked($destinationSelection->contains((string) $unit->id))>
                        <span class="picker-checkbox" aria-hidden="true"></span>
                        <span class="check-picker-copy">
                            <strong>{{ $unit->kode_unit }} - {{ $unit->nama_unit_kerja }}</strong>
                            <small>{{ collect([$unit->kab_kota, $unit->provinsi])->filter()->join(', ') ?: 'Wilayah belum tersedia' }}</small>
                        </span>
                    </label>
                @endforeach
            </div>
        </div>
        @error('destination_unit_ids')<span class="field-error">{{ $message }}</span>@enderror
        @error('destination_unit_ids.*')<span class="field-error">{{ $message }}</span>@enderror
        <span class="help">Anda dapat mencentang lebih dari satu lokasi. Data berasal dari master resmi unit kerja.</span>
    </div>

    <div class="field">
        <label for="start_at">Tanggal Mulai *</label>
        <input class="input @error('start_at') invalid @enderror" type="date" id="start_at" name="start_at" value="{{ old('start_at') ? substr((string) old('start_at'), 0, 10) : ($editing ? $visit->start_at->format('Y-m-d') : '') }}" required>
        @error('start_at')<span class="field-error">{{ $message }}</span>@enderror
    </div>
    <div class="field">
        <label for="end_at">Tanggal Selesai *</label>
        <input class="input @error('end_at') invalid @enderror" type="date" id="end_at" name="end_at" value="{{ old('end_at') ? substr((string) old('end_at'), 0, 10) : ($editing ? $visit->end_at->format('Y-m-d') : '') }}" required>
        @error('end_at')<span class="field-error">{{ $message }}</span>@enderror
    </div>
    <div class="field field-full">
        <label for="pic_unit_kerja_id">PIC Unit Kerja *</label>
        <select class="select @error('pic_unit_kerja_id') invalid @enderror" id="pic_unit_kerja_id" name="pic_unit_kerja_id" required>
            <option value="">Pilih PIC unit kerja</option>
            @foreach($picUnitKerjas->groupBy(fn($unit) => $unit->direktorat?->nama_direktorat ?: 'Tanpa Direktorat') as $direktorat => $unitKerjas)
                <optgroup label="{{ $direktorat }}">
                    @foreach($unitKerjas as $unit)
                        <option value="{{ $unit->id }}" @selected($picSelection === (string) $unit->id)>{{ $unit->kode_unit ?: '-' }} - {{ $unit->nama_unit }}</option>
                    @endforeach
                </optgroup>
            @endforeach
        </select>
        @error('pic_unit_kerja_id')<span class="field-error">{{ $message }}</span>@enderror
        <span class="help">Pilihan berasal dari master unit kerja SIDEWASI, sama seperti PIC pada Butir SNP.</span>
    </div>

    <div class="field field-full">
        <label>Peserta *</label>
        <div class="check-picker @error('participant_ids') invalid @enderror @error('participant_ids.*') invalid @enderror" data-check-picker data-validation-message="Pilih minimal satu peserta.">
            <div class="check-picker-search">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <input type="search" data-picker-search placeholder="Cari nama, unit, atau peran SIDEWASI..." aria-label="Cari peserta">
            </div>
            <div class="check-picker-selected">
                <div class="check-picker-selected-title">PESERTA TERPILIH <span data-picker-count></span></div>
                <div class="check-picker-chips" data-picker-chips></div>
                <span class="check-picker-empty" data-picker-empty>Belum ada peserta dipilih</span>
            </div>
            <div class="check-picker-list" data-picker-list>
                @foreach($employees as $employee)
                    <label class="check-picker-option" data-picker-option>
                        <input type="checkbox" id="participant_{{ $employee->id }}" name="participant_ids[]" value="{{ $employee->id }}" data-picker-checkbox data-chip-label="{{ $employee->name }}" @checked($participantSelection->contains((string) $employee->id))>
                        <span class="picker-checkbox" aria-hidden="true"></span>
                        <span class="check-picker-copy">
                            <strong>{{ $employee->name }}</strong>
                            <small>{{ $employee->organizational_unit ?: 'Unit belum tersedia' }}{{ $employee->role_label ? ' · '.$employee->role_label : '' }}</small>
                        </span>
                    </label>
                @endforeach
            </div>
        </div>
        @error('participant_ids')<span class="field-error">{{ $message }}</span>@enderror
        @error('participant_ids.*')<span class="field-error">{{ $message }}</span>@enderror
        <span class="help">Anda otomatis ditambahkan sebagai peserta agar kunjungan tetap dapat diakses.</span>
    </div>

    <div class="field field-full">
        <label for="purpose">Keperluan Kunjungan *</label>
        <textarea class="textarea @error('purpose') invalid @enderror" id="purpose" name="purpose" maxlength="5000" required placeholder="Jelaskan tujuan, ruang lingkup, dan hasil yang diharapkan...">{{ old('purpose', $visit->purpose ?? '') }}</textarea>
        @error('purpose')<span class="field-error">{{ $message }}</span>@enderror
    </div>
</div>

