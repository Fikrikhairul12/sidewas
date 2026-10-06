<?php

namespace App\Http\Controllers\Administrasi;

use App\Http\Controllers\Controller;
use App\Models\DjsnCluster;
use App\Models\DjsnSubCluster;
use App\Models\EksternalCluster;
use App\Models\EksternalSubCluster;
use App\Models\LogActivity;
use App\Models\RagabCluster;
use App\Models\RagabSubCluster;
use App\Models\RawasCluster;
use App\Models\RawasSubCluster;
use App\Models\SnpCluster;
use App\Models\SnpSubCluster;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ManajemenClusterController extends Controller
{
    /** @var array<string, array{label: string, cluster: class-string<Model>, sub_cluster: class-string<Model>}> */
    private const MODULES = [
        'snp' => ['label' => 'SNP', 'cluster' => SnpCluster::class, 'sub_cluster' => SnpSubCluster::class],
        'ragab' => ['label' => 'RAGAB', 'cluster' => RagabCluster::class, 'sub_cluster' => RagabSubCluster::class],
        'rawas' => ['label' => 'RAWAS', 'cluster' => RawasCluster::class, 'sub_cluster' => RawasSubCluster::class],
        'djsn' => ['label' => 'DJSN', 'cluster' => DjsnCluster::class, 'sub_cluster' => DjsnSubCluster::class],
        'eksternal' => ['label' => 'Eksternal', 'cluster' => EksternalCluster::class, 'sub_cluster' => EksternalSubCluster::class],
    ];

    public function index(Request $request): View
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        return view('layouts.administrasi.pilih-modul-cluster', ['modules' => self::MODULES]);
    }

    public function show(Request $request, string $module): View
    {
        $config = $this->authorizeModule($request, $module);
        $filters = $request->validate(['keyword' => ['nullable', 'string', 'max:255']]);
        $clusters = $config['cluster']::query()
            ->with(['subClusters' => fn ($query) => $query->orderBy('nama_sub_cluster')])
            ->when($filters['keyword'] ?? null, fn ($query, $keyword) => $query
                ->where(function ($query) use ($keyword) {
                    $query->where('nama_cluster', 'like', '%'.$keyword.'%')
                        ->orWhereHas('subClusters', fn ($subClusters) => $subClusters->where('nama_sub_cluster', 'like', '%'.$keyword.'%'));
                }))
            ->orderBy('nama_cluster')->get();
        $allClusters = $config['cluster']::query()->orderBy('nama_cluster')->get();
        $moduleLabel = $config['label'];

        return view('layouts.administrasi.manajemen-cluster', compact('module', 'moduleLabel', 'clusters', 'allClusters', 'filters'));
    }

    public function storeCluster(Request $request, string $module): RedirectResponse
    {
        $config = $this->authorizeModule($request, $module);
        $validated = $request->validate($this->clusterRules($module));
        $cluster = DB::connection('mysql_'.$module)->transaction(function () use ($request, $module, $config, $validated) {
            $cluster = $config['cluster']::create($validated);
            $this->log($request, $module, $cluster, 'create_cluster', null, $cluster->toArray());

            return $cluster;
        });

        return $this->redirect($module, 'Cluster berhasil ditambahkan.', $cluster->id);
    }

    public function updateCluster(Request $request, string $module, int $cluster): RedirectResponse
    {
        $config = $this->authorizeModule($request, $module);
        $record = $config['cluster']::findOrFail($cluster);
        $validated = $request->validate($this->clusterRules($module, $record));
        DB::connection('mysql_'.$module)->transaction(function () use ($request, $module, $config, $cluster, $validated) {
            $record = $config['cluster']::query()->lockForUpdate()->findOrFail($cluster);
            $before = $record->toArray();
            $record->update($validated);
            $this->log($request, $module, $record, 'update_cluster', $before, $record->toArray());
        });

        return $this->redirect($module, 'Cluster berhasil diperbarui.', $cluster);
    }

    public function statusCluster(Request $request, string $module, int $cluster): RedirectResponse
    {
        $config = $this->authorizeModule($request, $module);
        $validated = $request->validate(['status' => ['required', Rule::in(['active', 'inactive'])]]);
        DB::connection('mysql_'.$module)->transaction(function () use ($request, $module, $config, $cluster, $validated): void {
            $record = $config['cluster']::query()->lockForUpdate()->findOrFail($cluster);
            $before = $record->toArray();
            $record->update($validated);
            $this->log($request, $module, $record, 'change_cluster_status', $before, $record->toArray());
        });

        return $this->redirect($module, 'Status cluster berhasil diperbarui.', $cluster);
    }

    public function destroyCluster(Request $request, string $module, int $cluster): RedirectResponse
    {
        $config = $this->authorizeModule($request, $module);
        DB::connection('mysql_'.$module)->transaction(function () use ($request, $module, $config, $cluster) {
            $record = $config['cluster']::query()->lockForUpdate()->findOrFail($cluster);
            if ($record->subClusters()->exists()) {
                throw ValidationException::withMessages(['cluster' => 'Cluster masih memiliki subcluster. Hapus subcluster yang belum digunakan terlebih dahulu.']);
            }
            if ($this->hasUsage($module, 'cluster_id', $cluster)) {
                throw ValidationException::withMessages(['cluster' => 'Cluster yang sudah digunakan tidak dapat dihapus agar riwayat tetap terjaga.']);
            }
            $before = $record->toArray();
            $record->delete();
            $this->log($request, $module, $record, 'delete_cluster', $before, null);
        });

        return $this->redirect($module, 'Cluster berhasil dihapus.');
    }

    public function storeSubCluster(Request $request, string $module): RedirectResponse
    {
        $config = $this->authorizeModule($request, $module);
        $validated = $request->validate([
            'cluster_id' => ['required', 'integer', Rule::exists('mysql_'.$module.'.tb_cluster', 'id')->where('status', 'active')],
            'nama_sub_cluster' => ['required', 'string', 'max:255', Rule::unique('mysql_'.$module.'.tb_sub_cluster', 'nama_sub_cluster')->where('cluster_id', $request->integer('cluster_id'))],
            'keterangan' => ['nullable', 'string'],
        ]);
        DB::connection('mysql_'.$module)->transaction(function () use ($request, $module, $config, $validated) {
            $parent = $config['cluster']::query()->lockForUpdate()->findOrFail($validated['cluster_id']);
            if ($parent->status !== 'active') {
                throw ValidationException::withMessages(['cluster_id' => 'Pilih cluster aktif untuk menambah subcluster.']);
            }
            $record = $config['sub_cluster']::create($validated);
            $this->log($request, $module, $record, 'create_sub_cluster', null, $record->toArray());
        });

        return $this->redirect($module, 'Subcluster berhasil ditambahkan.', (int) $validated['cluster_id']);
    }

    public function updateSubCluster(Request $request, string $module, int $cluster, int $subCluster): RedirectResponse
    {
        $config = $this->authorizeModule($request, $module);
        $record = $config['sub_cluster']::where('cluster_id', $cluster)->findOrFail($subCluster);
        $validated = $request->validate([
            'nama_sub_cluster' => ['required', 'string', 'max:255', Rule::unique('mysql_'.$module.'.tb_sub_cluster', 'nama_sub_cluster')->where('cluster_id', $cluster)->ignore($record->id)],
            'keterangan' => ['nullable', 'string'],
        ]);
        DB::connection('mysql_'.$module)->transaction(function () use ($request, $module, $config, $cluster, $subCluster, $validated) {
            $record = $config['sub_cluster']::where('cluster_id', $cluster)->lockForUpdate()->findOrFail($subCluster);
            $before = $record->toArray();
            $record->update($validated);
            $this->log($request, $module, $record, 'update_sub_cluster', $before, $record->toArray());
        });

        return $this->redirect($module, 'Subcluster berhasil diperbarui.', $cluster);
    }

    public function statusSubCluster(Request $request, string $module, int $cluster, int $subCluster): RedirectResponse
    {
        $config = $this->authorizeModule($request, $module);
        $validated = $request->validate(['status' => ['required', Rule::in(['active', 'inactive'])]]);
        DB::connection('mysql_'.$module)->transaction(function () use ($request, $module, $config, $cluster, $subCluster, $validated): void {
            $parent = $config['cluster']::query()->lockForUpdate()->findOrFail($cluster);
            $record = $config['sub_cluster']::where('cluster_id', $cluster)->lockForUpdate()->findOrFail($subCluster);
            if ($validated['status'] === 'active' && $parent->status !== 'active') {
                throw ValidationException::withMessages(['status' => 'Aktifkan cluster induknya terlebih dahulu.']);
            }
            $before = $record->toArray();
            $record->update($validated);
            $this->log($request, $module, $record, 'change_sub_cluster_status', $before, $record->toArray());
        });

        return $this->redirect($module, 'Status subcluster berhasil diperbarui.', $cluster);
    }

    public function destroySubCluster(Request $request, string $module, int $cluster, int $subCluster): RedirectResponse
    {
        $config = $this->authorizeModule($request, $module);
        DB::connection('mysql_'.$module)->transaction(function () use ($request, $module, $config, $cluster, $subCluster) {
            $record = $config['sub_cluster']::where('cluster_id', $cluster)->lockForUpdate()->findOrFail($subCluster);
            if ($this->hasUsage($module, 'sub_cluster_id', $subCluster)) {
                throw ValidationException::withMessages(['sub_cluster' => 'Subcluster yang sudah digunakan tidak dapat dihapus agar riwayat tetap terjaga.']);
            }
            $before = $record->toArray();
            $record->delete();
            $this->log($request, $module, $record, 'delete_sub_cluster', $before, null);
        });

        return $this->redirect($module, 'Subcluster berhasil dihapus.', $cluster);
    }

    /** @return array{label: string, cluster: class-string<Model>, sub_cluster: class-string<Model>} */
    private function authorizeModule(Request $request, string $module): array
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
        abort_unless(isset(self::MODULES[$module]), 404);

        return self::MODULES[$module];
    }

    /** @return array<string, array<int, mixed>> */
    private function clusterRules(string $module, ?Model $cluster = null): array
    {
        return [
            'nama_cluster' => ['required', 'string', 'max:255', Rule::unique('mysql_'.$module.'.tb_cluster', 'nama_cluster')->ignore($cluster?->id)],
            'keterangan' => ['nullable', 'string'],
        ];
    }

    private function hasUsage(string $module, string $column, int $id): bool
    {
        $connection = 'mysql_'.$module;
        $tables = ['tb_record', 'tb_butir_'.$module];
        if ($module === 'ragab' && $column === 'sub_cluster_id') {
            $tables[] = 'tb_butir_sub_cluster';
        }
        foreach ($tables as $table) {
            if (Schema::connection($connection)->hasColumn($table, $column)
                && DB::connection($connection)->table($table)->where($column, $id)->exists()) {
                return true;
            }
        }

        $logs = LogActivity::query()->where('type_code', $module)
            ->whereNotIn('table_name', ['tb_cluster', 'tb_sub_cluster'])
            ->where(fn ($query) => $query->where('old_values', 'like', '%'.$column.'%')->orWhere('new_values', 'like', '%'.$column.'%'))
            ->cursor();
        foreach ($logs as $log) {
            if ($this->containsReference($log->old_values, $column, $id) || $this->containsReference($log->new_values, $column, $id)) {
                return true;
            }
        }

        return false;
    }

    private function containsReference(mixed $values, string $column, int $id): bool
    {
        if (! is_array($values)) {
            return false;
        }
        foreach ($values as $key => $value) {
            if ($key === $column && is_numeric($value) && (int) $value === $id) {
                return true;
            }
            if ($key === $column.'s' && is_array($value) && in_array($id, $value)) {
                return true;
            }
            if (is_array($value) && $this->containsReference($value, $column, $id)) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed>|null $before
     * @param  array<string, mixed>|null  $after
     */
    private function log(Request $request, string $module, Model $record, string $action, ?array $before, ?array $after): void
    {
        LogActivity::create([
            'user_id' => $request->user()->id,
            'type_code' => $module,
            'database_name' => 'sidewas_'.$module,
            'table_name' => $record->getTable(),
            'record_key' => (string) $record->id,
            'action' => $action,
            'description' => 'Super Admin mengubah data master melalui Manajemen Cluster.',
            'old_values' => $before,
            'new_values' => $after,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    private function redirect(string $module, string $message, ?int $cluster = null): RedirectResponse
    {
        return redirect()->route('administrasi.manajemen-cluster.show', $module)
            ->with('success', $message)->with('open_cluster', $cluster);
    }
}
