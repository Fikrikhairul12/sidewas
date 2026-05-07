<?php

namespace App\Models;

use App\Models\Concerns\TracksUser;
use Illuminate\Database\Eloquent\Model;

class SnpTindakLanjut extends Model
{
    use TracksUser;

    protected $connection = 'mysql_snp';
    protected $table = 'tb_tindak_lanjut';

    protected $fillable = [
        'id_butir_snp',
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
        return $this->belongsTo(SnpButir::class, 'id_butir_snp', 'id_butir_snp');
    }

    public function reviews()
    {
        return $this->hasMany(SnpReview::class, 'id_tindak_lanjut', 'id');
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
