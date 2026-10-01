<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class UnitKerja extends Model
{
    protected $connection = 'mysql';

    protected $table = 'tb_unit_kerja';

    protected $fillable = [
        'direktorat_id',
        'nama_unit',
        'kode_unit',
        'keterangan',
        'status',
        'managed_from_ui',
    ];

    protected function casts(): array
    {
        return ['managed_from_ui' => 'boolean'];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->whereHas('direktorat', fn (Builder $direktorat) => $direktorat->active());
    }

    public function direktorat()
    {
        return $this->belongsTo(Direktorat::class, 'direktorat_id', 'id');
    }

    public function users()
    {
        return $this->belongsToMany(
            User::class,
            'tb_user_unit_kerja',
            'unit_kerja_id',
            'user_id'
        )->withPivot('status')->withTimestamps();
    }
}
