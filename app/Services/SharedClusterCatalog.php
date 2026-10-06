<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class SharedClusterCatalog
{
    public const MODULES = ['snp', 'ragab', 'rawas', 'djsn', 'eksternal'];

    public static function nameKey(string $name): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($name)));
    }

    /** @return array{clusters: array<string, array<string, mixed>>, subclusters: array<string, array<string, mixed>>} */
    public function plan(): array
    {
        $clusters = [];
        $subclusters = [];
        foreach (self::MODULES as $module) {
            $db = DB::connection('mysql_'.$module);
            $parents = [];
            foreach ($db->table('tb_cluster')->orderBy('id')->get() as $row) {
                $identity = self::nameKey($row->nama_cluster);
                if ($identity === '' || in_array($identity, $parents, true)) {
                    throw new RuntimeException('Nama cluster kosong atau duplikat pada modul '.$module.'. Perbaiki data master sebelum menyatukan katalog.');
                }
                $parents[$row->id] = $identity;
                $clusters[$identity] ??= [
                    'shared_key' => $row->shared_key ?? (string) Str::uuid(),
                    'nama_cluster' => trim($row->nama_cluster),
                    'keterangan' => $row->keterangan,
                    'status' => $row->status,
                    'sources' => [],
                ];
                $clusters[$identity]['sources'][$module] = $row->id;
                if ($row->status === 'inactive') {
                    $clusters[$identity]['status'] = 'inactive';
                }
                $clusters[$identity]['keterangan'] ??= $row->keterangan;
            }
            foreach ($db->table('tb_sub_cluster')->orderBy('id')->get() as $row) {
                if (! isset($parents[$row->cluster_id])) {
                    throw new RuntimeException('Subcluster tanpa cluster induk pada modul '.$module.'. Perbaiki hubungan data terlebih dahulu.');
                }
                $parent = $parents[$row->cluster_id];
                $name = self::nameKey($row->nama_sub_cluster);
                $identity = json_encode([$parent, $name], JSON_THROW_ON_ERROR);
                if ($name === '' || isset($subclusters[$identity]['sources'][$module])) {
                    throw new RuntimeException('Nama subcluster kosong atau duplikat pada modul '.$module.'. Perbaiki data master terlebih dahulu.');
                }
                $subclusters[$identity] ??= [
                    'shared_key' => $row->shared_key ?? (string) Str::uuid(),
                    'parent' => $parent,
                    'nama_sub_cluster' => trim($row->nama_sub_cluster),
                    'keterangan' => $row->keterangan,
                    'status' => $row->status,
                    'sources' => [],
                ];
                $subclusters[$identity]['sources'][$module] = $row->id;
                if ($row->status === 'inactive') {
                    $subclusters[$identity]['status'] = 'inactive';
                }
                $subclusters[$identity]['keterangan'] ??= $row->keterangan;
            }
        }

        return compact('clusters', 'subclusters');
    }

    /** @param array{clusters: array<string, array<string, mixed>>, subclusters: array<string, array<string, mixed>>}|null $plan */
    public function synchronize(?array $plan = null): void
    {
        $plan ??= $this->plan();
        $this->transaction(function () use ($plan): void {
            foreach (self::MODULES as $module) {
                $db = DB::connection('mysql_'.$module);
                $parentIds = [];
                foreach ($plan['clusters'] as $identity => $cluster) {
                    $values = array_diff_key($cluster, ['sources' => true]);
                    $values['updated_at'] = now();
                    if (isset($cluster['sources'][$module])) {
                        $id = $cluster['sources'][$module];
                        $db->table('tb_cluster')->where('id', $id)->update($values);
                    } else {
                        $id = $db->table('tb_cluster')->insertGetId($values + ['created_at' => now()]);
                    }
                    $parentIds[$identity] = $id;
                }
                foreach ($plan['subclusters'] as $subcluster) {
                    $values = array_diff_key($subcluster, ['sources' => true, 'parent' => true]);
                    $values['cluster_id'] = $parentIds[$subcluster['parent']];
                    $values['updated_at'] = now();
                    if (isset($subcluster['sources'][$module])) {
                        $db->table('tb_sub_cluster')->where('id', $subcluster['sources'][$module])->update($values);
                    } else {
                        $db->table('tb_sub_cluster')->insert($values + ['created_at' => now()]);
                    }
                }
            }
        });
    }

    public function transaction(Closure $callback): mixed
    {
        $connections = ['mysql', ...array_map(fn (string $module): string => 'mysql_'.$module, self::MODULES)];
        $run = function (int $index) use (&$run, $connections, $callback): mixed {
            if ($index === count($connections)) {
                return $callback();
            }

            return DB::connection($connections[$index])->transaction(fn (): mixed => $run($index + 1));
        };

        return $run(0);
    }
}
