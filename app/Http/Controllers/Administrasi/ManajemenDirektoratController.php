<?php

namespace App\Http\Controllers\Administrasi;

use App\Http\Controllers\Controller;
use App\Models\Direktorat;
use App\Models\Komite;
use App\Models\LogActivity;
use App\Models\UnitKerja;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ManajemenDirektoratController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeSuperAdmin($request);

        $filters = $request->validate([
            'keyword' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $allDirektorats = Direktorat::query()->orderBy('nama_direktorat')->get();
        $dewanPengawas = $allDirektorats->first(fn (Direktorat $direktorat) => strcasecmp(trim($direktorat->nama_direktorat), 'Dewan Pengawas') === 0);
        $komites = Komite::query()
            ->withCount(['users as active_users_count' => fn ($users) => $users->where('tb_user_komite.status', 'active')])
            ->orderBy('kode_komite')->orderBy('nama_komite')->get();
        $matchesKomite = ! empty($filters['keyword']) && $komites->contains(fn (Komite $komite) => mb_stripos($komite->nama_komite, $filters['keyword']) !== false
            || mb_stripos($komite->kode_komite ?? '', $filters['keyword']) !== false);

        $direktorats = Direktorat::query()
            ->with(['unitKerja' => fn ($query) => $query
                ->withCount(['users as active_users_count' => fn ($users) => $users->where('tb_user_unit_kerja.status', 'active')])
                ->orderBy('nama_unit')])
            ->when($filters['keyword'] ?? null, fn ($query, $keyword) => $query
                ->where(function ($query) use ($keyword, $matchesKomite, $dewanPengawas) {
                    $query->where('nama_direktorat', 'like', '%'.$keyword.'%')
                        ->orWhere('kode_direktorat', 'like', '%'.$keyword.'%')
                        ->orWhereHas('unitKerja', fn ($units) => $units
                            ->where('nama_unit', 'like', '%'.$keyword.'%')
                            ->orWhere('kode_unit', 'like', '%'.$keyword.'%'))
                        ->when($matchesKomite && $dewanPengawas, fn ($query) => $query->orWhere('id', $dewanPengawas->id));
                }))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderBy('nama_direktorat')
            ->get();

        return view('layouts.administrasi.manajemen-direktorat', compact('direktorats', 'allDirektorats', 'filters', 'dewanPengawas', 'komites'));
    }

    public function storeDirektorat(Request $request): RedirectResponse
    {
        $this->authorizeSuperAdmin($request);
        $validated = $request->validate($this->direktoratRules());

        DB::connection('mysql')->transaction(function () use ($request, $validated) {
            $direktorat = Direktorat::create($validated + ['status' => 'active', 'managed_from_ui' => true]);
            $this->log($request, $direktorat, 'create_direktorat', null, $direktorat->toArray());
        });

        return $this->redirect('Direktorat berhasil ditambahkan.');
    }

    public function updateDirektorat(Request $request, Direktorat $direktorat): RedirectResponse
    {
        $this->authorizeSuperAdmin($request);
        $validated = $request->validate($this->direktoratRules($direktorat));

        DB::connection('mysql')->transaction(function () use ($request, $direktorat, $validated) {
            $before = $direktorat->toArray();
            $direktorat->update($validated);
            $this->log($request, $direktorat, 'update_direktorat', $before, $direktorat->toArray());
        });

        return $this->redirect('Direktorat berhasil diperbarui.');
    }

    public function statusDirektorat(Request $request, Direktorat $direktorat): RedirectResponse
    {
        $this->authorizeSuperAdmin($request);
        $validated = $request->validate(['status' => ['required', Rule::in(['active', 'inactive'])]]);

        DB::connection('mysql')->transaction(function () use ($request, $direktorat, $validated) {
            $direktorat = Direktorat::query()->lockForUpdate()->findOrFail($direktorat->id);

            if ($validated['status'] === 'inactive' && $direktorat->unitKerja()->where('status', 'active')->exists()) {
                throw ValidationException::withMessages(['status' => 'Nonaktifkan semua unit kerja aktif di direktorat ini terlebih dahulu.']);
            }

            if ($validated['status'] === 'inactive' && $this->hasActiveDirectorateWork($direktorat->id)) {
                throw ValidationException::withMessages(['status' => 'Direktorat masih dipakai pada pekerjaan yang berjalan. Selesaikan atau pindahkan penugasannya terlebih dahulu.']);
            }

            $before = $direktorat->toArray();
            $direktorat->update(['status' => $validated['status']]);
            $this->log($request, $direktorat, 'change_direktorat_status', $before, $direktorat->toArray());
        });

        return $this->redirect('Status direktorat berhasil diperbarui.');
    }

    public function destroyDirektorat(Request $request, Direktorat $direktorat): RedirectResponse
    {
        $this->authorizeSuperAdmin($request);

        DB::connection('mysql')->transaction(function () use ($request, $direktorat) {
            $direktorat = Direktorat::query()->lockForUpdate()->findOrFail($direktorat->id);

            if ($direktorat->unitKerja()->exists()) {
                throw ValidationException::withMessages(['direktorat' => 'Direktorat masih memiliki unit kerja.']);
            }

            if (! $direktorat->managed_from_ui || $this->hasReferences($this->direktoratReferences(), $direktorat->id) || $this->wasReferencedInActivityLog('direktorat', $direktorat->id)) {
                throw ValidationException::withMessages(['direktorat' => 'Direktorat yang pernah digunakan tidak dapat dihapus. Nonaktifkan untuk mempertahankan riwayat.']);
            }

            $before = $direktorat->toArray();
            $direktorat->delete();
            $this->log($request, $direktorat, 'delete_direktorat', $before, null);
        });

        return $this->redirect('Direktorat berhasil dihapus.');
    }

    public function storeUnit(Request $request): RedirectResponse
    {
        $this->authorizeSuperAdmin($request);
        $validated = $request->validate($this->unitRules());

        DB::connection('mysql')->transaction(function () use ($request, $validated) {
            $unit = UnitKerja::create($validated + ['status' => 'active', 'managed_from_ui' => true]);
            $this->log($request, $unit, 'create_unit_kerja', null, $unit->toArray());
        });

        return $this->redirect('Unit kerja berhasil ditambahkan.');
    }

    public function updateUnit(Request $request, UnitKerja $unitKerja): RedirectResponse
    {
        $this->authorizeSuperAdmin($request);
        $validated = $request->validate($this->unitRules($unitKerja));

        DB::connection('mysql')->transaction(function () use ($request, $unitKerja, $validated) {
            $unitKerja = UnitKerja::query()->lockForUpdate()->findOrFail($unitKerja->id);

            if ((int) $unitKerja->direktorat_id !== (int) $validated['direktorat_id']
                && ($unitKerja->users()->exists() || $this->hasReferences($this->unitReferences(), $unitKerja->id))) {
                throw ValidationException::withMessages(['direktorat_id' => 'Unit kerja yang pernah digunakan tidak dapat dipindahkan ke direktorat lain.']);
            }

            $before = $unitKerja->toArray();
            $unitKerja->update($validated);
            $this->log($request, $unitKerja, 'update_unit_kerja', $before, $unitKerja->toArray());
        });

        return $this->redirect('Unit kerja berhasil diperbarui.');
    }

    public function statusUnit(Request $request, UnitKerja $unitKerja): RedirectResponse
    {
        $this->authorizeSuperAdmin($request);
        $validated = $request->validate(['status' => ['required', Rule::in(['active', 'inactive'])]]);

        DB::connection('mysql')->transaction(function () use ($request, $unitKerja, $validated) {
            $unitKerja = UnitKerja::query()->lockForUpdate()->findOrFail($unitKerja->id);

            if ($validated['status'] === 'inactive') {
                $activeUsers = $unitKerja->users()->wherePivot('status', 'active')->count();
                if ($activeUsers > 0) {
                    throw ValidationException::withMessages(['status' => "Unit kerja masih memiliki {$activeUsers} user aktif. Pindahkan mereka melalui Manajemen User terlebih dahulu."]);
                }

                if ($this->hasActiveUnitWork($unitKerja->id)) {
                    throw ValidationException::withMessages(['status' => 'Unit kerja masih menjadi PIC pada pekerjaan yang berjalan. Selesaikan atau pindahkan PIC terlebih dahulu.']);
                }
            } elseif ($unitKerja->direktorat?->status !== 'active') {
                throw ValidationException::withMessages(['status' => 'Aktifkan direktoratnya terlebih dahulu.']);
            }

            $before = $unitKerja->toArray();
            $unitKerja->update(['status' => $validated['status']]);
            $this->log($request, $unitKerja, 'change_unit_kerja_status', $before, $unitKerja->toArray());
        });

        return $this->redirect('Status unit kerja berhasil diperbarui.');
    }

    public function destroyUnit(Request $request, UnitKerja $unitKerja): RedirectResponse
    {
        $this->authorizeSuperAdmin($request);

        DB::connection('mysql')->transaction(function () use ($request, $unitKerja) {
            $unitKerja = UnitKerja::query()->lockForUpdate()->findOrFail($unitKerja->id);
            $activeUsers = $unitKerja->users()->wherePivot('status', 'active')->count();

            if ($activeUsers > 0) {
                throw ValidationException::withMessages(['unit_kerja' => "Unit kerja masih memiliki {$activeUsers} user aktif. Pindahkan mereka melalui Manajemen User terlebih dahulu."]);
            }

            if (! $unitKerja->managed_from_ui || $unitKerja->users()->exists() || $this->hasReferences($this->unitReferences(), $unitKerja->id) || $this->wasReferencedInActivityLog('unit_kerja', $unitKerja->id)) {
                throw ValidationException::withMessages(['unit_kerja' => 'Unit kerja yang pernah digunakan tidak dapat dihapus. Nonaktifkan untuk mempertahankan riwayat.']);
            }

            $before = $unitKerja->toArray();
            $unitKerja->delete();
            $this->log($request, $unitKerja, 'delete_unit_kerja', $before, null);
        });

        return $this->redirect('Unit kerja berhasil dihapus.');
    }

    public function storeKomite(Request $request): RedirectResponse
    {
        $this->authorizeSuperAdmin($request);
        $validated = $request->validate($this->komiteRules());

        DB::connection('mysql')->transaction(function () use ($request, $validated) {
            $komite = Komite::create($validated);
            $this->log($request, $komite, 'create_komite', null, $komite->toArray());
        });

        return $this->redirect('Komite berhasil ditambahkan.')->with('open_komite', true);
    }

    public function updateKomite(Request $request, Komite $komite): RedirectResponse
    {
        $this->authorizeSuperAdmin($request);
        $validated = $request->validate($this->komiteRules($komite));

        DB::connection('mysql')->transaction(function () use ($request, $komite, $validated) {
            $komite = Komite::query()->lockForUpdate()->findOrFail($komite->id);
            $before = $komite->toArray();
            $komite->update($validated);
            $this->log($request, $komite, 'update_komite', $before, $komite->toArray());
        });

        return $this->redirect('Komite berhasil diperbarui.')->with('open_komite', true);
    }

    public function destroyKomite(Request $request, Komite $komite): RedirectResponse
    {
        $this->authorizeSuperAdmin($request);

        DB::connection('mysql')->transaction(function () use ($request, $komite) {
            $komite = Komite::query()->lockForUpdate()->findOrFail($komite->id);

            if ($komite->users()->exists()
                || $this->hasReferences($this->komiteReferences(), $komite->id)
                || $this->wasReferencedInActivityLog('komite', $komite->id)) {
                throw ValidationException::withMessages(['komite' => 'Komite yang telah digunakan oleh user atau data operasional tidak dapat dihapus agar riwayat tetap terjaga.']);
            }

            $before = $komite->toArray();
            $komite->delete();
            $this->log($request, $komite, 'delete_komite', $before, null);
        });

        return $this->redirect('Komite berhasil dihapus.')->with('open_komite', true);
    }

    /** @return array<string, array<int, mixed>> */
    private function komiteRules(?Komite $komite = null): array
    {
        return [
            'nama_komite' => ['required', 'string', 'max:255'],
            'kode_komite' => ['nullable', 'string', 'max:100', Rule::unique('mysql.tb_komite', 'kode_komite')->ignore($komite?->id)],
            'keterangan' => ['nullable', 'string'],
        ];
    }

    /** @return array<int, array{string, string, string}> */
    private function komiteReferences(): array
    {
        return [
            ['mysql_snp', 'tb_butir_pic', 'komite_id'],
            ['mysql_snp', 'tb_review', 'komite_id'],
            ['mysql_ragab', 'tb_butir_pic', 'komite_id'],
            ['mysql_rawas', 'tb_butir_pic', 'komite_id'],
            ['mysql_rawas', 'tb_review', 'komite_id'],
            ['mysql_djsn', 'tb_butir_pic', 'komite_id'],
            ['mysql_djsn', 'tb_review', 'komite_id'],
            ['mysql_eksternal', 'tb_butir_pic', 'komite_id'],
        ];
    }

    private function authorizeSuperAdmin(Request $request): void
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
    }

    private function direktoratRules(?Direktorat $direktorat = null): array
    {
        return [
            'nama_direktorat' => ['required', 'string', 'max:255'],
            'kode_direktorat' => ['nullable', 'string', 'max:50', Rule::unique('mysql.tb_direktorat', 'kode_direktorat')->ignore($direktorat?->id)],
            'keterangan' => ['nullable', 'string'],
        ];
    }

    private function unitRules(?UnitKerja $unitKerja = null): array
    {
        return [
            'direktorat_id' => ['required', 'integer', function (string $attribute, mixed $value, \Closure $fail) use ($unitKerja): void {
                $direktorat = Direktorat::find($value);
                if (! $direktorat || ($direktorat->status !== 'active' && $direktorat->id !== $unitKerja?->direktorat_id)) {
                    $fail('Pilih direktorat aktif.');
                }
            }],
            'nama_unit' => ['required', 'string', 'max:255'],
            'kode_unit' => ['nullable', 'string', 'max:100', Rule::unique('mysql.tb_unit_kerja', 'kode_unit')->ignore($unitKerja?->id)],
            'keterangan' => ['nullable', 'string'],
        ];
    }

    /** @return array<int, array{string, string, string}> */
    private function unitReferences(): array
    {
        return [
            ['mysql_snp', 'tb_butir_pic', 'unit_kerja_id'],
            ['mysql_snp', 'tb_tanggapan', 'unit_kerja_id'],
            ['mysql_snp', 'tb_tindak_lanjut', 'unit_kerja_id'],
            ['mysql_ragab', 'tb_butir_pic', 'unit_kerja_id'],
            ['mysql_ragab', 'tb_tindak_lanjut', 'unit_kerja_id'],
            ['mysql_rawas', 'tb_butir_pic', 'unit_kerja_id'],
            ['mysql_rawas', 'tb_tindak_lanjut', 'unit_kerja_id'],
            ['mysql_djsn', 'tb_butir_pic', 'unit_kerja_id'],
            ['mysql_eksternal', 'tb_butir_pic', 'unit_kerja_id'],
            ['mysql_eksternal', 'tb_tindak_lanjut', 'unit_kerja_id'],
            ['mysql_kunjungan', 'visits', 'pic_unit_kerja_id'],
        ];
    }

    /** @return array<int, array{string, string, string}> */
    private function direktoratReferences(): array
    {
        return [
            ['mysql_ragab', 'tb_butir_direktorat', 'direktorat_id'],
            ['mysql_eksternal', 'tb_butir_direktorat', 'direktorat_id'],
        ];
    }

    /** @param array<int, array{string, string, string}> $references */
    private function hasReferences(array $references, int $id): bool
    {
        foreach ($references as [$connection, $table, $column]) {
            if (Schema::connection($connection)->hasTable($table)
                && Schema::connection($connection)->hasColumn($table, $column)
                && DB::connection($connection)->table($table)->where($column, $id)->exists()) {
                return true;
            }
        }

        return false;
    }

    private function hasActiveUnitWork(int $unitId): bool
    {
        foreach ([
            ['mysql_snp', 'snp'],
            ['mysql_ragab', 'ragab'],
            ['mysql_rawas', 'rawas'],
            ['mysql_djsn', 'djsn'],
            ['mysql_eksternal', 'eksternal'],
        ] as [$connection, $module]) {
            $butirTable = 'tb_butir_'.$module;
            $butirId = 'id_butir_'.$module;

            if (Schema::connection($connection)->hasTable('tb_butir_pic')
                && Schema::connection($connection)->hasTable($butirTable)
                && DB::connection($connection)->table('tb_butir_pic as pic')
                    ->join($butirTable.' as butir', 'pic.'.$butirId, '=', 'butir.'.$butirId)
                    ->where('pic.unit_kerja_id', $unitId)
                    ->where('butir.status', '!=', 'selesai_tuntas')
                    ->exists()) {
                return true;
            }
        }

        return Schema::connection('mysql_kunjungan')->hasTable('visits')
            && DB::connection('mysql_kunjungan')->table('visits')
                ->where('pic_unit_kerja_id', $unitId)
                ->whereNotIn('status', ['COMPLETED', 'CANCELLED', 'REJECTED'])
                ->exists();
    }

    private function hasActiveDirectorateWork(int $direktoratId): bool
    {
        foreach ([['mysql_ragab', 'ragab'], ['mysql_eksternal', 'eksternal']] as [$connection, $module]) {
            $butirTable = 'tb_butir_'.$module;
            $butirId = 'id_butir_'.$module;

            if (Schema::connection($connection)->hasTable('tb_butir_direktorat')
                && Schema::connection($connection)->hasTable($butirTable)
                && DB::connection($connection)->table('tb_butir_direktorat as assigned')
                    ->join($butirTable.' as butir', 'assigned.'.$butirId, '=', 'butir.'.$butirId)
                    ->where('assigned.direktorat_id', $direktoratId)
                    ->where('butir.status', '!=', 'selesai_tuntas')
                    ->exists()) {
                return true;
            }
        }

        return false;
    }

    private function wasReferencedInActivityLog(string $field, int $id): bool
    {
        $logs = LogActivity::query()
            ->whereNotIn('table_name', ['tb_direktorat', 'tb_unit_kerja', 'tb_komite'])
            ->where(function ($query) use ($field) {
                $query->where('old_values', 'like', '%'.$field.'%')
                    ->orWhere('new_values', 'like', '%'.$field.'%');

                if (in_array($field, ['unit_kerja', 'komite'], true)) {
                    $query->orWhere('old_values', 'like', '%assignment%')
                        ->orWhere('new_values', 'like', '%assignment%');
                }
            })
            ->cursor();

        foreach ($logs as $log) {
            if ($this->containsReference($log->old_values, $field, $id)
                || $this->containsReference($log->new_values, $field, $id)) {
                return true;
            }
        }

        return false;
    }

    private function containsReference(mixed $value, string $field, int $id): bool
    {
        if (! is_array($value)) {
            return false;
        }

        foreach ($value as $key => $item) {
            if ($key === 'assignment' && in_array($field, ['unit_kerja', 'komite'], true) && is_array($item)
                && ($item['type'] ?? null) === ($field === 'unit_kerja' ? 'unit' : 'komite') && (int) ($item['id'] ?? 0) === $id) {
                return true;
            }

            if (is_string($key) && str_contains($key, $field)
                && $this->referenceValueMatches($item, $id)) {
                return true;
            }

            if (is_array($item) && $this->containsReference($item, $field, $id)) {
                return true;
            }
        }

        return false;
    }

    private function referenceValueMatches(mixed $value, int $id): bool
    {
        if (is_numeric($value)) {
            return (int) $value === $id;
        }

        if (! is_array($value)) {
            return false;
        }

        if (isset($value['id']) && (int) $value['id'] === $id) {
            return true;
        }

        foreach ($value as $item) {
            if ($this->referenceValueMatches($item, $id)) {
                return true;
            }
        }

        return false;
    }

    private function log(Request $request, Direktorat|UnitKerja|Komite $record, string $action, ?array $before, ?array $after): void
    {
        LogActivity::create([
            'user_id' => $request->user()->id,
            'type_code' => 'administrasi',
            'database_name' => 'sidewas',
            'table_name' => $record->getTable(),
            'record_key' => (string) $record->id,
            'action' => $action,
            'description' => 'Super Admin mengubah data master melalui Manajemen Direktorat.',
            'old_values' => $before,
            'new_values' => $after,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    private function redirect(string $message): RedirectResponse
    {
        return redirect()->route('administrasi.manajemen-direktorat.index')->with('success', $message);
    }
}
