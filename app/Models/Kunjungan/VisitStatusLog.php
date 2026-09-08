<?php

namespace App\Models\Kunjungan;

use App\Enums\VisitStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisitStatusLog extends Model
{
    protected $connection = 'mysql_kunjungan';

    public $timestamps = false;

    protected $fillable = ['visit_id', 'from_status', 'to_status', 'actor_user_id', 'notes', 'created_at'];

    protected function casts(): array
    {
        return ['from_status' => VisitStatus::class, 'to_status' => VisitStatus::class, 'created_at' => 'datetime'];
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }
}
