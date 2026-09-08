<?php

namespace App\Models\Kunjungan;

use App\Enums\VisitStatus;
use App\Models\UnitKerja;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

class Visit extends Model
{
    protected $connection = 'mysql_kunjungan';

    protected $fillable = [
        'visit_number', 'title', 'letter_number', 'destination_unit_id', 'pic_unit_kerja_id',
        'created_by_user_id', 'start_at', 'end_at', 'purpose', 'status',
        'cancelled_reason', 'approval_round', 'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'submitted_at' => 'datetime',
            'status' => VisitStatus::class,
        ];
    }

    public function destinationUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'destination_unit_id');
    }

    public function destinations(): BelongsToMany
    {
        return $this->belongsToMany(Unit::class, 'visit_destinations')->withTimestamps();
    }

    /**
     * Destination collection with a fallback for records created before the pivot existed.
     */
    public function getDestinationListAttribute(): Collection
    {
        $destinations = $this->destinations;

        return $destinations->isNotEmpty()
            ? $destinations
            : collect([$this->destinationUnit])->filter();
    }

    public function picUnitKerja(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class, 'pic_unit_kerja_id');
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'visit_participants')->withTimestamps();
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(VisitApproval::class)->orderByDesc('round');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(VisitReport::class)->orderByDesc('version');
    }

    public function currentReport(): HasOne
    {
        return $this->hasOne(VisitReport::class)->where('is_current', true);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(VisitStatusLog::class)->orderBy('created_at');
    }

    protected function isOverdue(): Attribute
    {
        return Attribute::get(fn () => $this->end_at?->isPast()
            && in_array($this->status, [VisitStatus::APPROVED, VisitStatus::ONGOING], true));
    }

    public function scopeParticipatedBy(Builder $query, int $employeeId): Builder
    {
        return $query->whereHas('participants', fn (Builder $participants) => $participants->whereKey($employeeId));
    }
}
