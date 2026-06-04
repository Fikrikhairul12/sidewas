<?php

namespace App\Models;

use App\Models\Concerns\TracksUser;
use Illuminate\Database\Eloquent\Model;

class DjsnTanggapan extends Model
{
    use TracksUser;

    protected $connection = 'mysql_djsn';

    protected $table = 'tb_tanggapan';

    protected $fillable = [
        'id_butir_djsn',
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
        return $this->belongsTo(DjsnButir::class, 'id_butir_djsn', 'id_butir_djsn');
    }

    public function review()
    {
        return $this->hasOne(DjsnReview::class, 'id_tanggapan', 'id');
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
