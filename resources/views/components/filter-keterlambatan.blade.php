@if (\App\Services\IsianUnitTahapService::enabled())
    <div>
        <label class="mb-2 block text-sm font-medium text-slate-700">Keterlambatan Tindak Lanjut</label>
        <select name="keterlambatan" class="w-full rounded-xl border-slate-300 text-sm">
            <option value="">Semua</option>
            <option value="terlambat" @selected(request('keterlambatan') === 'terlambat')>Terlambat</option>
            <option value="tidak_terlambat" @selected(request('keterlambatan') === 'tidak_terlambat')>Tanpa penanda terlambat</option>
        </select>
    </div>
@endif
