<?php

namespace App\Models;

use App\Models\Concerns\TracksUser;
use Illuminate\Database\Eloquent\Model;

class SnpTanggapan extends Model
{
    use TracksUser;

    protected $connection = 'mysql_snp';

    protected $table = 'tb_tanggapan';

    protected $fillable = [
        'id_butir_snp',
        'tanggapan',
        'deliverables',
        'dokumen',
        'ubah_tgl',
        'status_pengajuan_tgl',
        'created_by',
        'updated_by',
    ];

    public function butir()
    {
        return $this->belongsTo(SnpButir::class, 'id_butir_snp', 'id_butir_snp');
    }

    public function review()
    {
        return $this->hasOne(SnpReview::class, 'id_tanggapan', 'id');
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
