<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Direktorat extends Model
{
    protected $connection = 'mysql';

    protected $table = 'tb_direktorat';

    protected $fillable = [
        'nama_direktorat',
        'kode_direktorat',
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
        return $query->where('status', 'active');
    }

    public function unitKerja()
    {
        return $this->hasMany(UnitKerja::class, 'direktorat_id', 'id');
    }
}
