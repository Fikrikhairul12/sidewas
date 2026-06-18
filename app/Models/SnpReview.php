<?php

namespace App\Models;

use App\Models\Concerns\TracksUser;
use Illuminate\Database\Eloquent\Model;

class SnpReview extends Model
{
    use TracksUser;

    protected $connection = 'mysql_snp';

    protected $table = 'tb_review';

    protected $fillable = [
        'id_butir_snp',
        'putaran_tl',
        'id_tanggapan',
        'id_tindak_lanjut',
        'tahap_review',
        'komite_id',
        'hasil_review',
        'deliverables',
        'dokumen',
        'dokumen_memo',
        'status',
        'created_by',
        'updated_by',
    ];

    public function butir()
    {
        return $this->belongsTo(SnpButir::class, 'id_butir_snp', 'id_butir_snp');
    }

    public function tanggapan()
    {
        return $this->belongsTo(SnpTanggapan::class, 'id_tanggapan', 'id');
    }

    public function tindakLanjut()
    {
        return $this->belongsTo(SnpTindakLanjut::class, 'id_tindak_lanjut', 'id');
    }

    public function kompilasiTanggapan()
    {
        return $this->hasOne(SnpKompilasi::class, 'id_butir_snp', 'id_butir_snp')
            ->where('tahap_kompilasi', 'tanggapan');
    }

    public function kompilasiTindakLanjut()
    {
        return $this->hasOne(SnpKompilasi::class, 'id_butir_snp', 'id_butir_snp')
            ->where('tahap_kompilasi', 'tindak_lanjut')
            ->latestOfMany('id');
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
