<?php

namespace App\Models;

use App\Models\Concerns\TracksUser;
use Illuminate\Database\Eloquent\Model;

class DjsnTindakLanjut extends Model
{
    use TracksUser;

    protected $connection = 'mysql_djsn';
    protected $table = 'tb_tindak_lanjut';

    protected $fillable = [
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
}
