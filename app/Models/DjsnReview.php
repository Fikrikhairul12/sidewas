<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSuratPutaran;
use App\Models\Concerns\TracksUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DjsnReview extends Model
{
    use BelongsToSuratPutaran;
    use TracksUser;

    protected $connection = 'mysql_djsn';

    protected $table = 'tb_review';

    protected $fillable = [
        'putaran_id',
        'kompilasi_id',
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

    public function kompilasiPutaran(): BelongsTo
    {
        return $this->belongsTo(DjsnKompilasi::class, 'kompilasi_id');
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
