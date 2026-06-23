<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EksternalCluster extends Model
{
    protected $connection = 'mysql_eksternal';

    protected $table = 'tb_cluster';

    protected $fillable = [
        'nama_cluster',
        'keterangan',
    ];

    public function subClusters()
    {
        return $this->hasMany(EksternalSubCluster::class, 'cluster_id', 'id');
    }

    public function records()
    {
        return $this->hasMany(EksternalRecord::class, 'cluster_id', 'id');
    }
}
