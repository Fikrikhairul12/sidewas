<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DjsnCluster extends Model
{
    protected $connection = 'mysql_djsn';
    protected $table = 'tb_cluster';

    protected $fillable = ['nama_cluster', 'keterangan'];

    public function subClusters()
    {
        return $this->hasMany(DjsnSubCluster::class, 'cluster_id', 'id');
    }

    public function records()
    {
        return $this->hasMany(DjsnRecord::class, 'cluster_id', 'id');
    }
}
