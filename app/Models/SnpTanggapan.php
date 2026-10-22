<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSuratPutaran;
use App\Models\Concerns\SyncsSuratAwalStatus;
use App\Models\Concerns\TracksUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SnpTanggapan extends Model
{
    use BelongsToSuratPutaran;
    use SyncsSuratAwalStatus;
    use TracksUser;

    protected $connection = 'mysql_snp';

    protected $table = 'tb_tanggapan';

    protected $casts = ['submitted_after_close_at' => 'datetime'];

    protected $fillable = [
        'putaran_id',
        'unit_kerja_id',
        'id_butir_snp',
        'butir_pic_id',
        'tanggapan',
        'deliverables',
        'dokumen',
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

    public function butirPic()
    {
        return $this->belongsTo(SnpButirPic::class, 'butir_pic_id', 'id');
    }

    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class, 'unit_kerja_id');
    }
}
