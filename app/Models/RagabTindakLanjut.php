<?php

namespace App\Models;

use App\Models\Concerns\TracksUser;
use Illuminate\Database\Eloquent\Model;

class RagabTindakLanjut extends Model
{
    use TracksUser;

    protected $connection = 'mysql_ragab';

    protected $table = 'tb_tindak_lanjut';

    protected $fillable = [
        'id_butir_ragab',
        'unit_kerja_id',
        'tindak_lanjut',
        'deliverables',
        'dokumen',
        'jth_tempo',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'jth_tempo' => 'date',
    ];

    public function butir()
    {
        return $this->belongsTo(RagabButir::class, 'id_butir_ragab', 'id_butir_ragab');
    }

    public function unitKerja()
    {
        return $this->belongsTo(UnitKerja::class, 'unit_kerja_id', 'id');
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
