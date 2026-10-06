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
use App\Services\SharedClusterCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
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

    public function __construct(private SharedClusterCatalog $catalog) {}

    public function index(Request $request): View
    {
        $this->authorizeSuperAdmin($request);
        $filters = $request->validate(['keyword' => ['nullable', 'string', 'max:255']]);
        $clusters = SnpCluster::query()
            ->with(['subClusters' => fn ($query) => $query->orderBy('nama_sub_cluster')])
            ->when($filters['keyword'] ?? null, fn ($query, $keyword) => $query
                ->where(function ($query) use ($keyword) {
                    $query->where('nama_cluster', 'like', '%'.$keyword.'%')
                        ->orWhereHas('subClusters', fn ($subclusters) => $subclusters->where('nama_sub_cluster', 'like', '%'.$keyword.'%'));
                }))
            ->orderBy('nama_cluster')->get();
        $allClusters = SnpCluster::query()->orderBy('nama_cluster')->get();

        return view('layouts.administrasi.manajemen-cluster', compact('clusters', 'allClusters', 'filters'));
    }

    public function show(Request $request, string $module): RedirectResponse
    {
        $this->authorizeSuperAdmin($request);
        abort_unless(isset(self::MODULES[$module]), 404);

        return redirect()->route('administrasi.manajemen-cluster.index', $request->only('keyword'));
    }

    public function storeCluster(Request $request): RedirectResponse
    {
        $this->authorizeSuperAdmin($request);
        $validated = $request->validate($this->clusterRules());
        $id = $this->catalog->transaction(function () use ($request, $validated): int {
            $sharedKey = (string) Str::uuid();
            $canonicalId = 0;
            foreach (self::MODULES as $module => $config) {
                $record = $config['cluster']::create($validated + ['status' => 'active', 'shared_key' => $sharedKey]);
                $this->log($request, $module, $record, 'create_cluster', null, $record->toArray());
                if ($module === 'snp') {
                    $canonicalId = $record->id;
                }
            }

            return $canonicalId;
        });

        return $this->redirect('Cluster berhasil ditambahkan untuk semua modul.', $id);
    }

    public function updateCluster(Request $request, int $cluster): RedirectResponse
    {
        $this->authorizeSuperAdmin($request);
        $record = SnpCluster::findOrFail($cluster);
        $validated = $request->validate($this->clusterRules($record));
        $this->mutate($request, 'cluster', $cluster, $validated, 'update_cluster');

        return $this->redirect('Cluster berhasil diperbarui untuk semua modul.', $cluster);
    }

    public function statusCluster(Request $request, int $cluster): RedirectResponse
    {
        $this->authorizeSuperAdmin($request);
        $values = $request->validate(['status' => ['required', Rule::in(['active', 'inactive'])]]);
        $this->mutate($request, 'cluster', $cluster, $values, 'change_cluster_status');

        return $this->redirect('Status cluster berhasil diperbarui untuk semua modul.', $cluster);
    }

    public function destroyCluster(Request $request, int $cluster): RedirectResponse
    {
        $this->authorizeSuperAdmin($request);
        $this->mutate($request, 'cluster', $cluster, [], 'delete_cluster', true);

        return $this->redirect('Cluster berhasil dihapus dari semua modul.');
    }

    public function storeSubCluster(Request $request): RedirectResponse
    {
        $this->authorizeSuperAdmin($request);
        $validated = $request->validate([
            'cluster_id' => ['required', 'integer', Rule::exists('mysql_snp.tb_cluster', 'id')->where('status', 'active')],
            'nama_sub_cluster' => ['required', 'string', 'max:255', Rule::unique('mysql_snp.tb_sub_cluster', 'nama_sub_cluster')->where('cluster_id', $request->integer('cluster_id'))],
            'keterangan' => ['nullable', 'string'],
        ]);
        $this->catalog->transaction(function () use ($request, $validated): void {
            $parent = SnpCluster::query()->lockForUpdate()->findOrFail($validated['cluster_id']);
            $sharedKey = (string) Str::uuid();
            foreach (self::MODULES as $module => $config) {
                $localParent = $this->replica($config['cluster'], $parent->shared_key);
                if ($localParent->status !== 'active') {
                    throw ValidationException::withMessages(['cluster_id' => 'Pilih cluster aktif untuk menambah subcluster.']);
                }
                $record = $config['sub_cluster']::create(array_replace($validated, [
                    'cluster_id' => $localParent->id, 'shared_key' => $sharedKey, 'status' => 'active',
                ]));
                $this->log($request, $module, $record, 'create_sub_cluster', null, $record->toArray());
            }
        });

        return $this->redirect('Subcluster berhasil ditambahkan untuk semua modul.', (int) $validated['cluster_id']);
    }

    public function updateSubCluster(Request $request, int $cluster, int $subCluster): RedirectResponse
    {
        $this->authorizeSuperAdmin($request);
        $record = SnpSubCluster::where('cluster_id', $cluster)->findOrFail($subCluster);
        $validated = $request->validate([
            'nama_sub_cluster' => ['required', 'string', 'max:255', Rule::unique('mysql_snp.tb_sub_cluster', 'nama_sub_cluster')->where('cluster_id', $cluster)->ignore($record->id)],
            'keterangan' => ['nullable', 'string'],
        ]);
        $this->mutate($request, 'sub_cluster', $subCluster, $validated, 'update_sub_cluster', false, $cluster);

        return $this->redirect('Subcluster berhasil diperbarui untuk semua modul.', $cluster);
    }

    public function statusSubCluster(Request $request, int $cluster, int $subCluster): RedirectResponse
    {
        $this->authorizeSuperAdmin($request);
        $values = $request->validate(['status' => ['required', Rule::in(['active', 'inactive'])]]);
        $this->mutate($request, 'sub_cluster', $subCluster, $values, 'change_sub_cluster_status', false, $cluster);

        return $this->redirect('Status subcluster berhasil diperbarui untuk semua modul.', $cluster);
    }

    public function destroySubCluster(Request $request, int $cluster, int $subCluster): RedirectResponse
    {
        $this->authorizeSuperAdmin($request);
        $this->mutate($request, 'sub_cluster', $subCluster, [], 'delete_sub_cluster', true, $cluster);

        return $this->redirect('Subcluster berhasil dihapus dari semua modul.', $cluster);
    }

    /** @param array<string, mixed> $values */
    private function mutate(Request $request, string $kind, int $id, array $values, string $action, bool $delete = false, ?int $cluster = null): void
    {
        $this->catalog->transaction(function () use ($request, $kind, $id, $values, $action, $delete, $cluster): void {
            $canonical = self::MODULES['snp'][$kind]::query()->lockForUpdate()->findOrFail($id);
            if ($kind === 'sub_cluster') {
                abort_unless((int) $canonical->cluster_id === $cluster, 404);
            }
            $replicas = [];
            foreach (self::MODULES as $module => $config) {
                $record = $this->replica($config[$kind], $canonical->shared_key);
                if ($delete) {
                    if ($kind === 'cluster' && $record->subClusters()->exists()) {
                        throw ValidationException::withMessages(['cluster' => 'Cluster masih memiliki subcluster. Hapus subcluster yang belum digunakan terlebih dahulu.']);
                    }
                    $field = $kind === 'cluster' ? 'cluster_id' : 'sub_cluster_id';
                    if ($this->hasUsage($module, $field, $record->id)) {
                        throw ValidationException::withMessages([$kind => 'Data sudah digunakan pada modul '.strtoupper($module).'. Nonaktifkan untuk mempertahankan riwayat.']);
                    }
                }
                if ($kind === 'sub_cluster' && ($values['status'] ?? null) === 'active' && $record->cluster?->status !== 'active') {
                    throw ValidationException::withMessages(['status' => 'Aktifkan cluster induknya terlebih dahulu.']);
                }
                $replicas[$module] = $record;
            }
            foreach ($replicas as $module => $record) {
                $before = $record->toArray();
                if ($delete) {
                    $record->delete();
                } else {
                    $record->update($values);
                }
                $this->log($request, $module, $record, $action, $before, $delete ? null : $record->toArray());
            }
        });
    }

    /** @param class-string<Model> $model */
    private function replica(string $model, ?string $sharedKey): Model
    {
        if (! $sharedKey) {
            throw ValidationException::withMessages(['cluster' => 'Katalog belum disatukan. Jalankan migrasi terbaru terlebih dahulu.']);
        }
        $record = $model::query()->where('shared_key', $sharedKey)->lockForUpdate()->first();
        if (! $record) {
            throw ValidationException::withMessages(['cluster' => 'Katalog antar-modul belum lengkap. Periksa sinkronisasi data master.']);
        }

        return $record;
    }

    private function authorizeSuperAdmin(Request $request): void
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
    }

    /** @return array<string, array<int, mixed>> */
    private function clusterRules(?Model $cluster = null): array
    {
        return [
            'nama_cluster' => ['required', 'string', 'max:255', Rule::unique('mysql_snp.tb_cluster', 'nama_cluster')->ignore($cluster?->id)],
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

    private function redirect(string $message, ?int $cluster = null): RedirectResponse
    {
        return redirect()->route('administrasi.manajemen-cluster.index')
            ->with('success', $message)->with('open_cluster', $cluster);
    }
}
