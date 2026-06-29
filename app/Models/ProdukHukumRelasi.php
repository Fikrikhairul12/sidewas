<?php

namespace App\Models;

use App\Models\Concerns\TracksUser;
use Illuminate\Database\Eloquent\Model;

class ProdukHukumRelasi extends Model
{
    use TracksUser;

    protected $connection = 'mysql_produk_hukum';

    protected $table = 'tb_produk_hukum_relasi';

    protected $fillable = [
        'produk_hukum_id',
        'jenis_relasi',
        'produk_hukum_terkait_id',
        'nomor_peraturan_terkait',
        'judul_terkait',
        'keterangan',
        'created_by',
        'updated_by',
    ];

    public function produkHukum()
    {
        return $this->belongsTo(ProdukHukum::class, 'produk_hukum_id', 'id');
    }

    public function produkHukumTerkait()
    {
        return $this->belongsTo(ProdukHukum::class, 'produk_hukum_terkait_id', 'id');
    }
}
