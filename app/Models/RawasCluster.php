<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RawasCluster extends Model
{
    protected $connection = 'mysql_rawas';

    protected $table = 'tb_cluster';

    protected $fillable = [
        'nama_cluster',
        'keterangan',
    ];

    public function subClusters()
    {
        return $this->hasMany(RawasSubCluster::class, 'cluster_id', 'id');
    }

    public function records()
    {
        return $this->hasMany(RawasRecord::class, 'cluster_id', 'id');
    }
}
