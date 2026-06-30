<?php

namespace App\Models;

use App\Models\Concerns\TracksUser;
use Illuminate\Database\Eloquent\Model;

class ProdukHukumFile extends Model
{
    use TracksUser;

    protected $connection = 'mysql_produk_hukum';

    protected $table = 'tb_produk_hukum_file';

    protected $fillable = [
        'produk_hukum_id',
        'bentuk_file',
        'nama_file',
        'path_file',
        'link_file',
        'mime_type',
        'ukuran_file',
        'jenis_file',
        'created_by',
        'updated_by',
    ];

    public function produkHukum()
    {
        return $this->belongsTo(ProdukHukum::class, 'produk_hukum_id', 'id');
    }
}
