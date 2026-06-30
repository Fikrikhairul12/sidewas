<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProdukHukumJenisPeraturan extends Model
{
    protected $connection = 'mysql_produk_hukum';

    protected $table = 'tb_jenis_peraturan';

    protected $fillable = [
        'nama',
        'singkatan',
        'urutan',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
