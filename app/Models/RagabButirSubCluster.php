<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RagabButirSubCluster extends Model
{
    protected $connection = 'mysql_ragab';

    protected $table = 'tb_butir_sub_cluster';

    protected $fillable = [
        'id_butir_ragab',
        'sub_cluster_id',
    ];

    public function butir()
    {
        return $this->belongsTo(RagabButir::class, 'id_butir_ragab', 'id_butir_ragab');
    }

    public function subCluster()
    {
        return $this->belongsTo(RagabSubCluster::class, 'sub_cluster_id', 'id');
    }
}
