<?php

namespace App\Models;

use App\Models\Concerns\TracksUser;
use Illuminate\Database\Eloquent\Model;

class EksternalTindakLanjut extends Model
{
    use TracksUser;

    protected $connection = 'mysql_eksternal';

    protected $table = 'tb_tindak_lanjut';

    protected $fillable = [
        'id_butir_eksternal',
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
        return $this->belongsTo(EksternalButir::class, 'id_butir_eksternal', 'id_butir_eksternal');
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
