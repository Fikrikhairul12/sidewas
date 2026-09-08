<?php

namespace App\Models\Kunjungan;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    protected $connection = 'mysql_kunjungan';

    protected $fillable = [
        'kode_wil', 'nama_kapu_kanda', 'kode_unit', 'nama_unit_kerja',
        'provinsi', 'kab_kota', 'latitude', 'longitude',
    ];

    protected function casts(): array
    {
        return ['latitude' => 'float', 'longitude' => 'float'];
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class, 'destination_unit_id');
    }

    public function destinationVisits(): BelongsToMany
    {
        return $this->belongsToMany(Visit::class, 'visit_destinations')->withTimestamps();
    }
}
