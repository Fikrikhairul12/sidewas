<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Komite extends Model
{
    protected $connection = 'mysql';
    protected $table = 'tb_komite';

    protected $fillable = [
        'nama_komite',
        'kode_komite',
        'keterangan',
    ];

    public function users()
    {
        return $this->belongsToMany(
            User::class,
            'tb_user_komite',
            'komite_id',
            'user_id'
        )->withPivot('status')->withTimestamps();
    }
}
