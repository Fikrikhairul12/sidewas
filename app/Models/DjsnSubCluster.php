<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DjsnSubCluster extends Model
{
    protected $connection = 'mysql_djsn';
    protected $table = 'tb_sub_cluster';

    protected $fillable = ['cluster_id', 'nama_sub_cluster', 'keterangan'];

    public function cluster()
    {
        return $this->belongsTo(DjsnCluster::class, 'cluster_id', 'id');
    }

    public function butirs()
    {
        return $this->hasMany(DjsnButir::class, 'sub_cluster_id', 'id');
    }
}
