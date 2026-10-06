<?php

namespace App\Services;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ClusterSelection implements DataAwareRule, ValidationRule
{
    /** @var array<string, mixed> */
    private array $data = [];

    public function __construct(private string $module, private ?Model $existing = null) {}

    /** @param array<string, mixed> $data */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $clusterId = $this->data['cluster_id'] ?? null;
        if (filter_var($value, FILTER_VALIDATE_INT) === false || filter_var($clusterId, FILTER_VALIDATE_INT) === false) {
            $fail('Pilih cluster dan subcluster yang valid.');

            return;
        }
        $connection = DB::connection('mysql_'.$this->module);
        $cluster = $connection->table('tb_cluster')->where('id', $clusterId)->first();
        $sameCluster = $this->existing && (int) $this->existing->cluster_id === (int) $clusterId;

        if ($attribute === 'cluster_id') {
            if (! $cluster || ($cluster->status !== 'active' && ! $sameCluster)) {
                $fail('Pilih cluster aktif. Cluster nonaktif hanya dapat dipertahankan pada data yang sudah menggunakannya.');
            }

            return;
        }

        $subCluster = $connection->table('tb_sub_cluster')->where('id', $value)->first();
        if (! $cluster || ! $subCluster || (int) $subCluster->cluster_id !== (int) $clusterId) {
            $fail('Subcluster tidak sesuai dengan cluster yang dipilih.');

            return;
        }

        $existingIds = $this->existing ? [(int) $this->existing->sub_cluster_id] : [];
        if ($this->module === 'ragab' && $this->existing) {
            $existingIds = [...$existingIds, ...$this->existing->subClusters()->pluck('tb_sub_cluster.id')->map(fn ($id): int => (int) $id)->all()];
        }
        $unchanged = $sameCluster && in_array((int) $value, $existingIds, true);
        if (($cluster->status !== 'active' || $subCluster->status !== 'active') && ! $unchanged) {
            $fail('Pilih subcluster aktif pada cluster aktif. Data nonaktif hanya dapat dipertahankan pada penugasan lama.');
        }
    }
}
