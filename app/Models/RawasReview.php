<?php

namespace App\Models;

use App\Models\Concerns\TracksUser;
use Illuminate\Database\Eloquent\Model;

class RawasReview extends Model
{
    use TracksUser;

    protected $connection = 'mysql_rawas';

    protected $table = 'tb_review';

    protected $fillable = [
        'id_butir_rawas',
        'id_tindak_lanjut',
        'komite_id',
        'tahap_review',
        'hasil_review',
        'deliverables',
        'dokumen',
        'status',
        'created_by',
        'updated_by',
    ];

    public function butir()
    {
        return $this->belongsTo(RawasButir::class, 'id_butir_rawas', 'id_butir_rawas');
    }

    public function tindakLanjut()
    {
        return $this->belongsTo(RawasTindakLanjut::class, 'id_tindak_lanjut', 'id');
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
