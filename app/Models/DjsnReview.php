<?php

namespace App\Models;

use App\Models\Concerns\TracksUser;
use Illuminate\Database\Eloquent\Model;

class DjsnReview extends Model
{
    use TracksUser;

    protected $connection = 'mysql_djsn';

    protected $table = 'tb_review';

    protected $fillable = [
        'id_butir_djsn',
        'id_tanggapan',
        'id_tindak_lanjut',
        'tahap_review',
        'komite_id',
        'hasil_review',
        'deliverables',
        'dokumen',
        'status',
        'created_by',
        'updated_by',
    ];

    public function butir()
    {
        return $this->belongsTo(DjsnButir::class, 'id_butir_djsn', 'id_butir_djsn');
    }

    public function tanggapan()
    {
        return $this->belongsTo(DjsnTanggapan::class, 'id_tanggapan', 'id');
    }

    public function tindakLanjut()
    {
        return $this->belongsTo(DjsnTindakLanjut::class, 'id_tindak_lanjut', 'id');
    }

    public function komite()
    {
        return $this->belongsTo(Komite::class, 'komite_id', 'id');
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
