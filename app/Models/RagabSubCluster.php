<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RagabSubCluster extends Model
{
    protected $connection = 'mysql_ragab';

    protected $table = 'tb_sub_cluster';

    protected $fillable = [
        'cluster_id',
        'nama_sub_cluster',
        'keterangan',
    ];

    public function cluster()
    {
        return $this->belongsTo(RagabCluster::class, 'cluster_id', 'id');
    }

    public function records()
    {
        return $this->hasMany(RagabRecord::class, 'sub_cluster_id', 'id');
    }
}
