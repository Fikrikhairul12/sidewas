<?php

namespace App\Models;

use App\Models\Concerns\TracksUser;
use Illuminate\Database\Eloquent\Model;

class ProdukHukum extends Model
{
    use TracksUser;

    protected $connection = 'mysql_produk_hukum';

    protected $table = 'tb_produk_hukum';

    protected $fillable = [
        'kode_produk_hukum',
        'tipe_dokumen',
        'judul',
        'nomor_peraturan',
        'tahun_peraturan',
        'jenis_bentuk_peraturan',
        'singkatan_peraturan',
        'tempat_penetapan',
        'tanggal_penetapan',
        'tanggal_diundangkan',
        'sumber_ln',
        'sumber_tln',
        'subjek',
        'bahasa',
        'lokasi',
        'bidang_hukum',
        'abstrak',
        'status_peraturan',
        'sifat_dokumen',
        'status_publish',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'tanggal_penetapan' => 'date',
        'tanggal_diundangkan' => 'date',
    ];

    protected static function booted(): void
    {
        static::creating(function (ProdukHukum $produkHukum): void {
            if (empty($produkHukum->kode_produk_hukum)) {
                $year = $produkHukum->tahun_peraturan ?: now()->year;
                $count = static::where('tahun_peraturan', $year)->count() + 1;
                $produkHukum->kode_produk_hukum = 'PH-' . $year . '-' . str_pad((string) $count, 4, '0', STR_PAD_LEFT);
            }
        });
    }

    public function files()
    {
        return $this->hasMany(ProdukHukumFile::class, 'produk_hukum_id', 'id');
    }

    public function relasis()
    {
        return $this->hasMany(ProdukHukumRelasi::class, 'produk_hukum_id', 'id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by', 'id');
    }
}
