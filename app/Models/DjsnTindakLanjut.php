<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSuratPutaran;
use App\Models\Concerns\TracksUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DjsnTindakLanjut extends Model
{
    use BelongsToSuratPutaran;
    use TracksUser;

    protected $connection = 'mysql_djsn';

    protected $table = 'tb_tindak_lanjut';

    protected $fillable = [
        'putaran_id',
        'tahap_id',
        'unit_kerja_id',
        'butir_pic_id',
        'putaran_tl',
        'id_butir_djsn',
        'tindak_lanjut',
        'deliverables',
        'dokumen',
        'jth_tempo',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'jth_tempo' => 'date',
        'submitted_after_close_at' => 'datetime',
        'submitted_after_compilation_at' => 'datetime',
        'changed_after_compilation_at' => 'datetime',
    ];

    public function butir()
    {
        return $this->belongsTo(DjsnButir::class, 'id_butir_djsn', 'id_butir_djsn');
    }

    public function reviews()
    {
        return $this->hasMany(DjsnReview::class, 'id_tindak_lanjut', 'id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by', 'id');
    }

    public function butirPic(): BelongsTo
    {
        return $this->belongsTo(DjsnButirPic::class, 'butir_pic_id');
    }

    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class, 'unit_kerja_id');
    }
}
