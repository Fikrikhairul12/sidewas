<?php

namespace App\Models;

use App\Models\Concerns\TracksUser;
use Illuminate\Database\Eloquent\Model;

class EksternalReview extends Model
{
    use TracksUser;

    protected $connection = 'mysql_eksternal';

    protected $table = 'tb_review';

    protected $fillable = [
        'id_butir_eksternal',
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
        return $this->belongsTo(EksternalButir::class, 'id_butir_eksternal', 'id_butir_eksternal');
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
