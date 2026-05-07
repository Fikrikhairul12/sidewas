<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SnpCluster extends Model
{
    protected $connection = 'mysql_snp';
    protected $table = 'tb_cluster';

    protected $fillable = ['nama_cluster', 'keterangan'];

    public function subClusters()
    {
        return $this->hasMany(SnpSubCluster::class, 'cluster_id', 'id');
    }

    public function records()
    {
        return $this->hasMany(SnpRecord::class, 'cluster_id', 'id');
    }
}
