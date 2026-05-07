<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SnpData extends Model
{
    protected $connection = 'mysql_snp';

    protected $table = 'snp_data';

    protected $fillable = [
        'nama',
        'keterangan',
    ];
}
