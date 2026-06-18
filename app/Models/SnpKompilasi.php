<?php

namespace App\Models;

use App\Models\Concerns\TracksUser;
use Illuminate\Database\Eloquent\Model;

class SnpKompilasi extends Model
{
    use TracksUser;

    protected $connection = 'mysql_snp';

    protected $table = 'tb_kompilasi';

    protected $fillable = [
        'id_butir_snp',
        'putaran_tl',
        'tahap_kompilasi',
        'hasil_kompilasi',
        'deliverables',
        'dokumen',
        'ubah_tgl',
        'status_pengajuan_tgl',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'ubah_tgl' => 'date',
    ];

    public function butir()
    {
        return $this->belongsTo(SnpButir::class, 'id_butir_snp', 'id_butir_snp');
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
