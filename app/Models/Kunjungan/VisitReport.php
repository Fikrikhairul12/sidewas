<?php

namespace App\Models\Kunjungan;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisitReport extends Model
{
    protected $connection = 'mysql_kunjungan';

    protected $fillable = [
        'visit_id', 'version', 'original_filename', 'stored_filename', 'file_path',
        'mime_type', 'file_size', 'uploaded_by_user_id', 'is_current', 'uploaded_at',
    ];

    protected function casts(): array
    {
        return ['is_current' => 'boolean', 'uploaded_at' => 'datetime'];
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }
}
