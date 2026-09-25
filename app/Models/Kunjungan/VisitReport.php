<?php

namespace App\Models\Kunjungan;

use App\Enums\VisitReportStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisitReport extends Model
{
    protected $connection = 'mysql_kunjungan';

    protected $fillable = [
        'visit_id', 'version', 'status', 'original_filename', 'stored_filename', 'file_path',
        'mime_type', 'file_size', 'uploaded_by_user_id', 'reviewed_by_user_id',
        'review_notes', 'is_current', 'uploaded_at', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => VisitReportStatus::class,
            'is_current' => 'boolean',
            'uploaded_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }
}
