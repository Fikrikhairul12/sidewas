<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SnpSubCluster extends Model
{
    protected $connection = 'mysql_snp';
    protected $table = 'tb_sub_cluster';

    protected $fillable = ['cluster_id', 'nama_sub_cluster', 'keterangan'];

    public function cluster()
    {
        return $this->belongsTo(SnpCluster::class, 'cluster_id', 'id');
    }

    public function records()
    {
        return $this->hasMany(SnpRecord::class, 'sub_cluster_id', 'id');
    }
}
