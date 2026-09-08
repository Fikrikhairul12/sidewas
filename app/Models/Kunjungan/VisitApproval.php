<?php

namespace App\Models\Kunjungan;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisitApproval extends Model
{
    protected $connection = 'mysql_kunjungan';

    protected $fillable = [
        'visit_id',
        'round',
        'decision',
        'acted_by_user_id',
        'notes',
        'decided_at',
    ];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }
}
