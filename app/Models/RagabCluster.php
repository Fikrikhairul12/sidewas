<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RagabCluster extends Model
{
    protected $connection = 'mysql_ragab';

    protected $table = 'tb_cluster';

    protected $fillable = [
        'nama_cluster',
        'keterangan',
    ];

    public function subClusters()
    {
        return $this->hasMany(RagabSubCluster::class, 'cluster_id', 'id');
    }

    public function records()
    {
        return $this->hasMany(RagabRecord::class, 'cluster_id', 'id');
    }
}
