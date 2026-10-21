<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSuratPutaran;
use App\Models\Concerns\SyncsSuratAwalStatus;
use App\Models\Concerns\TracksUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DjsnTanggapan extends Model
{
    use BelongsToSuratPutaran;
    use SyncsSuratAwalStatus;
    use TracksUser;

    protected $connection = 'mysql_djsn';

    protected $table = 'tb_tanggapan';

    protected $fillable = [
        'putaran_id',
        'unit_kerja_id',
        'butir_pic_id',
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

    public function butirPic(): BelongsTo
    {
        return $this->belongsTo(DjsnButirPic::class, 'butir_pic_id');
    }

    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class, 'unit_kerja_id');
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
