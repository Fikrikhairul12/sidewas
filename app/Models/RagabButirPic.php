<?php

namespace App\Models;

use App\Models\Concerns\TracksUser;
use Illuminate\Database\Eloquent\Model;

class RagabButirPic extends Model
{
    use TracksUser;

    protected $connection = 'mysql_ragab';

    protected $table = 'tb_butir_pic';

    protected $fillable = [
        'id_butir_ragab',
        'unit_kerja_id',
        'komite_id',
        'jenis_pic',
        'created_by',
        'updated_by',
    ];

    public function butir()
    {
        return $this->belongsTo(RagabButir::class, 'id_butir_ragab', 'id_butir_ragab');
    }

    public function unitKerja()
    {
        return $this->belongsTo(UnitKerja::class, 'unit_kerja_id', 'id');
    }

    public function komite()
    {
        return $this->belongsTo(Komite::class, 'komite_id', 'id');
    }
}
