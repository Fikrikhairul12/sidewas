<?php

namespace App\Models;

use App\Models\Concerns\TracksUser;
use Illuminate\Database\Eloquent\Model;

class RagabReview extends Model
{
    use TracksUser;

    protected $connection = 'mysql_ragab';

    protected $table = 'tb_review';

    protected $fillable = [
        'id_butir_ragab',
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
        return $this->belongsTo(RagabButir::class, 'id_butir_ragab', 'id_butir_ragab');
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
